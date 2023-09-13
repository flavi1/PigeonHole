<?php
namespace PigeonHole;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);



class PigeonHole {
	
	/* 1 - SINGLETONS STORAGE */
	
	static protected $singletons = [];
	
	public static function getSingleton(string $singletonType)
	{
		return static::$singletons[$singletonType] ?? false;
	}
	
	public static function hasSingleton(string $singletonType)
	{
		return isset(static::$singletons[$singletonType]);
	}
	
	public static function setSingleton(string $singletonType, $singleton)
	{
		if(!is_object($singleton))
			throw new \RuntimeException('Singleton expected to be an object('.gettype($singleton).' given).');
		
		if(isset(static::$singletons[$singletonType]))
			throw new \RuntimeException('Singleton '.$singletonType.' alerady stored');
		else
			static::$singletons[$singletonType] = $singleton;
	}
	
	/* 2 - RESSOURCE PATH MANAGER */
	
	// Expected parameters type (for transformers)
	const PATH_PARAMS = 0;
	const RESSOURCE_PARAMS = 1;
	
	protected static $globalPaths = [];
	protected static $patterns = [];
	protected static $transformers = [];
	
    /**
     * @var array Array of default match types (regex helpers)
     */
    protected static $matchTypes = [
        'i'  => '[0-9]++',
        'a'  => '[0-9A-Za-z]++',
        'h'  => '[0-9A-Fa-f]++',
        '*'  => '.+?',
        '**' => '.++',
        ''   => '[^/\.]++'
    ];
	
	protected static function isArrayOfString($v, $empty = true)	// (or empty)
	{
		if(is_array($v)) {
			if(!$empty and empty($v)) {
				return false;
			} else {
				foreach($v as $str)
					if(!is_string($str))
						return false;
			}
		}
		else
			return false;
		return true;
	}
	
	// $path string or array of strings
	public static function setGlobalPath(string $name, $path)
	{
		if(!is_string($path) and !self::isArrayOfString($path))
			throw new \RuntimeException("Global Path {$name} must be a string or an array of strings.");
		if(is_string($path))
			$path = [$path];
		static::$globalPaths[$name] = $path;
	}
	
	// $path string cette fois
	public static function appendGlobalPath(string $name, string $path)
	{
		static::$globalPaths[$name][] = $path;
	}
	
	// $path string cette fois
	public static function prependGlobalPath(string $name, string $path)
	{
		array_unshift(static::$globalPaths[$name], $path);
	}
	
	public static function getGlobalPath(string $name)
	{
		return static::$globalPaths[$name];
	}
	
	public static function map(string $type, string $pathType, string $pattern, $tranform = null)
	{
		if($tranform and !is_callable($tranform))
			throw new \RuntimeException('Mapping Error : transformer parameter must be callable.');
		
		static::$patterns[$type][$pathType] = $pattern;
		if($tranform)
			static::$transformers[$type][$pathType] = $tranform;
	}
	
    /**
     * Add named match types. It uses array_merge so keys can be overwritten.
     *
     * @param array $matchTypes The key is the name and the value is the regex.
     */
    public static function setMatchTypes(array $matchTypes)
    {
        static::$matchTypes = array_merge(static::$matchTypes, $matchTypes);
    }

    protected static function patternToPath($pattern, array $pathParameters = [])
    {
        $path = $pattern;
        
        $globalPath = false;
        $globalPathRef = null;
        
		foreach(static::$globalPaths as $globalPathRef => $globalPath)
			if(strpos($path, "%{$globalPathRef}%") !== false) {
				//$path = substr($path, strlen("%{$globalPathRef}%"));
				break;	// OK => On a $globalPathRef + $globalPath
			}
        if (preg_match_all('`(/|\.|)\[([^:\]]*+)(?::([^:\]]*+))?\](\?|)`', $pattern, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $index => $match) {
                list($block, $pre, $type, $param, $optional) = $match;

                if ($pre) {
                    $block = substr($block, 1);
                }

                if (isset($pathParameters[$param])) {
                    // Part is found, replace for param value
                    $path = str_replace($block, $pathParameters[$param], $path);
                } elseif ($optional && $index !== 0) {
                    // Only strip preceding slash if it's not at the base
                    $path = str_replace($pre . $block, '', $path);
                } else {
                    // Strip match block
                    $path = str_replace($block, '', $path);
                }
            }
        }
		
		if(is_array($globalPath)) {
			$path = array_map(
				fn($globalPathItem): string => str_replace("%{$globalPathRef}%", $globalPathItem, $path),
				$globalPath
			);
		}
		
        return $path;
    }
    
    public static function generatePaths($type, $ressourceParams, $pathType = null)
    {
		if(!isset(static::$patterns[$type])) {
			return (\trigger_error("Warning : Ressource type {$type} has no pattern.", E_USER_WARNING) and false);
		}
		if(!$pathType) {
			$pathTypeList = [];
			foreach(static::$patterns[$type] as $k => $v)
				$pathTypeList[$k] = $k;
			return array_map(
				fn($pathTypeItem) => static::generatePaths($type, $ressourceParams, $pathTypeItem),
				$pathTypeList
			);
		}
		// Transform $ressourceParams to $pathParams
		if(isset(static::$transformers[$type][$pathType])) {
			if(!is_callable(static::$transformers[$type][$pathType]))
				throw new \RuntimeException("Transformer for ressource type {$type} and path type {$pathType} must be callable.");
			$pathParams = call_user_func(static::$transformers[$type][$pathType], $ressourceParams, self::PATH_PARAMS);
			if(!is_array($pathParams) and $pathParams !== false)
				throw new \RuntimeException("Transformer for ressource type {$type} and path type {$pathType} must return an array or false.");
		}
		else
			$pathParams = $ressourceParams;
		if(!$pathParams)
			return false;
		return static::patternToPath(static::$patterns[$type][$pathType], $pathParams) ?? false;
	}
	
    /**
     * Match a given Request Url against stored routes
     * @param string $path
     * @return array|boolean Array with route information on success, false on failure (no match).
     */
    public static function resolve($path, $filter = true)
    {
        $pathParams = [];
		
		// strip global paths
		foreach(static::$globalPaths as $k => $gp_arr)
			foreach($gp_arr as $gp)
				if(strpos($path, $gp) !== false) {
					//$path = substr($path, strlen("%{$k}%"));
					$path = str_replace($gp, "%{$k}%", $path);
					break;
				}

        // Strip query string (?a=b) from Request Url
        if (($strpos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $strpos);
        }

        $lastPathChar = $path ? $path[strlen($path)-1] : '';

		foreach(static::$patterns as $ressourceType => $ressourcePatterns)
			foreach ($ressourcePatterns as $pathType => $pattern) {
			//foreach ($ressourcePatterns as $pathType => $handler) {
				//list($defaultParams, $pattern) = $handler;

				if ($pattern === '*') {
					// * wildcard (matches all)
					$match = true;
				} elseif (isset($pattern[0]) && $pattern[0] === '@') {
					// @ regex delimiter
					$regexPattern = '`' . substr($pattern, 1) . '`u';
					$match = preg_match($regexPattern, $path, $pathParams) === 1;
				} elseif (($position = strpos($pattern, '[')) === false) {
					// No params in url, do string comparison
					$match = strcmp($path, $pattern) === 0;
				} else {
					// Compare longest non-param string with url before moving on to regex
					// Check if last character before param is a slash, because it could be optional if param is optional too (see https://github.com/dannyvankooten/AltoRouter/issues/241)
					if (strncmp($path, $pattern, $position) !== 0 && ($lastPathChar === '/' || $pattern[$position-1] !== '/')) {
						continue;
					}

					$regex = static::compilePattern($pattern);
					$match = preg_match($regex, $path, $pathParams) === 1;
				}

				if ($match) {
					if ($pathParams) {
						foreach ($pathParams as $key => $value) {
							if (is_numeric($key)) {
								unset($pathParams[$key]);
							}
						}
					}

					// Transform $pathParams to $ressourceParams
					if(isset(static::$transformers[$ressourceType][$pathType])) {
						if(!is_callable(static::$transformers[$ressourceType][$pathType]))
							throw new \RuntimeException("Transformer for ressource type {$ressourceType} and path type {$pathType} must be callable.");
						if(!empty($pathParams))
							$opt = $pathParams;
						else
							$opt = $path;
						$ressourceParams = call_user_func(static::$transformers[$ressourceType][$pathType], $opt, self::RESSOURCE_PARAMS);
					}
					else
						$ressourceParams = $pathParams;
					
					return [
						'type' => $ressourceType,
						'params' => $ressourceParams,
						'path_type' => $pathType,
					];
				}
			}

        return false;
    }

    /**
     * Compile the regex for a given pattern (EXPENSIVE)
     * @param $pattern
     * @return string
     */
    protected static function compilePattern($pattern)
    {
        if (preg_match_all('`(/|\.|)\[([^:\]]*+)(?::([^:\]]*+))?\](\?|)`', $pattern, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                list($block, $pre, $type, $param, $optional) = $match;

                if (isset(static::$matchTypes[$type])) {
                    $type = static::$matchTypes[$type];
                }
                if ($pre === '.') {
                    $pre = '\.';
                }

                $optional = $optional !== '' ? '?' : null;

                //Older versions of PCRE require the 'P' in (?P<named>)
                $regex = '(?:'
                        . ($pre !== '' ? $pre : null)
                        . '('
                        . ($param !== '' ? "?P<$param>" : null)
                        . $type
                        . ')'
                        . $optional
                        . ')'
                        . $optional;

                $pattern = str_replace($block, $regex, $pattern);
            }
        }
        return "`^$pattern$`u";
    }  
}

return;

PigeonHole::setGlobalPath('root', '/var/www');
PigeonHole::setGlobalPath('theme', ['/var/themes/extendable_theme', '/var/themes/child_theme']);
PigeonHole::setGlobalPath('www', 'http://mydomain.com');
PigeonHole::map('page', 'theme_path', '%theme%/[a:name].tpl');
PigeonHole::map('page', 'src_path', '%root%/templates/[a:name].tpl');
PigeonHole::map('page', 'cache_path', '%root%/cache/[a:name].php');
PigeonHole::map('page', 'url', '%www%/[a:name]');

print_r(PigeonHole::generatePaths('page', ['name' => 'youpi']));

/*


Array
(
    [theme_path] => Array
        (
            [0] => /var/themes/extendable_theme/youpi.tpl
            [1] => /var/themes/child_theme/youpi.tpl
        )

    [src_path] => Array
        (
            [0] => /var/www/templates/youpi.tpl
        )

    [cache_path] => Array
        (
            [0] => /var/www/cache/youpi.php
        )

    [url] => Array
        (
            [0] => http://mydomain.com/youpi
        )

)

*/


print_r(PigeonHole::resolve('/var/www/templates/youpi.tpl'));

/*
Array
(
    [type] => page
    [params] => Array
        (
            [name] => youpi
        )

    [path_type] => src_path
)
*/

print_r(PigeonHole::resolve('http://mydomain.com/youpi'));

/*
Array
(
    [type] => page
    [params] => Array
        (
            [name] => youpi
        )

    [path_type] => url
)
*/


print_r(PigeonHole::resolve('/var/themes/child_theme/youpi.tpl'));

/*
Array
(
    [type] => page
    [params] => Array
        (
            [name] => youpi
        )

    [path_type] => theme_path
)
*/


/*
TODO :

Tests

filter param pour resolve ?
(=> si false continuer, ex file_exists...)

PSR autoloader

Router

*/

// VENDORS RESSOURCE EXAMPLES :

PigeonHole::setGlobalPath('vendors', '/var/vendors');

PigeonHole::map('class', 'class_path', '%vendors%/[a:vendor]/src/[a:class_name].php');
PigeonHole::map('template', 'src_path', '%vendors%/[a:vendor]/templates/[a:name].tpl');
PigeonHole::map('template', 'theme_src_path', '%theme%/[a:vendor]/templates/[a:name].tpl');
PigeonHole::map('template', 'cache_path', '%root%/cache/templates/[a:vendor]/[a:name].php');


print_r(PigeonHole::generatePaths('class', ['vendor' => 'Acme', 'class_name' => 'Wheel']));
print_r(PigeonHole::generatePaths('template', ['vendor' => 'Acme', 'name' => 'Wheel']));

print_r(PigeonHole::resolve('/var/www/cache/templates/Acme/Wheel.php'));
print_r(PigeonHole::resolve('/var/themes/child_theme/Acme/templates/Wheel.tpl'));

// OTHER EXAMPLES

$routeHandler = function($opt, $resolve) {
	if($resolve) {
		if( $opt['category'] == 'valid' and $opt['title'] == 'exists' )
			return [
				'id' => 3,
				'category_id' => 1
			];
	} elseif( $opt['id'] == 3 and $opt['category_id'] == 1 ) {
		return [
			'category' => 'valid',
			'title' => 'exists'
		];
	}
	return false;
};

PigeonHole::map('article', 'view', '%www%/[a:category]/[a:title]/view', $routeHandler);
PigeonHole::map('article', 'edit', '%www%/[a:category]/[a:title]/edit', $routeHandler);
PigeonHole::map('article', 'remove', '%www%/[a:category]/[a:title]/remove', $routeHandler);


echo 'ARTICLE GOOD'."\n";

print_r(PigeonHole::generatePaths('article', [
	'id' => 3,
	'category_id' => 1
]));

echo 'REVERSE'."\n";

print_r(PigeonHole::resolve('http://mydomain.com/valid/exists/edit'));


echo 'ARTICLE BAD'."\n";

var_dump(PigeonHole::generatePaths('article', [
	'id' => 3,
	'category_id' => 2	// bad
]));



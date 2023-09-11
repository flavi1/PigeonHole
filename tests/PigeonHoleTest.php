<?php
namespace PigeonHole;

use PHPUnit\Framework\TestCase;


set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // error was suppressed with the @-operator
    if (0 === error_reporting()) {
        return false;
    }
    
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);	// to catch Warnings (see https://github.com/sebastianbergmann/phpunit/issues/5062)
});

class testHelper extends PigeonHole
{
	
	protected static $_testHelperInstance = null;
	
	public static function getTestHelperInstance()
	{
		if(!self::$_testHelperInstance)
		{
			$cn = '\\'.get_called_class();
			self::$_testHelperInstance = new $cn();
		}
		return self::$_testHelperInstance;
	} 
	
	static public function reset()
	{
		self::$singletons = [];
        self::$globalPaths = [];
        self::$patterns = [];
        self::$transformers = [];
        
        self::$matchTypes = [
            'i'  => '[0-9]++',
            'a'  => '[0-9A-Za-z]++',
            'h'  => '[0-9A-Fa-f]++',
            '*'  => '.+?',
            '**' => '.++',
            ''   => '[^/\.]++',
        ];
        
	}
	
	public function __isset($k)
	{
		return isset(self::${$k});
	}
	
	public function __get($k)
	{
		return self::${$k};
	}
	
    static public function __callStatic($methodName, $_args) {
		$args = [];
		foreach($_args as $_arg)
			$args[] = var_export($_arg, true);
        if (method_exists(self::class, $methodName)) {
            $evalStr = sprintf(
                'return self::%s(%s);',
                $methodName,
                implode(', ', $args)
            );

            return eval($evalStr);
        } else {
            throw new \BadMethodCallException("Method $methodName does not exist.");
        }
    }
	
	
}

class PigeonHoleTest extends TestCase
{
    public function setUp(): void
    {
        // Réinitialise les singletons et les paramètres globaux avant chaque test
		
		testHelper::reset();
    }

    public function testGetSingleton()
    {
        // Teste la récupération d'un singleton
        $singleton = new \stdClass();
        PigeonHole::setSingleton('example', $singleton);

        $result = PigeonHole::getSingleton('example');
        $this->assertSame($singleton, $result);
    }

    public function testHasSingleton()
    {
        // Teste la vérification de l'existence d'un singleton
        $singleton = new \stdClass();
        PigeonHole::setSingleton('example', $singleton);

        $this->assertTrue(PigeonHole::hasSingleton('example'));
        $this->assertFalse(PigeonHole::hasSingleton('nonexistent'));
    }

    public function testSetGlobalPath()
    {
        PigeonHole::setGlobalPath('root', '/var/www');

        // Vérifie que le chemin global 'root' a été correctement défini
        $this->assertEquals(['/var/www'], testHelper::getTestHelperInstance()->globalPaths['root']);
    }

    public function testAppendGlobalPath()
    {
        PigeonHole::setGlobalPath('theme', '/var/themes/extendable_theme');
        PigeonHole::appendGlobalPath('theme', '/var/themes/child_theme');

        // Vérifie que le chemin global 'theme' a été correctement étendu
        $this->assertEquals(['/var/themes/extendable_theme', '/var/themes/child_theme'], testHelper::getTestHelperInstance()->globalPaths['theme']);
    }

    public function testPrependGlobalPath()
    {
        PigeonHole::setGlobalPath('theme', '/var/themes/child_theme');
        PigeonHole::prependGlobalPath('theme', '/var/themes/extendable_theme');

        // Vérifie que le chemin global 'theme' a été correctement prépendu
        $this->assertEquals(['/var/themes/extendable_theme', '/var/themes/child_theme'], testHelper::getTestHelperInstance()->globalPaths['theme']);
    }

    public function testGetGlobalPath()
    {
        PigeonHole::setGlobalPath('root', '/var/www');
        PigeonHole::setGlobalPath('theme', '/var/themes/child_theme');

        // Vérifie que la méthode getGlobalPath retourne correctement les chemins globaux
        $this->assertEquals(['/var/www'], PigeonHole::getGlobalPath('root'));
        $this->assertEquals(['/var/themes/child_theme'], PigeonHole::getGlobalPath('theme'));
    }
    
    public function testIsArrayOfString()
    {

		$trueTests = [
			'an array' => ['An', 'Array of strings'],
			'another array' => ['An' => 'other', 'Associative' =>'array'],
		];
		$falseTests = [
			new \stdClass(),
			'a string' => 'a String',
			'NOT stricktly an array of string' => ['An' => 'other', 'Associative' =>null],
			123,
			true,
			null
		];
		foreach($trueTests as $k => $t)
		{
			$message = is_string($k) ? $k : false;
			$this->assertTrue(testHelper::isArrayOfString($t), $message);
		}
		foreach($falseTests as $k => $t)
		{
			$message = is_string($k) ? $k : false;
			$this->assertFalse(testHelper::isArrayOfString($t), $message);
		}
		$this->assertTrue(testHelper::isArrayOfString([], true), 'test empty parameter is true');
		$this->assertFalse(testHelper::isArrayOfString([], false), 'test empty parameter is false');

	}
	
    public function testMap()
    {
		$th = testHelper::getTestHelperInstance();
        // Définissez un transformateur de test
        $transformer = function ($params, $paramType) {
            return $params;
        };

        PigeonHole::map('page', 'theme_path', '%theme%/[a:name].tpl', $transformer);

        // Vérifie que le mapping a été correctement défini
        $this->assertEquals('%theme%/[a:name].tpl', $th->patterns['page']['theme_path']);
        $this->assertEquals($transformer, $th->transformers['page']['theme_path']);
    }
    
    public function testSetMatchTypes()
    {
		$th = testHelper::getTestHelperInstance();
        $defaultMatchTypes = [
            'i'  => '[0-9]++',
            'a'  => '[0-9A-Za-z]++',
            'h'  => '[0-9A-Fa-f]++',
            '*'  => '.+?',
            '**' => '.++',
            ''   => '[^/\.]++',
        ];
		
        // Définissez de nouveaux types de correspondance
        $newMatchTypes = [
            'custom1' => '[A-Z]++',
            'custom2' => '[a-z]++',
        ];
        
        $expected = array_merge($defaultMatchTypes, $newMatchTypes);

        PigeonHole::setMatchTypes($newMatchTypes);

        // Vérifie que les nouveaux types de correspondance ont été correctement définis
        $this->assertEquals($th->matchTypes, $expected);
    }
    
    
    public function testPatternToPathSrcPath()
    {
        PigeonHole::setGlobalPath('root', '/var/www');
        PigeonHole::setGlobalPath('theme', '/var/themes/child_theme');

        PigeonHole::map('page', 'src_path', '%root%/templates/[a:name].tpl');

        $pattern = '%root%/templates/[a:name].tpl';
        $pathParams = ['name' => 'youpi'];

        $result = testHelper::patternToPath($pattern, $pathParams);

        // Vérifie que le résultat est correct
        $expected = ['/var/www/templates/youpi.tpl'];
        $this->assertEquals($expected, $result);
    }

    public function testPatternToPathUrl()
    {
        PigeonHole::setGlobalPath('www', 'http://mydomain.com');

        PigeonHole::map('page', 'url', '%www%/[a:name]');

        $pattern = '%www%/[a:name]';
        $pathParams = ['name' => 'youpi'];

        $result = testHelper::patternToPath($pattern, $pathParams);

        // Vérifie que le résultat est correct
        $expected = ['http://mydomain.com/youpi'];
        $this->assertEquals($expected, $result);
    }

    public function testPatternToPathThemePath()
    {
        PigeonHole::setGlobalPath('theme', '/var/themes/child_theme');

        PigeonHole::map('page', 'theme_path', '%theme%/[a:name].tpl');

        $pattern = '%theme%/[a:name].tpl';
        $pathParams = ['name' => 'youpi'];

        $result = testHelper::patternToPath($pattern, $pathParams);

        // Vérifie que le résultat est correct
        $expected = ['/var/themes/child_theme/youpi.tpl'];
        $this->assertEquals($expected, $result);
    }


    public function testResolveSrcPath()
    {
        PigeonHole::setGlobalPath('root', '/var/www');
        PigeonHole::setGlobalPath('theme', '/var/themes/child_theme');

        PigeonHole::map('page', 'src_path', '%root%/templates/[a:name].tpl');

        $path = '/var/www/templates/youpi.tpl';

        $result = PigeonHole::resolve($path);

        // Vérifie que le résultat est correct
        $expected = [
            'type' => 'page',
            'params' => ['name' => 'youpi'],
            'path_type' => 'src_path'
        ];
        $this->assertEquals($expected, $result);
    }

    public function testResolveUrl()
    {
        PigeonHole::setGlobalPath('www', 'http://mydomain.com');

        PigeonHole::map('page', 'url', '%www%/[a:name]');

        $path = 'http://mydomain.com/youpi';

        $result = PigeonHole::resolve($path);

        // Vérifie que le résultat est correct
        $expected = [
            'type' => 'page',
            'params' => ['name' => 'youpi'],
            'path_type' => 'url'
        ];
        $this->assertEquals($expected, $result);
    }

    public function testResolveThemePath()
    {
        PigeonHole::setGlobalPath('theme', '/var/themes/child_theme');

        PigeonHole::map('page', 'theme_path', '%theme%/[a:name].tpl');

        $path = '/var/themes/child_theme/youpi.tpl';

        $result = PigeonHole::resolve($path);

        // Vérifie que le résultat est correct
        $expected = [
            'type' => 'page',
            'params' => ['name' => 'youpi'],
            'path_type' => 'theme_path'
        ];
        $this->assertEquals($expected, $result);
    }

    public function testResolveNotFound()
    {
        // Teste un cas où la résolution ne trouve pas de correspondance
        $path = '/var/www/not_found.tpl';

        $result = PigeonHole::resolve($path);

        // Vérifie que le résultat est faux car aucune correspondance n'a été trouvée
        $this->assertFalse($result);
    }
	
    public function testGeneratePaths()
    {
        PigeonHole::setGlobalPath('root', '/var/www');
        PigeonHole::setGlobalPath('theme', '/var/themes/child_theme');

        PigeonHole::map('page', 'src_path', '%root%/templates/[a:name].tpl');

        $type = 'page';
        $ressourceParams = ['name' => 'youpi'];
        $pathType = 'src_path';

        $result = PigeonHole::generatePaths($type, $ressourceParams, $pathType);

        // Vérifie que le résultat est correct
        $expected = ['/var/www/templates/youpi.tpl'];
        $this->assertEquals($expected, $result);
    }
	
    public function testGeneratePathsMultiples()
    {
		PigeonHole::setGlobalPath('root', '/var/www');
		PigeonHole::setGlobalPath('theme', ['/var/themes/extendable_theme', '/var/themes/child_theme']);
		PigeonHole::setGlobalPath('www', 'http://mydomain.com');

		PigeonHole::map('page', 'theme_path', '%theme%/[a:name].tpl');
		PigeonHole::map('page', 'src_path', '%root%/templates/[a:name].tpl');
		PigeonHole::map('page', 'cache_path', '%root%/cache/[a:name].php');
		PigeonHole::map('page', 'url', '%www%/[a:name]');

        $type = 'page';
        $ressourceParams = ['name' => 'youpi'];

        $result = PigeonHole::generatePaths($type, $ressourceParams);

        // Vérifie que le résultat est correct
        $expected = [
		  'theme_path' => ['/var/themes/extendable_theme/youpi.tpl', '/var/themes/child_theme/youpi.tpl'],
		  'src_path' => ['/var/www/templates/youpi.tpl'],
		  'cache_path' => ['/var/www/cache/youpi.php'],
		  'url' => ['http://mydomain.com/youpi'],
		];
        $this->assertEquals($expected, $result);
    }
    
    protected function initTransformer()
    {
		PigeonHole::setGlobalPath('www', 'http://mydomain.com');
		
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
	}
    
    public function testResolveWithTranformer() {
		$this->initTransformer();
		
        // Configurez les données de test pour la résolution
        $url = 'http://mydomain.com/valid/exists/edit';
        $expectedResult = [
			'type' => 'article',
			'params' =>[
					'id' => 3,
					'category_id' => 1,
				],
			'path_type' => 'edit',
		];

        // Appelez la fonction resolve avec l'URL
        $result = PigeonHole::resolve($url);
        
        // Vérifiez si le résultat correspond à ce qui est attendu
        $this->assertEquals($expectedResult, $result);
    }

    public function testGeneratePathsWithTranformer() {
		$this->initTransformer();
		
        // Configurez les données de test pour la génération de chemins
        $routeData = [
            'id' => 3,
            'category_id' => 1,
        ];
        $expectedPaths = [
            'view' => ['http://mydomain.com/valid/exists/view'],
            'edit' => ['http://mydomain.com/valid/exists/edit'],
            'remove' => ['http://mydomain.com/valid/exists/remove'],
        ];

        // Appelez la fonction generatePaths avec les données de route
        $result = PigeonHole::generatePaths('article', $routeData);

        // Vérifiez si le résultat correspond à ce qui est attendu
        $this->assertEquals($expectedPaths, $result);
    }
    
    // EXCEPTIONS :
    // ============
    
    
    // See : https://github.com/sebastianbergmann/phpunit/issues/5062
	protected function assertThrowableMessage(
		string $message,
		$callback,
		$args
	): void
	{
		$testDONE = false;
		try {
			$test = call_user_func_array($callback, $args);
		} catch (\ErrorException $e) {
			$this->assertEquals($message, $e->getMessage());
			$testDONE = true;
		}
		if(!$testDONE)
			$this->assertTrue(false, 'An Error must be throw.');	// SIC !
}
    
    
    public function testSetSingletonException()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Singleton expected to be an object');

        // Appel à setSingleton avec un non-objet
        PigeonHole::setSingleton('example', 'non-object');
    }

    public function testSetSingletonAlreadyStoredException()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Singleton example alerady stored');

        // Appel à setSingleton pour écraser un singleton existant
        PigeonHole::setSingleton('example', new \stdClass());
        PigeonHole::setSingleton('example', new \stdClass());
    }

    public function testSetGlobalPathInvalidTypeException()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Global Path example must be a string or an array of strings.');

        // Appel à setGlobalPath avec un type de chemin invalide
        PigeonHole::setGlobalPath('example', 123);
    }

    public function testMapInvalidTransformerException()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mapping Error : transformer parameter must be callable.');

        // Appel à map avec un transformateur non-appelable
        PigeonHole::map('example', 'path_type', '%pattern%', 'non-callable');
    }

    public function testGeneratePathsInvalidTransformerReturnTypeException()
    {
        PigeonHole::map('example', 'path_type', '%pattern%', function () {
            return 'invalid';
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Transformer for ressource type example and path type path_type must return an array or false.');

        // Appel à generatePaths avec un transformateur renvoyant un type de retour invalide
        PigeonHole::generatePaths('example', [], 'path_type');
    }
    
    public function testGeneratePathsNoPatternException()
    {
        $this->assertThrowableMessage('Warning : Ressource type example has no pattern.', ['\PigeonHole\PigeonHole', 'generatePaths'], ['example', [], 'path_type']);

        // Appel à generatePaths pour un type de ressource sans modèle de chemin
        //PigeonHole::generatePaths('example', [], 'path_type');
    }

}

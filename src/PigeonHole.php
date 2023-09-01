<?php
namespace PigeonHole;

class PigeonHole
{
	static protected $singletons = [];
	protected $paths = [];
	protected $RessourceInfoFactories = [];
	protected $enabledVandors = '*';
	
	static function getInstance()
	{
		if(!isset(static::$singletons['self']))
			static::$singletons['self'] = new static();
		return static::$singletons['self'];
	}
	
	static function __callStatic($method, $arguments)
	{
		$self = static::getInstance();
		return call_user_func_array([$self, $method], $arguments);
	}
	
	function getSingleton(string $singletonType)
	{
		return static::singletons[$singletonType] ?? false;
	}
	
	function hasSingleton(string $singletonType)
	{
		return isset(static::singletons[$singletonType]);
	}
	
	function withSingleton(string $singletonType, $singleton)
	{
		if(!is_object($singleton))
			exit('ERROR. singleton expected to be an object('.gettype($singleton).' given).'); // TODO
		
		if(isset(static::singletons[$singletonType]))
			echo 'ERROR ? '.$singletonType.' alerady stored'; // TODO
		else
			static::singletons[$singletonType] = $singleton;
		return $this;
	}
	
	function getPaths(string $pathType)
	{
		if(!isset($this->paths[$pathType]))
			return false;
		$result = [];
		foreach($this->paths[$pathType] as $p)
		{
			if(strpos($p, '%subfolder%') !== false) {	// associative if %subfolder%.
				$p = explode('%subfolder%', $path);
				foreach(scandir($p[0]) as $f)
					if(!in_array($f, ['.', '..']))
						if(isset($p[1]))
							$result[$f] = $p[0].$f.$p[1];	// TODO peut être que $p[1] n'est pas une bonne idée... à voir
						else
							$result[$f] = $p[0].$f;
			} else
				$result[] = $p;
		}
		return $result;	// soit ['/mon/theme1', 'mon/theme2'] ou ['Acme' => '/lib/Acme/src', 'OtherVendor' => '/other/vendors/OtherVendor/src']
	}
	
	function withPaths(string $pathType, $paths)	// string OU array of strings ! ex theme qui étend un autre. multiple vendors sources => last pushed = last searched
	{
		if(is_string($paths))
			$paths = [$paths];
		$this->paths[$pathType] = [];	// reset
		foreach($paths as $p)
			$this->appendPath($pathType, $p);
		return $this;
	}

	function prependPath(string $pathType, string $path)
	{
		return $this->appendPath($pathType, $path, true);
	}
		
	function appendPath(string $pathType, string $path, $prepend = false)	// TODO real_path
	{
		if(substr($path, -1, 1) == '/')
			$path = substr($path, 0, -1);
		
		if(!$prepend)
			$this->paths[$pathType][] = $path;
		else
			array_unshift($this->paths[$pathType], $path);
		return $this;
	}
	
	function registerRessourceType(string $ressourceType, RessourceInfoFactory $infoFactory)
	{
		
	}
	
}

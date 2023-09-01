<?php
namespace PigeonHole;

class RessourceInfoFactory
{
	protected $relativePatterns = [];
	
	function __construct()	// ex : ['vendor' => 'template/%selector%.tpl', 'theme' => '%selector%.tpl']
	{
		$args = func_get_args();
		foreach($args as $pathType => $relativePattern)
			$this->relativePatterns[(string) $pathType] = (string) $relativePattern;
	}
	
	public function getRelativePattern(string $pathType)
	{
		if(isset($this->relativePatterns[$pathType]))
			return $this->relativePatterns[$pathType];
		return false;	// gracefully = no need a hasRelativePattern method
	}
	
	protected function externalRessourceExsits($url)
	{
		$headers = @get_headers($url);
		return ($headers and strpos($headers[0], '200 OK') !== false) ? true : false;
	}
	
	function __invoke(string $selector, $params = [])
	{
		$relativePatterns = $this->relativePatterns;
		foreach($relativePatterns as  $pathType => &$relativePattern) {
			$relativePattern = str_replace('%selector%', $selector, $relativePattern);
			foreach($params as $k => $v)
				$relativePattern = str_replace('{'.$k.'}', $v, $relativePattern);
		}
		
		$infos = [];
		foreach(array_keys($relativePattern) as $pathType) {
			if($pList = PigeonHole::getPaths[$pathType])
			foreach($pList as $pSource) {
				$fullPath = $pSource.'/'.$relativePatterns[$pathType];
				if(substr($pathType, 0, 1) == '@') {
					// external ressource exist? TODO add a local callback ?
					$infos[$pathType][$fullPath] = $this->externalRessourceExsits($fullPath);
				}
				else
					$infos[$pathType][$fullPath] = file_exists($fullPath);	// (file or directory)
			}
		}
		
		return $infos;
	}
}

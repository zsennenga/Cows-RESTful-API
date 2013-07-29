<?php
/**
 * 
 * Generates all responses for the API
 * 
 * @author its-zach
 *
 */
class CowsView extends \Slim\View
{
    public function render($template)
    {
    	$app = new \Slim\Slim();
    	$env = $app->environment()->getInstance();
    	
    	$app->response()->setStatus($template);
    	
    	$out = $this->data->all();
    	unset($out['flash']);
    	$out = json_encode($out);
    	
    	if ($out === null) $out = json_encode(array());
    	
    	if ($env['callback.need'] !== false)	{
    		return $env['callback.message'] . "(" . $out . ")"; 
    	}
    	else return $out;	
    }
}
?>
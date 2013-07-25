<?php

class CowsView extends \Slim\View
{
    public function render($template)
    {
    	$app = new \Slim\Slim();
    	$env = $app->environment()->getInstance();
    	
    	$app->response()->setStatus($template);
    	
    	if ($env['callback.need'] !== false)	{
    		return $env['callback.message'] . "(" . json_encode($this->data->all()) . ")"; 
    	}
    	else return json_encode($this->data->all());	
    }
}
?>
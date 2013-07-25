<?php

class CowsView extends \Slim\View
{
    public function render($template)
    {
    	$app = $this->app;
    	$env = $app->environment()->getInstance();
    	
    	$app->setStatusCode($template);
    	
    	if ($env['callback.need'] !== false)	{
    		echo $env['callback.message'];
    		return $env['callback.message'] . "(" . json_encode($this->data) . ")"; 
    	}
    	else return json_encode($this->data);	
    }
}
?>
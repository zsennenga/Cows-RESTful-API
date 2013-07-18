<?php
/**
 * Step 1: Require the Slim Framework
 *
 * If you are not using Composer, you need to require the
 * Slim Framework and register its PSR-0 autoloader.
 *
 * If you are using Composer, you can skip this step.
 */
require 'Slim/Slim.php';

\Slim\Slim::registerAutoloader();

/**
 * Step 2: Instantiate a Slim application
 *
 * This example instantiates a Slim application using
 * its default settings. However, you will usually configure
 * your Slim application now by passing an associative array
 * of setting names and values into the application constructor.
 */
$app = new \Slim\Slim();

/**
 * Step 3: Define the Slim application routes
 *
 * Here we define several Slim application routes that respond
 * to appropriate HTTP request methods. In this example, the second
 * argument for `Slim::get`, `Slim::post`, `Slim::put`, `Slim::patch`, and `Slim::delete`
 * is an anonymous function.
 */

$app->map('/session', function ()	{
	$method = $app->request()->getMethod();
	if ($method == 'DELETE')	{
		
	}
	else if ($method == 'POST')	{
		
	}
	else	{
		$app->response()->stats(501);
	}
})->via('POST','DELETE');

$app->map('/event', function ()	{
	$method = $app->request()->getMethod();
	if ($method == 'GET')	{
	
	}
	else if ($method == 'POST')	{
	
	}
	else	{
		$app->response()->stats(501);
	}
})->via('GET','POST');

$app->map('/event/:id', function($id)	{
	$method = $app->request()->getMethod();
	if ($method == 'GET')	{
	
	}
	else if ($method == 'DELETE')	{
	
	}
	else	{
		$app->response()->stats(501);
	}
})->via('GET','DELETE')->conditions(array('id' => '[0-9]'));

/**
 * Step 4: Run the Slim application
 *
 * This method should be called last. This executes the Slim application
 * and returns the HTTP response to the HTTP client.
 */
$app->run();

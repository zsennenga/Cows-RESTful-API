<?php
/**
 * Step 1: Require the Slim Framework
 *
 * If you are not using Composer, you need to require the
 * Slim Framework and register its PSR-0 autoloader.
 *
 * If you are using Composer, you can skip this step.
 */
if (!file_exists('includes/Utility.php'))
require_once 'Slim/Slim.php';
require_once 'includes/Utility.php';
require_once 'includes/CowsRSS.php';
require_once 'includes/eventSequence.php';
require_once 'includes/SessionWrapper.php';
require_once 'includes/CurlWrapper.php';

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

set_exception_handler('error_handler');

/**
 * Step 3: Define the Slim application routes
 *
 * Here we define several Slim application routes that respond
 * to appropriate HTTP request methods. In this example, the second
 * argument for `Slim::get`, `Slim::post`, `Slim::put`, `Slim::patch`, and `Slim::delete`
 * is an anonymous function.
 */
$app->get('/', function()	{
	$app = \Slim\Slim::getInstance();
	//TODO list all services/access methods
});
//DONE
$app->map('/session/', function ()	{
	$app = \Slim\Slim::getInstance();
	$method = $app->request()->getMethod();
	if ($method == 'DELETE')	{
		$sess = $app->request()->params('sessionKey');
		if ($sess === null)	throwError(ERROR_PARAMS,"This action requires a sessionKey");
		$sess = new SessionWrapper($sess);
		$sess->destroySession();
	}
	else if ($method == 'POST')	{
		$curl = new CurlWrapper();
		
		$tgc = $app->request()->params('tgc');
		if ($tgc === null)	{
			$app->halt(400,generateError(ERROR_PARAMETERS, "You must include the tgc parameter to create a session"));
		}
		else if (!$curl->validateTGC())	{
			$app->halt(400,generateError(ERROR_CAS, "Invalid TGC"));
		}
		
		$siteId = $app->request()->params('siteid');
		if ($siteId === null)	{
			$app->halt(400,generateError(ERROR_PARAMETERS, "You must include the siteID parameter to create a session"));
		}
		else if (!$curl->validateSiteID())	{
			$app->halt(400,generateError(ERROR_PARAMETERS, "Invalid site ID"));
		}
		
		$sess = SessionWrapper::createSession($tgc, $siteId);
		$app->response()->setStatus(201);
		$app->response()->setBody(json_encode(array('sessionKey' => $sess->getSessionKey())));
	}
	else	{
		$app->response()->setStatus(501);
	}
})->via('POST','DELETE');

//TODO POST
$app->map('/event/', function ()	{
	
	$app = \Slim\Slim::getInstance();
	$method = $app->request()->getMethod();
	
	$ajaxResponse = false;
	$timeBounded = false;
	
	if ($method == 'GET')	{
		
		//Handle API specific GET parameters
		
		//Store and remove the ajax callback if necessary
		if (isset($_GET['callback']))	{
			$callback = $_GET['callback'];
			$ajaxResponse = true;
			unset($_GET['callback']);
		}
		
		if (isset($_GET['timeStart']) && isset($_GET['timeEnd']))	{
			$timeBounded = true;
			$timeStart = $_GET['timeStart'];
			unset($_GET['timeStart']);
			$timeEnd = $_GET['timeEnd'];
			unset($_GET['timeEnd']);
			if (strtotime($timeStart) === false || strtotime($timeEnd) === false)	{
				$app->halt(400, generateError(ERROR_PARAMETERS,"Invalid time range"));
			}
		}
		else if (isset($_GET['timeStart']) || isset($_GET['timeEnd']))	{
			$app->halt(400, json_encode(
					generateError(ERROR_PARAMETERS,"Time ranges must include both bounds")));
		}
		
		//Build RSS object
		try	{
			//Feed cows the whole batch of $_GET parameters
			$cows = new cowsRss('http://cows.ucdavis.edu/ITS/event/atom?' . http_build_query($_GET));
		}
		catch (Exception $e) {
			$app->halt(500,json_encode(generateError(ERROR_RSS, $e->getMessage() )));
		}
		if ($timeBounded)	{
			$sequence = eventSequence::createSequenceFromArrayTimeBounded(
					$cows->getData(time()),strtotime($timeStart, time()),strtotime($timeEnd, time()));
		}
		else	{
			$sequence = new eventSequence($cows->getData(time()));
		}
		$json = json_encode($sequence->toArray());
		if ($ajaxResponse)	{
			$app->response()->setBody($callback . "($json)");
		}
		else $app->response()->setBody($json);
		$app->response()->setStatus(200);
	}
	
	else if ($method == 'POST')	{
	
	}
	
	else	{
		$app->response()->setStatus(501);
	}
})->via('GET','POST');

//TODO GET DELETE
$app->map('/event/:id', function($id)	{
	$app = \Slim\Slim::getInstance();
	$method = $app->request()->getMethod();
	if ($method == 'GET')	{
	
	}
	else if ($method == 'DELETE')	{
	
	}
	else	{
		$app->response()->setStatus(501);
	}
})->via('GET','DELETE')->conditions(array('id' => '[0-9]'));

/**
 * Step 4: Run the Slim application
 *
 * This method should be called last. This executes the Slim application
 * and returns the HTTP response to the HTTP client.
 */
$app->run();

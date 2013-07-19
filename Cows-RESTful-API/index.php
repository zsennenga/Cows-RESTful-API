<?php
/**
 * Step 1: Require the Slim Framework
 *
 * If you are not using Composer, you need to require the
 * Slim Framework and register its PSR-0 autoloader.
 *
 * If you are using Composer, you can skip this step.
 */
if (!file_exists('includes/Config.php'))	{
	echo "Please follow the Install instructions before running this application";
	exit(0);
}	
require_once 'Slim/Slim.php';
require_once 'includes/config.php';
require_once 'includes/Utility.php';
require_once 'includes/SessionWrapper.php';
require_once 'includes/CurlWrapper.php';
require_once 'includes/CowsRSS.php';
require_once 'includes/eventSequence.php';

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

set_exception_handler('error_handler');

$app->get('/', function()	{
	$app = \Slim\Slim::getInstance();
	if ($app->request()->get("format") == "json") {
		$session = array(
				"POST" => array(
						"requiresAuth" => false,
						"requiredParameters" => "tgc,siteid",
						"description" => "Generate a session key which will allow you to use COWS services which require authentication"
				),
		);
		$sesskey = array(
				"DELETE" => array(
						"requiresAuth" => true,
						"requiredParameters" => "",
						"description" => "Destroys a session, and makes a good effort to log you out of COWS and CAS"
				)
		);
		$event = array(
				"GET" => array(
						"requiresAuth" => true,
						"requiredParameters" => "siteid OR sessionKey",
						"description" => "Gets all events that meet the parameters given as GET parameters. Only requires auth if anonymous mode is off on the COWS site"
				),
				"POST" => array(
						"requiresAuth" => true,
						"requiredParameters" => "sessionKey, All Event Parameters",
						"description" => "Creates an event with the given Parameters"
				)
		);
		$eventid = array(
				"GET" => array(
						"requiresAuth" => true,
						"requiredParameters" => "siteid OR sessionKey",
						"description" => "Gets the information with the Event with the specified ID"
				),
				"DELETE" => array(
						"requiresAuth" => true,
						"requiredParameters" => "GET sessionKey",
						"description" => "Deletes the event with the Specified ID"
				)
		);
		$methods = array("/session" => $session, "/session/:key" => $sesskey, "/event" => $event, "/event/:id" => $eventid);
		$app->response()->setBody(json_encode($methods));
	}
	else $app->response()->setBody(file_get_contents("includes/methods.html"));
});

$app->post('/session/', function ()	{
	$app = \Slim\Slim::getInstance();
	$curl = new CurlWrapper();
	
	$tgc = $app->request()->post('tgc');
	if ($tgc === null)	{
		throwError(ERROR_PARAMETERS, "You must include the tgc parameter to create a session",400);
	}
	else if (!$curl->validateTGC())	{
		throwError(ERROR_CAS, "Invalid TGC",400);
	}
	
	$siteId = $app->request()->post('siteid');
	if ($siteId === null)	{
		throwError(ERROR_PARAMETERS, "You must include the siteID parameter to create a session",400);
	}
	else if (!$curl->validateSiteID())	{
		throwError(ERROR_PARAMETERS, "Invalid site ID",400);
	}
	
	$sess = SessionWrapper::createSession($tgc, $siteId);
	$app->response()->setStatus(201);
	$app->response()->setBody(json_encode(array('sessionKey' => $sess->getSessionKey())));

});

$app->delete('/session/:key/', function($key) {
	$sess = new SessionWrapper($key);
	$sess->destroySession();
	$app->response()->status(200);
});

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
				throwError(ERROR_PARAMETERS, "Invalid time range", 400);
			}
		}
		else if (isset($_GET['timeStart']) || isset($_GET['timeEnd']))	{
				throwError(ERROR_PARAMETERS, "Time ranges must include both bounds", 400);
		}
		
		if (isset($_GET['siteid']))	{
			$curl = new CurlHandle("");
			if (!$curl->validateSiteID($_GET['siteid']))	{
				throwError(ERROR_PARAMETERS, "SiteID invalid", 400);
			}
		}
		else if (isset($_GET['sessionKey']))	{
			$sess = new SessionWrapper($_GET['sessionKey']);
		}
		else	{
			throwError(ERROR_PARAMETERS, "Must set sessionKey or SiteID",400);
		}
		
		//Build RSS object
		//Feed cows the whole batch of $_GET parameters
		if (!isset($sess))	{
			$cows = new cowsRss();
			$cows->setFeedUrl(COWS_BASE_PATH . $siteId . COWS_RSS_PATH . '?' . http_build_query($_GET));
		}
		else {
			$curl = new CurlHandle($sess->getSessionKey());
			$cows = new CowsRss();
			$cows->setFeedData($curl->getFeed($sess->getSiteId(),$_GET));
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
		$params = $app->request()->post();
		if (!isset($params['sessionKey'])) throwError(ERROR_PARAMETERS, "SessionKey must be set", 400);
		$sess = new SessionHandler($params['sessionKey']);
		unset($params['sessionKey']);
		$curl = new CurlWrapper($sess->getCookieFile());
		$curl->cowsLogin($sess->getTGC(), $sess->getSiteId());
		$curl->createEvent($sess->getSiteId(), $params);
	}
	
	else	{
		$app->response()->setStatus(501);
	}
})->via('GET','POST');

//TODO GET
$app->map('/event/:id/', function($id)	{
	$app = \Slim\Slim::getInstance();
	$method = $app->request()->getMethod();
	if ($method == 'GET')	{
		if ($app->request()->get("sessionKey") !== false)	{
			$sess = new SessionWrapper($app->request()->get("sessionKey"));
			$curl = new CurlWrapper($sess->getCookieFile());
			$siteId = $sess->getSiteId();
		}
		else	{
			if ($app->request()->get("siteId") === false) throwError(ERROR_PARAMETERS, "SiteID must be set",400);
			$curl = new CurlWrapper();
			$siteId = $app->request()->get("siteId");
		}
		$curl->getSingleEvent($siteId, $id);
	}
	else if ($method == 'DELETE')	{
		if (!$app->request()->get('sessionKey') === false)	{
			throwError(ERROR_PARAMETERS, "You must provite a sessionKey to access this interface",400);
		}
		$sess = new SessionWrapper($app->request()->get('sessionKey'));
		$curl = new CurlWrapper($sess->getCookieFile());
		$curl->deleteEvent($id);
		$app->response()->setStatus(200);
	}
	else	{
		$app->response()->setStatus(501);
	}
})->via('GET','DELETE')->conditions(array('id' => '[0-9]'));


$app->run();

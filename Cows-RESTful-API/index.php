<?php

if (!file_exists('includes/Config.php'))	{
	echo "Please follow the Install instructions before running this application";
	exit(0);
}	
require_once 'Slim/Slim.php';
require_once 'includes/config.php';
require_once 'includes/Utility.php';
require_once 'includes/DocumentWrapper.php';
require_once 'includes/SessionWrapper.php';
require_once 'includes/CurlWrapper.php';
require_once 'includes/CowsRSS.php';
require_once 'includes/eventSequence.php';

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

set_exception_handler('error_handler');

$app->response()->body(json_encode(array()));
$app->contentType('application/json');

$app->get('/', function()	{
	$app = \Slim\Slim::getInstance();
	if ($app->request()->get("format") == "json") {
		$session = array(
				"POST" => array(
						"requiresAuth" => false,
						"requiredParameters" => "tgc",
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
						"requiredParameters" => "sessionKey (if auth is required)",
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
						"requiredParameters" => "sessionKey (If auth is required)",
						"description" => "Gets the information with the Event with the specified ID"
				),
				"DELETE" => array(
						"requiresAuth" => true,
						"requiredParameters" => "sessionKey (Via GET or POST due to the limitations of DELETE)",
						"description" => "Deletes the event with the Specified ID"
				)
		);
		$methods = array("/session/:siteId" => $session, "/session/:key" => $sesskey, "/event/:siteId" => $event, "/event/:siteId/:id" => $eventid);
		$app->response()->setBody(json_encode($methods));
	}
	else {
		$app->contentType('text/html');
		$app->response()->setBody(file_get_contents("includes/methods.html"));
	}
	
});

$app->post('/session/:siteId/', function ($siteId)	{
	$app = \Slim\Slim::getInstance();
	$curl = CurlWrapper::CreateWithoutCookie();
	
	$tgc = $app->request()->params('tgc');
	if ($tgc === null)	{
		throwError(ERROR_PARAMETERS, "You must include the tgc parameter to create a session",400);
	}
	else if ($curl->validateTGC($tgc) !== true)	{
		throwError(ERROR_CAS, "Invalid TGC" . $curl->validateTGC($tgc),400);
	}
	
	if (!$curl->validateSiteID($siteId))	{
		throwError(ERROR_PARAMETERS, "Invalid site ID",400);
	}
	$sess = SessionWrapper::createSession($tgc, $siteId);
	$app->response()->setStatus(201);
	$app->response()->setBody(json_encode(array('sessionKey' => $sess->getSessionKey())));

});

$app->delete('/session/:key/', function($key) {
	$app = \Slim\Slim::getInstance();
	$sess = new SessionWrapper($key);
	$sess->destroySession();
	$app->response()->status(200);
});

$app->map('/event/:siteId/', function ($siteId)	{
	
	$app = \Slim\Slim::getInstance();
	$method = $app->request()->getMethod();
	
	$ajaxResponse = false;
	$timeBounded = false;
	
	if ($method == 'GET')	{
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
		$curl = CurlWrapper::CreateWithoutCookie();
		if (!$curl->validateSiteID($siteId))	{
			throwError(ERROR_PARAMETERS, "SiteID invalid", 400);
		}
		else if (isset($_GET['sessionKey']))	{
			$sess = new SessionWrapper($_GET['sessionKey']);
		}
		unset($curl);
		
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
		$curl->cowsLogin($sess->getTGC(), $siteId);
		$curl->createEvent($siteId, $params);
	}

	else	{
		$app->response()->setStatus(501);
	}
})->via('GET','POST');

$app->map('/event/:siteId/:eventId/', function($siteId,$eventId)	{
	
	$app = \Slim\Slim::getInstance();
	$method = $app->request()->getMethod();
	
	if ($method == 'GET')	{
		if ($app->request()->get("sessionKey") !== null)	{
			$sess = new SessionWrapper($app->request()->get("sessionKey"));
			$curl = new CurlWrapper($sess->getCookieFile());
		}
		else	{
			$curl = CurlWrapper::CreateWithoutCookie();
		}
		$app->response()->setBody($curl->getSingleEvent($siteId, $eventId));
	}
	else if ($method == 'DELETE')	{
		if (!$app->request()->get('sessionKey') === null)	{
			throwError(ERROR_PARAMETERS, "You must provite a sessionKey to access this interface",400);
		}
		$sess = new SessionWrapper($app->request()->get('sessionKey'));
		$curl = new CurlWrapper($sess->getCookieFile());
		$curl->deleteEvent($siteId, $eventId,$sess->getTGC());
		$app->response()->setStatus(200);
	}
	else	{
		$app->response()->setStatus(501);
	}
})->via('GET','DELETE')->conditions(array('id' => '[0-9]'));

$app->get('/error/', function()	{
	$app = \Slim\Slim::getInstance();
	$out = array(
			"-1" => "ERROR_GENERIC",
			"-2" => "ERROR_CAS",
			"-3" => "ERROR_EVENT",
			"-4" => "ERROR_CURL",
			"-5" => "ERROR_RSS",
			"-6" => "ERROR_PARAMETERS",
			"-7" => "ERROR_DB",
			"-8" => "ERROR_COWS"
	);
	$app->response()->setBody(json_encode($out));
});

$app->notFound(function ()	{
	$app = \Slim\Slim::getInstance();
	throwError(ERROR_GENERIC, "Invalid Route. Please refer to the documentation for a list of valid routes.", 404);
});

$app->run();

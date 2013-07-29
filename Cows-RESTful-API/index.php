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
require_once 'includes/SimpleMiddleware.php';

set_exception_handler('error_handler');

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

$app->add(new CowsMiddleware());
$app->view(new CowsView());

$app->contentType('application/json');

$env = $app->environment()->getInstance();

/**
 * Dispenses documentation about the api
 */
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
		$app->render(200,$methods);
	}
	else {
		$app->contentType('text/html');
		$app->response()->setBody(file_get_contents("includes/methods.html"));
	}
	
});

/**
 * Creates a session on COWS
 */
$app->post('/session/:siteId/', 'sessPost', function ($siteId) {
	$app = \Slim\Slim::getInstance();
	$env = $app->environment()->getInstance();
	
	$tgc = $app->request()->params('tgc');
	
	$env['sess.instance']->createSession($tgc,$siteId);
	
	$app->render(201,array());
});
/**
 * Destroys a session on COWS
 */
$app->delete('/session/', function() {
	$app = \Slim\Slim::getInstance();
	$env = $app->environment()->getInstance();
	$env['sess.instance']->destroySession();
	$app->render(200,array());
});
/**
 * Gets event info and creates events
 */
$app->map('/event/:siteId/', function ($siteId){
	
	$app = \Slim\Slim::getInstance();
	$env = $app->environment()->getInstance();
	$method = $app->request()->getMethod();

	$timeBounded = false;
	
	if ($method == 'GET')	{
		$params = $app->request()->get();
		unset($params['signature']);
		if (isset($params['timeStart']) && isset($params['timeEnd']))	{
			$timeBounded = true;
			$timeStart = $params['timeStart'];
			unset($params['timeStart']);
			$timeEnd = $params['timeEnd'];
			unset($params['timeEnd']);
			if (strtotime($timeStart) === false || strtotime($timeEnd) === false)	{
				throwError(ERROR_PARAMETERS, "Invalid time range", 400);
			}
			else if (strtotime($timeStart) > $strtotime($timeEnd))	{
				throwError(ERROR_PARAMETERS, "Start time must be before End time", 400);
			}
		}
		else if (isset($params['timeStart']) || isset($params['timeEnd']))	{
				throwError(ERROR_PARAMETERS, "Time ranges must include both bounds", 400);
		}
		
		//Build RSS object
		//Feed cows the whole batch of $params parameters
		if ($env['sess.instance']!= null)	{
			$cows = new cowsRss();
			$cows->setFeedUrl(COWS_BASE_PATH . $siteId . COWS_RSS_PATH . '?' . http_build_query($params));
		}
		else {
			$curl = new CurlHandle($env['sess.instance']->getCookieFile());
			$cows = new CowsRss();
			$cows->setFeedData($curl->getFeed($env['sess.instance']->getSiteId(),$params));
		}
		if ($timeBounded)	{
			$sequence = eventSequence::createSequenceFromArrayTimeBounded(
					$cows->getData(time()),strtotime($timeStart, time()),strtotime($timeEnd, time()));
		}
		else	{
			$sequence = new eventSequence($cows->getData(time()));
		}
		$app->render(200,$sequence->toArray());
	}
	
	else if ($method == 'POST')	{
		$params = $app->request()->params();
		unset($params['signature']);
		unset($params['publicKey']);
		$curl = new CurlWrapper($env['sess.instance']->getCookieFile());
		$out = $curl->createEvent($siteId, $params);
		$app->render(201,$out);
	}

	else	{
		$app->response()->setStatus(501);
	}
})->via('GET','POST');
/**
 * Gets single event info and deletes events
 */
$app->map('/event/:siteId/:eventId/', function($siteId,$eventId)	{
	
	$app = \Slim\Slim::getInstance();
	$env = $app->environment()->getInstance();
	$method = $app->request()->getMethod();
	
	if ($method == 'GET')	{
		if ($app->request()->get("sessionKey") !== null)	{
			$curl = new CurlWrapper($env['sess.instance']->getCookieFile());
		}
		else	{
			$curl = CurlWrapper::CreateWithoutCookie();
		}
		$app->render(200,$curl->getSingleEvent($siteId, $eventId));
	}
	else if ($method == 'DELETE')	{
		if (!$app->request()->get('sessionKey') === null)	{
			throwError(ERROR_PARAMETERS, "You must provite a sessionKey to access this interface",400);
		}
		$curl = new CurlWrapper($env['sess.instance']->getCookieFile());
		$curl->deleteEvent($siteId, $eventId);
		$app->render(200,array());
	}
	else	{
		$app->response()->setStatus(501);
	}
})->via('GET','DELETE')->conditions(array('id' => '[0-9]'));
/**
 * Gets error documentation
 */
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
	$app->render(200,$out);
});
/**
 * Handles 404 Errors
 */
$app->notFound(function ()	{
	$app = \Slim\Slim::getInstance();
	throwError(ERROR_GENERIC, "Invalid Route. Please refer to the documentation for a list of valid routes.", 404);
});

$app->run();

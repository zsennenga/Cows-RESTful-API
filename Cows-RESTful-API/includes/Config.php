<?php
define("DB_HOST", "localhost");
define("DB_NAME", "CowsRESTDB");
define("DB_TABLE", "CowsTable");
define("DB_USER", "dbuser");
define("DB_PASS", "dbpass");
// DO NOT EDIT BELOW THIS LINE
define("COWS_LOGIN_PATH","/Account/LogOn");
define("COWS_EVENT_PATH","/Event/Create");
define("COWS_LOGOUT_PATH","/Account/LogOff");
define("CAS_PROXY_PATH","https://cas.ucdavis.edu:8443/cas/proxy");
define("CAS_LOGOUT_PATH","");
define("COWS_BASE_PATH","http://cows.ucdavis.edu/");
define("ERROR_GENERIC", "-1");
define("ERROR_CAS", "-2");
define("ERROR_EVENT", "-3");
define("ERROR_CURL", "-4");
define("ERROR_RSS", "-5");
define("ERROR_PARAMETERS","-6");
define("ERROR_DB","-7");
define("ERROR_COWS","-8");
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
				"description" => "Gets all events that meet the parameters given as GET parameters. Only requires auth if anonymous
					mode is off on the COWS site"
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
?>
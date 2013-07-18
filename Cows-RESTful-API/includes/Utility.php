<?php
define("COWS_LOGIN_PATH","/Account/LogOn");
define("COWS_EVENT_PATH","/Event/Create");
define("COWS_LOGOUT_PATH","/Account/LogOff");
define("CAS_PROXY_PATH","https://cas.ucdavis.edu:8443/cas/proxy");
define("COWS_BASE_PATH","http://cows.ucdavis.edu/");
define("ERROR_GENERIC", "-1");
define("ERROR_CAS", "-2");
define("ERROR_EVENT", "-3");
define("ERROR_CURL", "-4");
define("ERROR_RSS", "-5");
define("ERROR_PARAMETERS","-6");
define("DB_HOST", "localhost");
define("DB_NAME", "CowsRESTDB");
define("DB_TABLE", "CowsTable");
define("DB_USER", "dbuser");
define("DB_PASS", "dbpass");

/**
 * 
 * Quick function to generate an array from an error code and error message
 * 
 * @param Error Code $code
 * @param Error Message $message
 * @return Error array
 */
function generateError($code, $message)	{
	return array("code" => $code, "message" => $message);
}
?>
<?php
define("DB_HOST", "dbHost");
define("DB_USER", "dbUser");
define("DB_PASS", "dbPass");

// DO NOT EDIT BELOW THIS LINE
define("DB_NAME", "cowsrest");
define("DB_TABLE", "cowsrest");
define("DB_TABLE", "cowsrestlog");
define("COWS_LOGIN_PATH","/Account/LogOn");
define("COWS_EVENT_PATH","/Event/Create");
define("COWS_BASE_EVENT_PATH","/Event");
define("COWS_LOGOUT_PATH","/Account/LogOff");
define("COWS_DELETE_PATH","/event/Delete");
define("COWS_RSS_PATH","/event/atom");
define("CAS_PROXY_PATH","https://cas.ucdavis.edu/cas/proxy");
define("CAS_LOGOUT_PATH","https://cas.ucdavis.edu/cas/logout");
define("COWS_BASE_PATH","http://cows.ucdavis.edu/");
define("ERROR_GENERIC", "-1");
define("ERROR_CAS", "-2");
define("ERROR_EVENT", "-3");
define("ERROR_CURL", "-4");
define("ERROR_RSS", "-5");
define("ERROR_PARAMETERS","-6");
define("ERROR_DB","-7");
define("ERROR_COWS","-8");
?>
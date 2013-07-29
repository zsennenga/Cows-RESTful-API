<?php

require_once 'Utility.php';

function sessPost()	{
	//Setup Variables
	$app = \Slim\Slim::getInstance();
	$env = $app->environment()->getInstance();
	$tgc = $app->request()->params('tgc');
	$curl = CurlWrapper::CreateWithoutCookie();
	
	if ($tgc === null)	{
		throwError(ERROR_PARAMETERS, "You must include the tgc parameter to create a session",400);
	}
	else if ($curl->validateTGC($tgc) !== true)	{
		throwError(ERROR_CAS, "Invalid TGC" . $curl->validateTGC($tgc),400);
	}
}

?>
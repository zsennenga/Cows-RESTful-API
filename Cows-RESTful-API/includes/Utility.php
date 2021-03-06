<?php
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
/**
 * Alternative exception handler
 * @param exception $e
 */
function error_handler($e)	{
	$app = \Slim\Slim::getInstance();
	$app->render(500,generateError(ERROR_GENERIC,$e->getMessage()));
	Log::getInstance()->doLog();
	$app->stop();
}
/**
 * 
 * Kills the request due to an error in some process.
 * 
 * @param Error code $code
 * @param string $message
 */
function throwError($code,$message,$status = null)	{
	if ($status == null) $status = 500;
	$app = \Slim\Slim::getInstance();
	$app->render($status,generateError($code,$message));
	Log::getInstance()->doLog();
	$app->stop();
}
?>
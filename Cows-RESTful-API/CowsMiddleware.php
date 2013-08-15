<?php

require_once 'includes/Utility.php';
/**
 * Handles variable validation and parses necessary variables
 * @author its-zach
 *
 */
class CowsMiddleware extends \Slim\Middleware
{
	public function call()
	{
		$app = $this->app;
		$env = $app->environment()->getInstance();
		$end = false;
		
		//Handle Callback
		$env['callback.need'] = false;
		$env['callback.message'] = "";
		if ($app->request()->params('callback') != null)	{
			$env['callback.need'] = true;
			$env['callback.message'] = $app->request()->params('callback');
		}
		
		$headers = apache_request_headers();
		if(!isset($headers['Authorization'])){
			$app->render(400, generateError(ERROR_PARAMETERS,"No Authorization header in request " . print_r($headers)));
			$end = true;
		}
		else {
			$auth = $headers['Authorization'];
			$auth = explode("|",$auth);
				
			$signature = $auth[2];
			$time = $auth[1];
			$publicKey = $auth[0];
		
			//Check Timestamp
			if ($time < strtotime("-5 Minutes",time()) || $time > strtotime("+5 Minutes",time()))	{
				$app->render(400,generateError(ERROR_PARAMETERS, "Signature has expired."));
				$end = true;
			}
			//Check Signature
			
			if (isset($signature) != null)	{
				if (isset($publicKey) != null)	{
					if (!SessionWrapper::checkKey($signature,$publicKey,$time))	{
						$app->render(400,generateError(ERROR_PARAMETERS, "Invalid signature"));
						$end = true;
					}
			
					$path = $app->request()->getPathInfo();
					if ($path != "/" && $path != "/error" && $path != "/error/")	{
						$stuff = explode("/",$path);
						$siteId = $stuff[2];
						$curl = CurlWrapper::CreateWithoutCookie();
						if (!$curl->validateSiteID($siteId) && !($path == "/session" || $path == "/session/"))	{
							$app->render(400, generateError(ERROR_PARAMETERS, "SiteID invalid"));
							$end = true;
						}
						try 	{
							$env['sess.instance'] = new SessionWrapper($publicKey);
						}
						catch (Exception $e)	{
							$app->render(500,generateError(ERROR_GENERIC, $e->getMessage()));
							$end = true;
						}
					}
				}
				else {
					$app->render(400,generateError(ERROR_PARAMETERS, "Signed requests must include a publicKey"));
					$end = true;
				}
			}
			else	{
				$app->render(400,generateError(ERROR_PARAMETERS, "All requests must be signed."));
				$end = true;
			}
		}
		
		if (!$end) {
			$this->next->call();
		}
	}
}
?>
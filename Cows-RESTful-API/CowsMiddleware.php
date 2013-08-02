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
		
		//Check Timestamp
		if (isset($_REQUEST['time']))	{
			$time = $_REQUEST['time'];
			if ($time < strtotime("-5 Minutes",time()) || $time > strtotime("+5 Minutes",time()))	{
				$app->render(400,generateError(ERROR_PARAMETERS, "Signature has expired."));
				$end = true;
			}
			//Check Signature
			
			$headers = apache_request_headers();
  			if(!isset($headers['Authorization'])){
				throwError(ERROR_PARAMETERS,"No Authorization header in request",400);
  			}
			
  			$auth = $headers['Authorization'];
			$auth = explode("|",$auth);
			
			$signature = $auth[2];
			$time = $auth[1];
			$publicKey = $auth[0];
			
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
							throwError(ERROR_PARAMETERS, "SiteID invalid", 400);
						}
						try 	{
							$env['sess.instance'] = new SessionWrapper($_REQUEST['publicKey']);
						}
						catch (Exception $e)	{
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
		else 	{
			$app->render(400,generateError(ERROR_PARAMETERS, "All requests must be timestamped."));
			$end = true;
		}
		
		if (!$end) {
			$this->next->call();
		}
	}
}
?>
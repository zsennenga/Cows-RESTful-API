<?php

require_once 'includes/Utility.php';

class CowsMiddleware extends \Slim\Middleware
{
	public function call()
	{
		// Get reference to application
		$app = $this->app;
		$env = $app->environment()->getInstance();
		$end = false;

		// Capitalize response body
		
		$env['callback.need'] = false;
		$env['callback.message'] = "";
		if ($app->request()->params('callback') != null)	{
			$env['callback.need'] = true;
			$env['callback.message'] = $app->request()->params('callback');
		}
		
		if (isset($_REQUEST['time']))	{
			$time = $_REQUEST['time'];
			if ($time < strtotime("-15 Minutes",time()) || $time > strtotime("+15 Minutes",time()))	{
				$app->render(400,generateError(ERROR_PARAMETERS, "Signature has expired."));
				$end = true;
			}
			if ($app->request()->params('signature') != null)	{
				if ($app->request()->params('publicKey') != null)	{
					if (!SessionWrapper::checkKey())	{
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
							$env['sess.instance'] = new SessionWrapper($app->request()->params('publicKey'));
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
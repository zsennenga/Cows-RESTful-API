<?php

require_once 'includes/Utility.php';

class CowsMiddleware extends \Slim\Middleware
{
	public function call()
	{
		// Get reference to application
		$app = $this->app;
		$env = $app->environment()->getInstance();

		// Capitalize response body
		
		$env['callback.need'] = false;
		$env['callback.message'] = "";
		if ($app->request()->params('callback') != null)	{
			$env['callback.need'] = true;
			$env['callback.message'] = $app->request()->params('callback');
		}
		
		if ($app->request()->params('signature') != null)	{
			if ($app->request()->params('publicKey') != null)	{
				if (!SessionWrapper::checkKey())	{
					$app->render(400,generateError(ERROR_PARAMETERS, "Invalid signature"));
					$end = true;
				}
				$env['sess.instance'] = new SessionWrapper($app->request()->params('publicKey'));
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
		
		if (!$end) $this->next->call();
	}
}
?>
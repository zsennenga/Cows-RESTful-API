<?php
class CurlWrapper	{
	private $curlHandle;
	private $cookieFile;
	private $response;
	private $loggedIn = false;
	
	/**
	 *
	 * Generates a random filename for the cookie file for cURL
	 *
	 * @return cookieFile name
	 */
	private function genFilename()	{
		$charset = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$randString = '';
		for ($i = 0; $i < 15; $i++) {
			$randString .= $charset[rand(0, strlen($charset)-1)];
		}
		return realpath(dirname(__FILE__)) . "/cookies/cookieFile" . $randString;
	}
	
	public function __construct($cookieFile = null)	{
		$this->curlHandle = curl_init();
		if ($cookieFile != "")	{
			if ($cookieFile == null)	{
				$this->cookieFile = genFilename();
			}
			else $this->cookieFile = $cookieFile;
			curl_setopt($this->curlHandle, CURLOPT_COOKIEJAR, $this->cookieFile);
			curl_setopt($this->curlHandle, CURLOPT_COOKIEFILE, $this->cookieFile);
		}
	
		curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->curlHandle, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($this->curlHandle, CURLOPT_SSL_VERIFYPEER, false);
	}
	
	public function __destruct()	{
		curl_close($this->curlHandle);
	}
	
	/**
	 * 
	 * Executes a GET request with the given parameters.
	 * 
	 * Expects either a keyed array or a string built with http_build_query
	 * 
	 * @param String $url
	 * @param Array or String $parameters
	 */
	private function getWithParameters($url, $parameters = "")  {
		if (is_array($parameters))	{
			$paramters = http_build_query($parameters);
		}
		curl_setopt($this->curlHandle, CURLOPT_HTTPGET, true);
		curl_setopt($this->curlHandle, CURLOPT_URL, $url . "?" . $parameters);
		$out = curl_exec($this->curlHandle);
		if ($out === false) throwError(ERROR_CURL,curl_error($this->curlHandle));
		return $out;
	}
	/**
	 * 
	 * Executes a post request with the given parameters
	 * 
	 * Expects either a keyed array or a string built with http_build_query
	 * 
	 * @param unknown $url
	 * @param unknown $parameters
	 * @return mixed
	 */
	private function postWithParameters($url, $parameters = "")  {
		if (is_array($parameters))	{
			$paramters = http_build_query($parameters);
		}
		curl_setopt($this->curlHandle, CURLOPT_POST, true);
		curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $parameters);
		$out = curl_exec($this->curlHandle);
		if ($out === false) throwError(ERROR_CURL,curl_error($this->curlHandle));
		return $out;
	}
	/**
	 * Gets the last curl error
	 * @return string
	 */
	public function getLastError()	{
		return curl_error($this->curlHandle);
	}
	/**
	 * Gets the cookieFile name
	 * @return string
	 */
	public function getCookieFile()	{
		return $this->cookieFile;
	}
	
	/**
	 * Checks CAS to verify if a TGC can generate a ticket
	 * 
	 * @param String $tgc
	 * @return boolean
	 */
	public function validateTGC($tgc)	{
		$params = array("pgt" => $tgc,
			  "targetService" => test);
		$resp = $this->getWithParameters(CAS_PROXY_PATH, $params);
		if (strpos($resp, 'proxyFailure') !== false)	return false;
		return true;
	}
	/**
	 * Checks if a siteID refers to a valid cows site
	 * @param String $siteID
	 * @return boolean
	 */
	public function validateSiteID($siteID)	{
		$this->getWithParameters(COWS_BASE_PATH . $siteID);
		$last = curl_getinfo($this->curlHandle, CURLINFO_EFFECTIVE_URL);
		if (strpos($last,"aspxerrorpath") !== false)	return false;
		return true;
	}
	/**
	 * Uses a TicketGrantingCookie to generate a ServiceTicket for a given service
	 * 
	 * @param String $tgc
	 * @param String $service
	 * @return String
	 */
	private function getTicket($tgc, $service)	{
		//TODO this
	}
	
	/**
	 * Executes a login to COWS
	 * 
	 * @param String $tgc
	 * @param String $siteID
	 * @return boolean
	 */
	public function cowsLogin($tgc, $siteID)	{
		if ($this->loggedIn) throwError(ERROR_COWS,"Already Logged in");
		
		$returnURL = COWS_BASE_PATH . $siteID . "/";
		$loginURL = COWS_BASE_PATH . $siteID . COWS_LOGIN_PATH;
		
		$service = $loginURL . "?returnUrl=" . $returnURL;
		$ticket = $this->getTicket($tgc,$service);
		
		$params = array("returnUrl" => $returnURL,
				"ticket" => $ticket
		);
		$out = getWithParameters($loginURL,$params);
		$last = curl_getinfo($this->curlHandle, CURLINFO_EFFECTIVE_URL);
		if (strpos($last,"cows.ucdavis.edu") === false)	{
			return false;
		}
		else	{
			$this->loggedIn = true;
			return true;
		}
	}
	/**
	 * Gets the request verification token and other fields
	 * from Cows via scraping the /event/create page
	 */
	public function getCowsFields()	{
		if (!$this->loggedIn) throwError(ERROR_COWS,"Not logged in");
		//TODO
	}
	/**
	 * Executes a logout of cows
	 * 
	 * @return boolean
	 */
	public function cowsLogout($siteID)	{
		if (!$this->loggedIn) throwError(ERROR_COWS,"Can't logout if not logged in");
		$this->getWithParameters(COWS_BASE_PATH . $siteID . COWS_LOGOUT_PATH);
	}
	/**
	 * Executes a logout of CAS
	 * 
	 * @param Ticket Granting Cookie $tgc
	 */
	public function casLogout($tgc)	{
		//TODO Set cookie CASTGC = $tgc
		$this->getWithParameters(CAS_LOGOUT_PATH);
	}
	/**
	 *  Deletes the event with the given id
	 *  
	 *  @param Event id $id 
	 */
	public function deleteEvent($id)	{
		//TODO this
	}
	/**
	 * Gets an RSS feed that requires authentication
	 */
	public function getFeed()	{
		//TODO this
	}	
}
?>
<?php
class CurlWrapper	{
	private $curlHandle;
	private $cookieFile;
	private $response;
	
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
	 * @param String $url
	 * @param String $parameters
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
	 * Checks if a siteId refers to a valid cows site
	 * @param String $siteId
	 * @return boolean
	 */
	public function validateSiteID($siteId)	{
		$this->getWithParameters(COWS_BASE_PATH . $siteId);
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
		$params = array(
			"service" => $service,
			"pgt" => $tgc	
		);
		$out = $this->getWithParameters(CAS_PROXY_PATH,$params);
		
		//Quick and dirty parsing of the CAS response
		if (strpos($out,"proxyFailure") === false)	{
			$out = strip_tags($out);
			$out = str_replace(' ', '', $out);
			$out = str_replace('\n','', $out);
			$out = str_replace('\t','', $out);
			$out = str_replace('\r', '', $out);
			return $out;
		}	
		else	{
			throwError(ERROR_CAS, "Unable to get service ticket");
		}
	}
	
	/**
	 * Executes a login to COWS
	 * 
	 * @param String $tgc
	 * @param String $siteId
	 * @return boolean
	 */
	public function cowsLogin($tgc, $siteId)	{
		$returnURL = COWS_BASE_PATH . $siteId . "/";
		$loginURL = COWS_BASE_PATH . $siteId . COWS_LOGIN_PATH;
		
		$service = $loginURL . "?returnUrl=" . $returnURL;
		$ticket = $this->getTicket($tgc,$service);
		
		$params = array("returnUrl" => $returnURL,
				"ticket" => $ticket
		);
		$out = getWithParameters($loginURL,$params);
		$last = curl_getinfo($this->curlHandle, CURLINFO_EFFECTIVE_URL);
		if (strpos($last,"cows.ucdavis.edu") === false)	{
			throwError(ERROR_CAS, "Unable to login");
		}
	}
	/**
	 * Gets the request verification token and other fields
	 * from Cows via scraping the /event/create page
	 */
	public function getCowsFields($siteId)	{
		if (!$this->loggedIn) throwError(ERROR_COWS,"Not logged in");
		$out = $this->getWithParameters(COWS_BASE_PATH . $siteId . COWS_EVENT_PATH);
		$doc = new DocumentWrapper($out);
		return array(
				"__RequestVerificationToken" => $doc->getField("__RequestVerificationToken"),
				"ContactName" => $doc->getField("ContactName"),
				"ContactEmail" => $doc->getField("ContactEmail"),
				"EventStatusName" => $doc->getField("EventStatusName")
		);
	}
	/**
	 * Gets the requestVerificationToken from a specified URL
	 * @param String $url
	 * @return String $token
	 */
	private function getRequestVerificationToken($url)	{
		$out = $this->getWithParameters($url);
		$doc = new DocumentWrapper($out);
		return $doc->getField("__RequestVerificationToken");
	}
	/**
	 * Executes a logout of cows
	 * 
	 * @return boolean
	 */
	public function cowsLogout($siteId)	{
		$this->getWithParameters(COWS_BASE_PATH . $siteId . COWS_LOGOUT_PATH);
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
	 *  @param Site Id $siteId
	 *  @param Event id $id 
	 */
	public function deleteEvent($siteId,$id)	{
		$url = COWS_BASE_PATH . $siteId . COWS_DELETE_PATH;
		$params = array(
				"SiteId" => $siteId,
				"EventId" => $id,
				"__RequestVerificationToken" => $this->getRequestVerificationToken(),
				"timestamp" => "AAAAAAAOH6s="
		);
		$this->postWithParameters($url,$params);
	}
	/**
	 * Gets an RSS feed that requires authentication
	 */
	public function getFeed($siteId, $params)	{
		return $this->getWithParameters(COWS_BASE_PATH . $siteId . COWS_RSS_PATH . '?' . http_build_query($params));
	}
	
	public function createEvent($siteId,$params)	{
		if (!$this->loggedIn) throwError(ERROR_GENERIC, "Must be logged in to create an event.");
		if (!is_array($params)) throwError(ERROR_GENERIC, "Parameters for createEvent must be an array");
		
		if (!isset($params['Categories'])) throwError(ERROR_PARAMETERS, "Categories must be set",400);
		$cat = urldecode($params['Categories']);
		unset($params['Categories']);
		if (strlen($cat) > 0) $cat = split("&",$cat);
		$appendString = "";
		foreach($cat as $str)	{
			$appendString .= "&Categories=" . urlencode($str);
		}
		
		if (isset($params['Locations']))	{
			$loc = urldecode($params['Locations']);
			unset($params['Locations']);
			if (strlen($loc) > 0) $loc = split("&",$loc);
			foreach($loc as $str)	{
				$appendString .= "&DisplayLocations=" . urlencode($str);
			}
		}
		
		$url = COWS_BASE_PATH . $siteId . COWS_EVENT_PATH;
		$params = http_build_query(array_merge($params,$this->getCowsFields($siteId))) . $appendString;
		$out = $this->postWithParameters($url,$params);
		$doc = new DocumentWrapper($out);
		$doc->findCowsError();
	}
	
	public function getSingleEvent($siteId, $eventId)	{
		$out = $this->getWithParameters(COWS_BASE_PATH . $siteId . COWS_EVENT_PATH . "/details/" . $eventId);
		$doc = new DocumentWrapper($out);
		return $doc->parseEvent();
	}
}
?>
<?php
/**
 * Used to abstract all HTTP functions away from other parts of the code
 * 
 * @author its-zach
 *
 */
class CurlWrapper	{
	private $curlHandle;
	private $cookieFile;
	
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
		$path = DIRECTORY_SEPARATOR . "cookies" . DIRECTORY_SEPARATOR . "cookieFile" . $randString;
		return realpath(dirname(__FILE__)) . $path;
	}
	
	public function __construct($cookieFile = null)	{
		$this->curlHandle = curl_init();
		if ($cookieFile == null && !(strlen($cookieFile) == 0 && !is_null($cookieFile)))	{
			$this->cookieFile = $this->genFilename();
		}
		else $this->cookieFile = $cookieFile;
		curl_setopt($this->curlHandle, CURLOPT_COOKIEJAR, $this->cookieFile);
		curl_setopt($this->curlHandle, CURLOPT_COOKIEFILE, $this->cookieFile);
		
		curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->curlHandle, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($this->curlHandle, CURLOPT_SSL_VERIFYPEER, false);
		
		curl_setopt ($this->curlHandle, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/28.0.1500.72 Safari/537.36");
		curl_setopt ($this->curlHandle, CURLOPT_AUTOREFERER, true );
	}
	/**
	 * Generate a CurlWrapper instance without a cookie
	 * @return CurlWrapper
	 */
	public static function CreateWithoutCookie()	{
		$curl = new CurlWrapper();
		if (file_exists($curl->cookieFile)) unlink($curl->cookieFile);
		$curl->clearCookieHandler();
		return $curl;
	}
	/**
	 * Remove cookie handling for a cURL instance that doesn't need cookies
	 */
	private function clearCookieHandler()	{
		curl_setopt($this->curlHandle, CURLOPT_COOKIEJAR, null);
		curl_setopt($this->curlHandle, CURLOPT_COOKIEFILE, null);
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
			$parameters = http_build_query($parameters);
		}
		curl_setopt($this->curlHandle, CURLOPT_HTTPGET, true);
		if ($parameters != "") curl_setopt($this->curlHandle, CURLOPT_URL, $url . "?" . $parameters);
		else curl_setopt($this->curlHandle, CURLOPT_URL, $url);
		$out = curl_exec($this->curlHandle);
		
		if ($out === false) throwError(ERROR_CURL,curl_error($this->curlHandle));
		if (curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE) == 404) throwError(ERROR_PARAMETERS,"Page was not found",400);
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
	 * It does this by checking if it can get a valid ticket for a dummy service
	 * 
	 * @param String $tgc
	 * @return boolean
	 */
	public function validateTGC($tgc)	{
		$params = array("pgt" => $tgc,
			  "targetService" => "http://");
		$resp = $this->getWithParameters(CAS_PROXY_PATH, $params);
		if (strpos($resp, 'proxyFailure') !== false)	{
			return false;
		}
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
			return trim($out);
		}	
		else	{
			if (strpos($out,"INVALID_TICKET") !== false) throwError(ERROR_CAS, "Invalid TGC, Unable to get service ticket");
			else throwError(ERROR_CAS, "Unable to get service ticket");
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
		$returnURL = COWS_BASE_PATH . $siteId;
		$loginURL = COWS_BASE_PATH . $siteId . COWS_LOGIN_PATH;
		
		$service = $loginURL . "?returnUrl=" . $returnURL;
		$ticket = $this->getTicket($tgc,$service);
		
		$params = array("returnUrl" => $returnURL,
				"ticket" => $ticket
		);
		$out = $this->getWithParameters($loginURL,$params);
		$last = curl_getinfo($this->curlHandle, CURLINFO_EFFECTIVE_URL);
		if (strpos($last,"cas.ucdavis.edu") !== false)	{
			throwError(ERROR_CAS, "Unable to login");
		}
	}
	/**
	 * Gets the request verification token and other fields
	 * from Cows via scraping the /event/create page
	 */
	public function getCowsFields($siteId)	{
		$out = $this->getWithParameters(COWS_BASE_PATH . $siteId . COWS_EVENT_PATH);
		$doc = new DocumentWrapper($out);
		$doc->getField("__RequestVerificationToken");
		$phone = $doc->getField("ContactPhone");
		if ($phone == "" || $phone == null) $phone = "No Phone";
		return array(
				"__RequestVerificationToken" => $doc->getField("__RequestVerificationToken"),
				"ContactName" => $doc->getField("ContactName"),
				"ContactPhone" => $phone,
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
		curl_setopt($this->curlHandle, CURLOPT_COOKIEJAR, null);
		curl_setopt($this->curlHandle, CURLOPT_COOKIEFILE, null);

		curl_setopt($this->curlHandle, CURLOPT_COOKIE, "CASTGC=" . $tgc);
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
				"__RequestVerificationToken" => $this->getRequestVerificationToken($url . "/" . $id),
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
	/**
	 * Creates an event
	 * 
	 * @param string $siteId
	 * @param array $params
	 * @return event ID
	 */
	public function createEvent($siteId,$params)	{
		//Check Params
		if (!is_array($params)) throwError(ERROR_GENERIC, "Parameters for createEvent must be an array");
		
		$appendString = "";
		
		//Parse DisplayLocation and Category parameters
		if (!isset($params['Categories'])) throwError(ERROR_PARAMETERS, "Categories must be set",400);
		$cat = urldecode($params['Categories']);
		unset($params['Categories']);
		
		$catString = "";
		if (strlen($cat) > 0) {
			$cat = explode("&",$cat);
			foreach($cat as $str)	{
				$catString .= "&Categories=" . urlencode($str);
			}			
		}
	
		$locString = "";
		if (isset($params['Locations']))	{
			$loc = urldecode($params['Locations']);
			unset($params['Locations']);
			if (strlen($loc) > 0) {
				$loc = explode("&",$loc);
				foreach($loc as $str)	{
					$locString .= "&DisplayLocations=" . urlencode($str);
				}
			}
			
		}
		
		if ($catString != "")	{
			$appendString .= $catString;
		}
		
		if ($locString != "")	{
			$appendString .= $locString;
		}
		//Execute request
		$url = COWS_BASE_PATH . $siteId . COWS_EVENT_PATH;
		$paramsFinal = http_build_query(array_merge($params,$this->getCowsFields($siteId))) . $appendString . "&siteId=" . $siteId;
		$out = $this->postWithParameters($url,$paramsFinal);
		$doc = new DocumentWrapper($out);
		$doc->findCowsError();
		return $this->getEventId($siteId,$params,$cat);
	}
	/**
	 * Gets event data for a single event
	 * 
	 * @param string $siteId
	 * @param string $eventId
	 * @return string
	 */
	public function getSingleEvent($siteId, $eventId)	{
		$out = $this->getWithParameters(COWS_BASE_PATH . $siteId . COWS_BASE_EVENT_PATH . "/details/" . $eventId);
		$doc = new DocumentWrapper($out);
		return $doc->parseEvent();
	}
	/**
	 * Gets the event id for a specific event, used to return the event id from an event that was just created
	 * 
	 * @param string $siteId
	 * @param array $params
	 * @param Catagories String $cats
	 * @return multitype:NULL
	 */
	private function getEventId($siteId,$params, $cats)	{	
		//Get jsonbyday json for the date the event was created
		$paramsNew = array();
		$url = COWS_BASE_PATH . $siteId . COWS_BASE_EVENT_PATH . "/jsonbyday";
		$paramsNew['startDate'] = $params['StartDate'];
		$paramsNew['endDate'] = $params['EndDate'];
		$paramsNew = http_build_query($paramsNew);
		//Add category specifiers to the query string
		foreach($cats as $cat)	{
			$paramsNew .= "&categories=" . $cat;
		}
		//get json
		$out = $this->getWithParameters($url,$paramsNew);
		$out = json_decode($out,true);
	
		if (!is_array($out))	{
			throwError(ERROR_COWS, "Invalid json given to find eventId" ,500);
		}
		//Look for a matching title/room
		foreach ($out['e'] as $event)	{
			if ($event[0]['t'] == $params['EventTitle'] &&
				$event[0]['l'] == "R" . $params['BuildingAndRoom'])	{
				return array(
						"eventId" => $event[0]['i']
				);
			}
		}
		throwError(ERROR_COWS, "Could not find eventId");
	}
}
?>
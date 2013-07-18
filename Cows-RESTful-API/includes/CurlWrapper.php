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
		if ($cookieFile == null)	{
			$this->cookieFile = genFilename();
		}
		else $this->cookieFile = $cookieFile;
		$this->curlHandle = curl_init();
	
		curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->curlHandle, CURLOPT_COOKIEJAR, $this->cookieFile);
		curl_setopt($this->curlHandle, CURLOPT_COOKIEFILE, $this->cookieFile);
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
	public function getWithParameters($url, $parameters)  {
		if (is_array($parameters))	{
			$paramters = http_build_query($parameters);
		}
		curl_setopt($this->curlHandle, CURLOPT_HTTPGET, true);
		curl_setopt($this->curlHandle, CURLOPT_URL, $url . "?" . $parameters);
		$out = curl_exec($this->curlHandle);
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
	public function postWithParameters($url, $parameters)  {
		if (is_array($parameters))	{
			$paramters = http_build_query($parameters);
		}
		curl_setopt($this->curlHandle, CURLOPT_POST, true);
		curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $parameters);
		$out = curl_exec($this->curlHandle);
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
}
?>
<?php

require_once 'Utility.php';
require_once 'CurlWrapper.php';

class SessionWrapper	{
	private $sessionKey;
	private $dbHandle;
	private $sessionVar;	
	
	public function __construct($sessionKey)	{
		$this->dbHandle =  new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
		$this->sessionKey = $sessionKey;
		$query = $this->dbHandle->prepare("SELECT * FROM " . DB_TABLE . " WHERE sessionKey = :key");
		$query->bindParam(":key", $this->sessionKey, PDO::PARAM_STR);
		$this->execute($query);
		$this->sessionVar = $query->fetch();
		if ($this->sessionVar === false)	{
			throwError(ERROR_DB,'Session key not Found');
		}
	}
	
	public static function createSession($tgc,$siteID,$pubKey)	{
		$handle = new CurlWrapper();
		$cookieFile = $handle->getCookieFile();
		unset($handle);
		
		$dbHandle =  new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
		
		$query = $dbHandle->prepare("SELECT * FROM " . DB_TABLE . " WHERE publicKey = :key");
		$query->bindParam(":key", $pubKey, PDO::PARAM_STR);
		if ($query->execute() === false)	{
			throwError(ERROR_DB, "Unable to execute your query.");
		}
		
		if ($query->fetch() != FALSE)	{
			$out = $query->fetch();
			if (file_exists($out['cookieFile'])) 
				unlink($out['cookieFile']);
			$sid = hash_hmac("sha256", $pubKey.$siteId.time(), $out['privateKey']);
			$curl = new CurlWrapper();
			$cookie = $curl->getCookieFile();
			$curl->cowsLogin($tgc, $siteID);
			unset($curl);
		}
		else	{
			throwError(ERROR_PARAMETERS, "Unrecognized Public Key", 400);
		}
		
		$query = $dbHandle->prepare("UPDATE " . DB_TABLE 
				. " SET sessionKey = :sid, cookieFile = :cookie"
				. " WHERE publicKey = :key");
		$query->bindParam(":sid",$sid, PDO::PARAM_STR);
		$query->bindParam(":cookie", $cookie, PDO::PARAM_STR);
		$query->bindParam(":key", $pubKey, PDO::PARAM_STR);
		if ($query->execute() === false)	{
			throwError(ERROR_DB,$query->errorInfo());
		}
		
		return new SessionWrapper($sessionKey);
	}
	
	public function getCookieFile()	{
		return $this->sessionVar['cookieFile'];
	}
	
	public function getSessionKey()	{
		return $this->sessionKey;
	}
	
	public static function checkKey()	{
		$app = new \Slim\Slim();
		
		$inputKey = $app->request()->params('signature');
		$data = $_SERVER['REQUEST_METHOD'].$_SERVER['REQUEST_URI'];
		//Regex from http://stackoverflow.com/questions/1842681/regular-expression-to-remove-one-parameter-from-query-string
		$params = preg_replace("/&signature(\=[^&]*)?(?=&|$)|^signature(\=[^&]*)?(&|$)/", "", $_SERVER['QUERY_STRING'],1);
		$data = $data . $params;
		$outputKey = hash_hmac("sha256",$data,$this->getPrivateKey());
		return strtolower($outputKey) == strtolower($inputKey);
	}
	
	public static function checkAltKey ()	{
		$app = new \Slim\Slim();
		
		$inputKey = $app->request()->params('signature');
		$pubKey = $app->request()->params('publicKey');
		
		$data = $_SERVER['REQUEST_METHOD'].$_SERVER['REQUEST_URI'];
		//Regex from http://stackoverflow.com/questions/1842681/regular-expression-to-remove-one-parameter-from-query-string
		$params = preg_replace("/&signature(\=[^&]*)?(?=&|$)|^signature(\=[^&]*)?(&|$)/", "", $_SERVER['QUERY_STRING'],1);
		$data = $data . $params;
		$outputKey = hash_hmac("sha256",$data,$pubKey);
		return strtolower($outputKey) == strtolower($inputKey);
	}
	
	public function __destruct()	{
		unset($this->dbHandle);
	}
	
	public function destroySession()	{
		$handle = new CurlWrapper($this->getCookieFile());
		$handle->cowsLogout($this->getSiteId());
		unset($handle);
		
		if (file_exists($this->getCookieFile())) unlink($this->getCookieFile());
		
		$query = $this->dbHandle->prepare("UPDATE " . DB_TABLE . 
				"SET sessionKey = '', cookieFile = '' WHERE sessionKey = :key");
		$query->bindParam(":key", $this->sessionKey);
		$this->execute($query);
		
		unset($this->dbHandle);
		unset($this->sessionVar);
	}
	/**
	 * Executes a PDO query, checks for errors in execution.
	 * @param PDO Query Object $query
	 */
	private function execute($query)	{
		if ($query->execute() === false)	{
			throwError(ERROR_DB,$query->errorInfo());
		}
	}
}
?>
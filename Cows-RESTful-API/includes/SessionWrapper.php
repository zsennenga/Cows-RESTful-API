<?php

require_once 'Utility.php';
require_once 'CurlWrapper.php';

class SessionWrapper	{
	private $dbHandle;
	private $sessionVar;
	private $publicKey;
	
	public function __construct($publicKey)	{
		$this->publicKey = $publicKey;
		$this->dbHandle =  new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
		$query = $this->dbHandle->prepare("SELECT * FROM " . DB_TABLE . " WHERE publicKey = :key");
		$query->bindParam(":key", $this->publicKey, PDO::PARAM_STR);
		$this->execute($query);
		$this->sessionVar = $query->fetch();
		if ($this->sessionVar === false)	{
			throwError(ERROR_PARAMS, "Invalid public key", 400);
		}
		if ($this->sessionVar['cookieFile'] == '')	{
			$curl = new CurlWrapper();
			$cookie = $curl->getCookieFile();
			$curl->cowsLogin($tgc, $siteID);
			unset($curl);
			
			$query = $dbHandle->prepare("UPDATE " . DB_TABLE
					. " SET cookieFile = :cookie"
					. " WHERE publicKey = :key");
			$query->bindParam(":cookie", $cookie, PDO::PARAM_STR);
			$query->bindParam(":key", $pubKey, PDO::PARAM_STR);
			if ($query->execute() === false)	{
				throwError(ERROR_DB,$query->errorInfo());
			}
		}
	}
	
	public function getCookieFile()	{
		return $this->sessionVar['cookieFile'];
	}
	
	public function getPublicKey()	{
		return $this->publicKey;
	}

	public static function checkKey ()	{
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
				"SET cookieFile = '' WHERE publicKey = :key");
		$query->bindParam(":key", $this->publicKey);
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
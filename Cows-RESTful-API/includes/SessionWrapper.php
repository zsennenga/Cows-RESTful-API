<?php

require_once 'Utility.php';
require_once 'CurlWrapper.php';

class SessionWrapper	{
	private $dbHandle;
	private $sessionVar;
	private $publicKey;
	private $siteId;
	
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
	}
	
	public function createSession($tgc, $siteId)	{
		$this->siteId = $siteId;
		if ($this->sessionVar['cookieFile'] == '')	{
			$curl = new CurlWrapper();
			$cookie = $curl->getCookieFile();
			$curl->cowsLogin($tgc, $siteId);
			unset($curl);
			$query = $this->dbHandle->prepare("UPDATE " . DB_TABLE
					. " SET cookieFile = :cookie"
					. " WHERE publicKey = :key");
			$query->bindParam(":cookie", $cookie, PDO::PARAM_STR);
			$query->bindParam(":key",$this->sessionVar['publicKey'], PDO::PARAM_STR);
			if ($query->execute() === false)	{
				throwError(ERROR_DB,$query->errorInfo());
			}
			$query = $this->dbHandle->prepare("SELECT * FROM " . DB_TABLE . " WHERE publicKey = :key");
			$query->bindParam(":key", $this->publicKey, PDO::PARAM_STR);
			$this->execute($query);
			$this->sessionVar = $query->fetch();
			if ($this->sessionVar === false)	{
				throwError(ERROR_PARAMS, "Invalid public key", 400);
			}
		}
		else	{
			$curl = new CurlWrapper($this->sessionVar['cookieFile']);
			$curl->cowsLogin($tgc, $siteId);
		}
	}
	
	public function getCookieFile()	{
		return $this->sessionVar['cookieFile'];
	}
	
	public function getPublicKey()	{
		return $this->publicKey;
	}

	public function __destruct()	{
		unset($this->dbHandle);
	}
	
	public function destroySession()	{
		$cookie = $this->getCookieFile();
		$handle = new CurlWrapper($cookie);
		$handle->cowsLogout($this->siteId);
		unset($handle);
		
		$query = $this->dbHandle->prepare("UPDATE " . DB_TABLE . 
				" SET cookieFile = '' WHERE publicKey = :key");
		$query->bindParam(":key", $this->publicKey);
		$this->execute($query);
		
		if (file_exists($cookie)) unlink($cookie);
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
	
	public static function checkKey ()	{
		$app = new \Slim\Slim();
	
		$inputKey = $_REQUEST['signature'];
		$pubKey = $app->request()->params('publicKey');
		
		$dbHandle =  new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
		$query = $dbHandle->prepare("SELECT * FROM " . DB_TABLE . " WHERE publicKey = :key");
		$query->bindParam(":key", $pubKey, PDO::PARAM_STR);
		if ($query->execute() === false)	{
			return false;
		}
		$out = $query->fetch();
		if ($out === false)	{
			return false;
		}
		$privKey = $out['privateKey'];
	
		$data = $_SERVER['REQUEST_METHOD'];
		$qstring =  explode("?",$_SERVER['REQUEST_URI']);
		if (count($qstring) >= 2)	{
			//Regex from http://stackoverflow.com/questions/1842681/regular-expression-to-remove-one-parameter-from-query-string
			$data = $data . $qstring[0] . preg_replace('/&signature(\=[^&]*)?(?=&|$)|^signature(\=[^&]*)?(&|$)/', "", $qstring[1],1);
		}
		else {
			$data = $data . $_SERVER['REQUEST_URI'];
			$params = $app->request()->params();
			unset($params['signature']);
			$data = $data . http_build_query($params);
		}
		$outputKey = hash_hmac("sha256",$data,$privKey);
		return $outputKey === $inputKey;
	}
	
}
?>
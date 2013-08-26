<?php

require_once 'Utility.php';
require_once 'CurlWrapper.php';

/**
 * Handles all authentication/deauthentication to cows and the API
 * 
 * @author its-zach
 *
 */
class SessionWrapper	{
	private $dbHandle;
	private $sessionVar;
	private $publicKey;
	private $siteId;
	
	/**
	 * 
	 * Given a public key generates a session wrapper
	 * 
	 * @param string $publicKey
	 */
	public function __construct($publicKey)	{
		$this->publicKey = $publicKey;
		$this->dbHandle =  new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
		$query = $this->dbHandle->prepare("SELECT * FROM " . DB_TABLE . " WHERE publicKey = :key");
		$query->bindParam(":key", $this->publicKey, PDO::PARAM_STR);
		$this->execute($query);
		$this->sessionVar = $query->fetch();
		if ($this->sessionVar === false)	{
			throwError(ERROR_PARAMETERS, "Invalid public key", 400);
		}
	}
	/**
	 * Logs into cows with a given session
	 * @param Ticket Granting Cookie $tgc
	 * @param string $siteId
	 */
	public function createSession($tgc, $siteId)	{
		$this->siteId = $siteId;
		//No cookie file
		if ($this->sessionVar['cookieFile'] == '')	{
			//Login to cows
			$curl = new CurlWrapper();
			$cookie = $curl->getCookieFile();
			$curl->cowsLogin($tgc, $siteId);
			unset($curl);
			//Save cookie file in the database
			$query = $this->dbHandle->prepare("UPDATE " . DB_TABLE
					. " SET cookieFile = :cookie"
					. " WHERE publicKey = :key");
			$query->bindParam(":cookie", $cookie, PDO::PARAM_STR);
			$query->bindParam(":key",$this->sessionVar['publicKey'], PDO::PARAM_STR);
			if ($query->execute() === false)	{
				throwError(ERROR_DB,$query->errorInfo());
			}
			//Updated the $this->sessionVar variable with the most recent values
			$this->sessionVar['cookieFile'] = $cookie;
		}
		//Session is still open - just perform a login to refresh the session
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
	/**
	 * Logs you out from cows, deletes your cookies
	 */
	public function destroySession()	{
		//Logout from cows
		$cookie = $this->getCookieFile();
		$handle = new CurlWrapper($cookie);
		$handle->cowsLogout($this->siteId);
		unset($handle);
		//Clear cookie from DB
		$query = $this->dbHandle->prepare("UPDATE " . DB_TABLE . 
				" SET cookieFile = '' WHERE publicKey = :key");
		$query->bindParam(":key", $this->publicKey);
		$this->execute($query);
		//Destroy Cookie file
		if (file_exists($cookie)) unlink($cookie);
		//Clean up
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
	/**
	 * Checks if a signature passed in is valid
	 * @return boolean
	 */
	public static function checkKey ($inputKey, $pubKey, $time)	{
		$app = new \Slim\Slim();
		
		//Get private key from the db based on public Key
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
		
		//Parse the correct data string for hashing - remove the signature from the query string
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
		//Check the keys
		$outputKey = hash_hmac("sha256",$data.$time,$privKey);
		return $outputKey === $inputKey;
	}
	
}
?>
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
	
	public static function createSession($tgc,$siteID)	{
		$handle = new CurlWrapper();
		$cookieFile = $handle->getCookieFile();
		unset($handle);
		
		$sessionKey = sha1($tgc.$siteID.time());
		$sid = sha1($tgc.$siteID);
		$dbHandle =  new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
		$query = $dbHandle->prepare("SELECT * FROM " . DB_TABLE . " WHERE sid = :key");
		$query->bindParam(":key", $sid, PDO::PARAM_STR);
		if ($query->execute() === false)	{
			throwError(ERROR_DB,$query->errorInfo());
		}
		
		if ($query->fetch() != FALSE)	{
			$out = $query->fetch();
			if (file_exists($out['cookieFile'])) unlink($out['cookieFile']);
			$query = $dbHandle->prepare("DELETE FROM " . DB_TABLE . " WHERE sid = :key");
			$query->bindParam(":key", $sid);
			if ($query->execute() === false)	{
				throwError(ERROR_DB,$query->errorInfo());
			}
		}
		
		$query = $dbHandle->prepare("INSERT INTO " . DB_TABLE . " VALUES (:sid, :key, :cookie,:id,:tgc)");
		$query->bindParam(":sid",$sid);
		$query->bindParam(":key", $sessionKey);
		$query->bindParam(":id", $siteID);
		$query->bindParam(":cookie", $cookieFile);
		$query->bindParam(":tgc", $tgc);
		if ($query->execute() === false)	{
			throwError(ERROR_DB,$query->errorInfo());
		}
		
		return new SessionWrapper($sessionKey);
	}
	
	public function getSiteId()	{
		return $this->sessionVar['siteID'];
	}
	
	public function getTGC()	{
		return $this->sessionVar['tgc'];
	}
	
	public function getCookieFile()	{
		return $this->sessionVar['cookieFile'];
	}
	
	public function getSessionKey()	{
		return $this->sessionKey;
	}
	
	public function __destruct()	{
		unset($this->dbHandle);
	}
	
	public function destroySession()	{
		$handle = new CurlWrapper($this->getCookieFile());
		$handle->cowsLogout($this->getSiteId());
		$handle->casLogout($this->getTGC());
		unset($handle);
		
		if (file_exists($this->getCookieFile())) unlink($this->getCookieFile());
		
		$query = $this->dbHandle->prepare("DELETE FROM " . DB_TABLE . " WHERE sessionKey = :key");
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
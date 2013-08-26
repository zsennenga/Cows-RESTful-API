<?php
class Log	{
	private static $logInstance = null;
	
	private $dbHandle;
	private $stmt;
	
	private $publicKey;
	private $route;
	private $response;
	private $params;
	private $method;
	
	private function Log()	{
		$this->dbHandle =  new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
		$this->stmt = $this->dbHandle->prepare("INSERT INTO ".DB_TABLE_LOG." (ip,publicKey,route,method,params,response) VALUES (:ip,:pkey,:route,:method,:params,:response)");
		$this->publicKey = "";
		$this->route = "";
		$this->response = "";
		$this->params = "";
		$this->method = "";
	}
	
	public static function getInstance()	{
		static $logInstance;
		if ($logInstance == null) $logInstance = new Log();
		return $logInstance;
	}
	
	public function setKey($pk)	{
		$this->publicKey = $pk;
	}
	
	public function setRoute($r,$m)	{
		$this->route = $r;
		$this->method = $m;
	}
	
	public function setResp($res)	{
		$this->response = $res;
	}
	
	public function setParams($p)	{
		$p = http_build_query($p);
		if (strpos($p, 'tgc') !== FALSE)	{
			//Don't wanna store any TGCs
			$p = preg_replace('/&tgc(\=[^&]*)?(?=&|$)|^tgc(\=[^&]*)?(&|$)/', "", $p,1);
		}
		$this->params = $p;
	}
	
	public function doLog()	{
		$this->stmt->bindValue(":ip", $_SERVER['REMOTE_ADDR']);
		$this->stmt->bindValue(":pkey", $this->publicKey);
		$this->stmt->bindValue(":route", $this->route);
		$this->stmt->bindValue(":method", $this->method);
		$this->stmt->bindValue(":params", $this->params);
		$this->stmt->bindValue(":response", $this->response);
		if (!$this->stmt->execute())	{
		}
	}
}
?>
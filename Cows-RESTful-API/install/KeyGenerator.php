<?php
define("DB_HOST", "dbHost");
define("DB_NAME", "dbName");
define("DB_TABLE", "dbTable");
define("DB_USER", "dbUser");
define("DB_PASS", "dbPass");

$dbHandle =  new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
if(!defined('STDIN'))	{
	echo "Please run from command line\n";
	exit(0);
}
if (sizeof($argv) != 2)	{
	echo "Usage: ./KeyGenerator.php comment\n";
	exit (0);
}
$comment = $argv[1];
$charset = "!@#$%^&*()_+[]{}<>,.?:;'|0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
$randString = '';
for ($i = 0; $i < 1024; $i++) {
	$randString .= $charset[mt_rand(0, strlen($charset)-1)];
}
$privateKey = hash("sha512",$randString);
$publicKey = hash("sha256",$comment.time());

$query = $dbHandle->prepare("Insert Into " . DB_TABLE . " VALUES (:private, :public, '', '', :comment)");
$query->bindParam(":private", $privateKey, PDO::PARAM_STR);
$query->bindParam(":public", $publicKey, PDO::PARAM_STR);
$query->bindParam(":comment", $comment, PDO::PARAM_STR);
if (!$query->execute())	{
	echo "Unable to insert into database. Error: " . $query->errorInfo() ."\n";
	exit(0);
}
echo "Private Key: " . $privateKey . "\n";
echo "Public Key: " . $publicKey . "\n";
?>
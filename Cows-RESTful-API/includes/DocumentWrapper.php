<?php
class DocumentWrapper	{
	private $doc;
	
	public function __construct($data)	{
		//Generate Xpath from the html output of a cURL query
		$doc = new DOMDocument();
		$doc->loadHTML($data);
		$this->doc = new DOMXPath($doc);
	}
	
	public function findCowsError()	{
		$div = $this->doc->query('//div[@class="validation-summary-errors"]');
		
		//Any results means cows threw an error
		if ($div->length > 0)	{
			$div = $div->item(0);
			$error = str_replace('may not be null or empty', '', $div->nodeValue);
			throwError(ERROR_EVENT, "COWS Error: " . strip_tags(htmlspecialchars_decode($error),400));
		}
		
		//Cows likes to throw generic errors sometimes for no reason
		//Well okay there is usually a reason
		if (strstr($htmlOutput,"Error") !== false)	{
			throwError(ERROR_EVENT, "COWS Error: Unknown Problem occurred.",400);
		}
	}
	
	public function getField($field)	{
		$nodes = $this->doc->query('//input[@name="'.$field.'"]');
		if ($nodes->length == 0)	{
			throwError(ERROR_CAS, "Unable to obtain ". $field ,400);
		}
		$node = $nodes->item(0);
		
		$val = $node->getAttribute('value');
		return $val;
	}
	
	public function parseEvent()	{
		$retArray = array(
				"title" => "",
				"category" => "",
				"startDate" => "",
				"endDate" => "",
				"startTime" => "",
				"endTime" => "",
				"building" => "",
				"room" => ""
		);
		//TODO FIX THIS
		$q = $this->doc->query('//div[@class="EventTypeName"]/display-field');
		$retArray['category'] = $q->item(0);
		$q = $this->doc->query('//div[@class="StartDate"]/display-field/date');
		$retArray['startDate'] = $q->item(0)->nodeValue;
		$q = $this->doc->query('//div[@class="StartDate"]/display-field/time');
		$retArray['endDate'] = $q->item(0)->nodeValue;
		$q = $this->doc->query('//div[@class="EndDate"]/display-field/date');
		$retArray['startTime'] = $q->item(0)->nodeValue;
		$q = $this->doc->query('//div[@class="EndDate"]/display-field/time');
		$retArray['endTime'] = $q->item(0)->nodeValue;
		$q = $this->doc->query('//div[@class="BuildingName"]/display-field');
		$retArray['building'] = $q->item(0)->nodeValue;
		$q = $this->doc->query('//div[@class="RoomName"]/display-field');
		$retArray['room'] = $q->item(0)->nodeValue;
		
		return json_encode($retArray);
	}
}
?>
<?php
class DocumentWrapper	{
	private $doc;
	private $rawData;
	
	public function __construct($data)	{
		//Generate Xpath from the html output of a cURL query
		if ($data == "")	{
			throwError(ERROR_PARAMETERS, "Blank document given. Make sure event id/site id are correct.",400);
		}
		$this->rawData = $data;
		$doc = new DOMDocument();
		libxml_use_internal_errors(true);
		$doc->loadHTML($data);
		libxml_use_internal_errors(false);
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
		if (strstr($this->rawData,"Error") !== false)	{
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
		
		$q = $this->doc->query('//div[@class="EventTypeName"]/div[@class="display-field"]');
		if (!is_object($q->item(0))) throwError(ERROR_PARAMETERS,"Was not able to parse single event. 
				Check that the event id is correct and whether or not you need to be authorized to access this siteid.",400);
		$retArray['category'] = $q->item(0)->nodeValue;
		
		$q = $this->doc->query('//div[@class="StartDate"]/div[@class="display-field"]/span[@class="date"]');
		$retArray['startDate'] = $q->item(0)->nodeValue;
		
		$q = $this->doc->query('//div[@class="StartDate"]/div[@class="display-field"]/span[@class="time"]');
		$retArray['endDate'] = $q->item(0)->nodeValue;
		
		$q = $this->doc->query('//div[@class="EndDate"]/div[@class="display-field"]/span[@class="date"]');
		$retArray['startTime'] = $q->item(0)->nodeValue;
		
		$q = $this->doc->query('//div[@class="EndDate"]/div[@class="display-field"]/span[@class="time"]');
		$retArray['endTime'] = $q->item(0)->nodeValue;
		
		$q = $this->doc->query('//div[@class="BuildingName"]/div[@class="display-field"]');
		$retArray['building'] = $q->item(0)->nodeValue;
		
		$q = $this->doc->query('//div[@class="RoomName"]/div[@class="display-field"]');
		$retArray['room'] = $q->item(0)->nodeValue;
		
		$q = $this->doc->query('//div[@id="event-dialog"]');
		$retArray['title'] = $q->item(0)->getAttribute("title");
		
		return json_encode($retArray);
	}
}
?>
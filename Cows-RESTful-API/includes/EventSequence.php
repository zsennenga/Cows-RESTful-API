<?php
/**
 * eventSequence.php
 * 
 * Contains the class eventSequence and associated functions
 */
require_once("CowsRSS.php");
require_once("Event.php");
/**
 * eventSequence
 * 
 * Contains and manages a sequence of event objects
 * 
 * @author its-zach
 *
 */
class eventSequence	{
	/**
	 * eventList
	 * 
	 * array containing all the events referenced by this sequence
	 * 
	 * @var array
	 */
	private $eventList;
	/**
	 * displayPast
	 * 
	 * boolean, defaulting to false, which specifies whether or not events in the past are to be outputed from toString()
	 * 
	 * @var boolean
	 */
	private $displayPast;
	
	/**
	 * __construct
	 * 
	 * Builds a sequence of events in $eventList
	 * 
	 * @param array $eventArray
	 */
	function __construct($eventArray)	{
		$this->eventList = array();
		foreach ($eventArray as $event)	{
			array_push($this->eventList,new event($event));
		}
		usort($this->eventList,'eventSequence::doSort');
		$this->displayPast = false;
	}
	/**
	 * createSequenceFromArrayCountBounded
	 * 
	 * Creates an eventSequence with the closest $count events
	 * 
	 * @param array $es
	 * @param int $count
	 */
	public static function createSequenceFromArrayCountBounded($eventArray, $count, $displayPast)	{
		$eventSource = new eventSequence($eventArray);
		$eventOut = new eventSequence(array());
		$i = 0;
		$eventOut->displayPast = $displayPast;
		foreach ($eventSource->getList() as $event)	{
			if ($i < $count && (!$event->isPast() || $displayPast))	{
				$eventOut->addEvent($event);
				$i++;
			}
			else if ($i >= $count) break;
		}
		return $eventOut;
	}
	/**
	 * createSequenceFromArrayTimeBounded
	 * 
	 * Creates an eventSequence with all events that are occuring between $startTime and $endTime.
	 * The strings should be in the format of a date, such as MM/DD/YY or MM/DD/YYYY or in the form of +X Hours or -X Hours
	 * 
	 * @param array $es
	 * @param string $startTime
	 * @param string $endTime
	 */
	public static function createSequenceFromArrayTimeBounded($eventArray, $startTime, $endTime)	{
		$eventSource = new eventSequence($eventArray);
		$eventOut = new eventSequence(array());
		foreach($eventSource->getList() as $event)	{
			if ($event->getStartTimestamp() >= $startTime
					&& $event->getEndTimestamp() <= $endTime)	{
				$eventOut->addEvent($event);
			}
		}
		$eventOut->setdisplayPast(false);
		return $eventOut;
	}
	/**
	 * createSequenceFromSequenceCountBounded
	 *
	 * Creates an eventSequence with the closest $count events
	 *
	 * @param eventSequence $es
	 * @param int $count
	 */
	public static function createSequenceFromSequenceCountBounded($eventArray, $count, $displayPast)	{
		$eventOut = new eventSequence(array());
		$i = 0;
		$eventOut->displayPast = $displayPast;
		$eventList = $eventArray->getList();
		foreach ($eventList as $event)	{
			if($i < $count && (!$event->isPast() || $displayPast))	{
				$eventOut->addEvent($event);
				$i++;
			}
			else if ($i >= $count) break;
		}
		return $eventOut;
	}
	/**
	 * createSequenceFromSequenceTimeBounded
	 *
	 * Creates an eventSequence with all events that are occuring between $startTime and $endTime.
	 * The strings should be in the format of a date, such as MM/DD/YY or MM/DD/YYYY or in the form of +X Hours or -X Hours
	 *
	 * @param eventSequence $es
	 * @param string $startTime
	 * @param string $endTime
	 */
	public static function createSequenceFromSequenceTimeBounded($eventArray, $startTime, $endTime)	{
		$eventOut = new eventSequence(array());
		foreach($eventArray->getList() as $event)	{
			if ($event->getStartTimestamp() >= strtotime($startTime)
					&& $event->getEndTimestamp() <= strtotime($endTime))	{
					$eventOut->addEvent($event);
			}
			
		}
		$eventOut->setdisplayPast(false);
		return $eventOut;
	}
	/**
	 * 
	 * setDisplayPast
	 * 
	 * sets the variable DisplayPast
	 * 
	 * @param boolean $bool
	 */
	function setDisplayPast($bool)	{
		$this->displayPast = $bool;
	}
	/**
	 * 
	 * toString
	 * 
	 * Returns a string containing the html to display all the events described by this eventSequence
	 * 
	 * @return string
	 */
	function toString()	{
		$str = "";
		foreach($this->eventList as $event)	{
			if (!$event->isPast() || $this->displayPast)	{
				$str .= $event->toString();
			}
		}
		return $str;
	}
	/**
	 * toArray
	 * 
	 * Returns an array containing all of the events described by this eventSequence
	 */
	function toArray()	{
		$retArray = array();
		foreach ($this->eventList as $event)	{
			array_push($retArray,$event->toArray());
		}
		return $retArray;
	}
	/**
	 * getList
	 * 
	 * returns the internal eventList
	 * 
	 * @return array
	 */
	function getList()	{
		return $this->eventList;
	}
	function addEvent($event)	{
		array_push($this->eventList,$event);
	}
	/**
	 *
	 * doSort
	 *
	 * Actual sort function used by uasort. Sorts by date and time.
	 *
	 * @param event $a
	 * @param event $b
	 * @return number
	 */
	public static function doSort($a,$b)	{
		if ($a->getStartTimestamp() == $b->getStartTimestamp()) return 0;
		if ($a->getStartTimestamp() > $b->getStartTimestamp())	{
			return 1;
		}
		else	{
			return -1;
		}
	}
}

?>
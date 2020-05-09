<?php

namespace Cissee\Webtrees\Module\ResearchSuggestions;

use Fisharebest\Webtrees\Source;
use Vesta\Model\GedcomDateInterval;

class SourceEvent {

	private $source;
	private $eventTypes;
	private $interval;
	private $place;

  public function getSource() {
		return $this->source;
	}
  
	public function getSourceXref() {
		return $this->source->xref();
	}
	
	public function getEventTypes() {
		return $this->eventTypes;
	}

	public function getInterval() {
		return $this->interval;
	}
	
	public function getPlace() {
		return $this->place;
	}

	//note: in Gedcom, DATE and PLAC are optional. Here, they are required. 
  public function __construct(Source $source, $eventTypes, GedcomDateInterval $interval, $place) {
  	$this->source = $source;
		$this->eventTypes = $eventTypes;
		$this->interval = $interval;
		$this->place = $place;
	}
}

<?php

namespace Cissee\Webtrees\Module\ResearchSuggestions;

use Fisharebest\Webtrees\Source;
use Vesta\Model\GedcomDateInterval;

class SourceEvent {

    private $source;
    private $eventTypes;
    private $interval;
    private $placeGedcomAsLevel2Tag;

    public function getSource(): Source {
        return $this->source;
    }

    public function getSourceXref(): string {
        return $this->source->xref();
    }

    public function getEventTypes(): array {
        return $this->eventTypes;
    }

    public function getInterval(): GedcomDateInterval {
        return $this->interval;
    }

    public function getPlaceGedcomAsLevel2Tag(): string {
        return $this->placeGedcomAsLevel2Tag;
    }

    //note: in Gedcom, DATE and PLAC are optional. Here, they are required. 
    public function __construct(
        Source $source,
        array $eventTypes,
        GedcomDateInterval $interval,
        string $placeGedcomAsLevel2Tag) {

        $this->source = $source;
        $this->eventTypes = $eventTypes;
        $this->interval = $interval;
        $this->placeGedcomAsLevel2Tag = $placeGedcomAsLevel2Tag;
    }

}

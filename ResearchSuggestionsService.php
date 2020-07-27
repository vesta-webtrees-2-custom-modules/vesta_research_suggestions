<?php

namespace Cissee\Webtrees\Module\ResearchSuggestions;

use Cissee\WebtreesExt\VirtualFact;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\GedcomTag;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Services\SearchService;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
use Vesta\Hook\HookInterfaces\FunctionsPlaceUtils;
use Vesta\Model\GedcomDateInterval;
use Vesta\Model\PlaceStructure;
use function collect;
use function route;

class ResearchSuggestionsService {

  protected $module;
  protected $searchService;
  
  public const BIRT_GROUPED_FACTS = ['BIRT','CHR','BAPM'];
  public const DEAT_GROUPED_FACTS = ['DEAT','BURI','CREM'];
  
  public function __construct(
          $module,
          SearchService $searchService) {
    
    $this->module = $module;
    $this->searchService = $searchService;
  }
  
  public function routeSelect2Source(Tree $tree, string $gedcom) {
    return route('module', [
        'module' => $this->module->name(),
        'action' => 'Select2Source',
        'tree' => $tree->name(),
        'gedcom' => $gedcom,
    ]);
  }
          
  //impl overlaps with getAdditionalFacts - should be cleaned up!
  /**
   * 
   * @param Fact $fact
   * @param Tree $tree
   * @param bool $ignorePartialRanges
   * 
   * @return Collection<SourceEvent>
   */
  public function getSourceSuggestions(
          Fact $fact,
          Tree $tree,
          bool $ignorePartialRanges = false): Collection {
    
    $collection = new Collection();
    
    $factWithPlace = null;
		$interval = null;
				
		$place = $fact->attribute("PLAC");
		if ($place) {
						
			$date = $fact->attribute("DATE");
			if ($date) {          
				$currentInterval = GedcomDateInterval::create($fact->attribute("DATE"), $ignorePartialRanges);
				//intersect to min common interval - 
				//this is to avoid bogus recommendations for fact combinations such as
				//BIRT 01 JAN 1715
				//BAPM AFT 01 JAN 1715 -> matches e.g. 1715 - 1850, even if meaning here is "shortly after BIRT"
				//(if option 'ignore partial ranges' isn't checked)
				//
				//on the other hand, if we actually have different dates,
				//expand to interval
				if ($interval !== null) {
					$intersected = $interval->intersect($currentInterval);
					if ($intersected !== null) {
						$interval = $intersected;
					} else {
						$interval = $interval->expand($currentInterval);
					}
				} else {
					$interval = $currentInterval;
				}
			
				$factWithPlace = $fact; 
			}
		}
			
		if ($interval === null) {
      return $collection;
    }
    
		$resolvedPlaces = array();
    if ($factWithPlace !== null) {
      $resolvedPlaces = array_merge(
        $resolvedPlaces,
        $this->resolvePlace(
                PlaceStructure::fromFactWithExplicitInterval($factWithPlace, $interval), 
                ['POLI','RELI']));
    }

    //(TODO: handle BAPM/CHR confusion)
    $events = $this->getSourceEvents($tree, [$fact->getTag()], $resolvedPlaces);

    foreach ($events as $event) {
      $match = $interval->intersect($event->getInterval());
      if ($match !== null) {
        $collection->push($event);
      }			
    }
    
    return $collection;
  }
  
  public function resolvePlace(
          PlaceStructure $ps, 
          array $typesOfLocation): array {
    
    //error_log("resolve: " . $placeName);
    
		$resolved = new Collection();
    //$ps = PlaceStructure::create("2 PLAC " . $placeName, $tree, null, $dateInterval->toGedcomString(2));
    
    //add place itself!
    $resolved->put($ps->getGedcomName(), $ps);
    
    //resolve via hook
    $parents = FunctionsPlaceUtils::placPplac($this->module, $ps, new Collection($typesOfLocation));

    foreach ($parents as $parentPs) {
      //error_log("resolved: " . $parentPs->getGedcomName());
      //error_log("at level: " . $parentPs->getLevel());
      $resolved->put($parentPs->getGedcomName(), $parentPs);
    }
    
		return $resolved
            ->sort(PlaceStructure::sorterByLevel())
            ->map(function (PlaceStructure $ps): string {
                    return $ps->getGedcomName();
                })
            ->toArray();
	}
    		
	public function getAdditionalFacts(
          Individual $person, 
          $ignorePartialRanges = false, 
          $tags = null) {
    
    $birtTags = explode(',', $this->module->getPreference('BIRT_GROUPED_FACTS', implode(',',self::BIRT_GROUPED_FACTS)));
    //intersect because we want to have a specific order!
    $birtTags = array_intersect(self::BIRT_GROUPED_FACTS, $birtTags);
    
    $deatTags = explode(',', $this->module->getPreference('DEAT_GROUPED_FACTS', implode(',',self::DEAT_GROUPED_FACTS)));
    //intersect because we want to have a specific order!
    $deatTags = array_intersect(self::DEAT_GROUPED_FACTS, $deatTags);
    
		$facts = array();

		//it is a feature that even for exact dates (e.g. exact BIRT date and exact CHR date), 
		//we get an interval like 'between October 13, 1784 and October 17, 1784'.
		//This is useful even if the source provides e.g. CHR only, 
		//because some sources list CHRs under the BIRT year
		//(relevant if born in late December, and baptized early January),
		//so it's safer to include both dates.
		//(honestly it's mainly done because it's easier to implement though) 
		//
		//TODO recheck
		
		//1. research suggestion for birth/christening?
    
		if (!empty($birtTags) && (($tags === null) || (array_intersect($tags, $birtTags)))) {
			//we define not to require any if there is at least one sourced event.
			//also, we cannot provide a suggestion if there is no event of the respective type (because in that case we do not have date & place)
			
			$factsWithPlace = array();
			$interval = null;
			$isSourced = false;
			foreach ($person->facts() as $fact) {
				if (!in_array($fact->getTag(), $birtTags)) {
					continue;
				}
				if ($fact->attribute("SOUR")) {
					$isSourced = true;
					break;
				}
				
				$place = $fact->attribute("PLAC");
				if ($place) {						
					$date = $fact->attribute("DATE");
					if ($date) {
						$currentInterval = GedcomDateInterval::create($fact->attribute("DATE"), $ignorePartialRanges);
						//intersect to min common interval - 
						//this is to avoid bogus recommendations for fact combinations such as
						//BIRT 01 JAN 1715
						//BAPM AFT 01 JAN 1715 -> matches e.g. 1715 - 1850, even if meaning here is "shortly after BIRT"
						//(if option 'ignore partial ranges' isn't checked)
						//
						//on the other hand, if we actually have different dates,
						//expand to interval
						if ($interval !== null) {
							$intersected = $interval->intersect($currentInterval);
							if ($intersected !== null) {
								$interval = $intersected;
							} else {
								$interval = $interval->expand($currentInterval);
							}
						} else {
							$interval = $currentInterval;
						}
						
						$factsWithPlace[] = $fact; 
					}
				}
			}
			
			if ((!$isSourced) && ($interval !== null)) {
				$resolvedPlaces = array();
				foreach ($factsWithPlace as $factWithPlace) {
					$resolvedPlaces = array_merge(
									$resolvedPlaces,
									$this->resolvePlace(
                          PlaceStructure::fromFactWithExplicitInterval($factWithPlace, $interval), 
                          ['POLI','RELI']));
				}
				
				$events = $this->getSourceEvents($person->tree(), $birtTags, $resolvedPlaces);

				foreach ($events as $event) {
					$sourceId = $event->getSourceXref();
					$match = $interval->intersect($event->getInterval());
					if ($match !== null) {
						$asType = null;
						//'preferred' order
						foreach ($birtTags as $type) {
							if (in_array($type, $event->getEventTypes())) {
								$asType = $type;
								break;
							}
						}
						
            //TODO I18N
            $labels = collect($birtTags)
                    ->map(function (string $t) {
                      return GedcomTag::getLabel($t);
                    })->implode('/');
            
						$gedcom = "1 ".$asType." ". I18N::translate('Missing source for %1$s - Possible source:', $labels);

						//conceptually a bit nicer, but leads to ugly sorting of facts:
						//EVEN with date 'pulls' up other non-dated events, such as OCCU (cf Functions.sortFacts/Fact.compareType)
						//$gedcom = "1 EVEN Missing source for birth/christening - Possible source:";
						//$gedcom .= "\n2 TYPE Research Suggestion";
						
						//"assuming birth in the interval xy ..."
						$gedcom .= $match->toGedcomString(2);

						$gedcom .= "\n2 PLAC " . $event->getPlace();
						$gedcom .= "\n2 SOUR @" . $sourceId. "@";

						$research = new VirtualFact($gedcom, $person, 'research');
						$facts[] = $research;
					}//else unexpected, shouldn't have been returned!					
				}					
			}
		}

		//2. research suggestion for confirmation (even without event)?
		if (($tags === null) || (array_intersect($tags, ['CONF']))) {
			
			$factsWithPlace = array();
			$interval = null;
			$hasEvent = false;
			$isSourced = false;
			
			//2a. do we already have a CONF event?
			foreach ($person->facts() as $fact) {
				if ($fact->getTag() !== 'CONF') {
					continue;
				}
				
				$hasEvent = true;
				
				if ($fact->attribute("SOUR")) {
					$isSourced = true;
					break;
				}
				
				$place = $fact->attribute("PLAC");
				if ($place) {
					$date = $fact->attribute("DATE");
					if ($date) {
						$interval = GedcomDateInterval::create($fact->attribute("DATE"), $ignorePartialRanges);							
						$factsWithPlace[] = $fact;
					}
				}
				
				//there should be only one CONF event
				break;
			}
			
			if ((!$isSourced) && (!$hasEvent)) {
				//2b. extrapolate via birth/christening, if still alive
				//(assuming family didn't move in the meantime)
				
				//first get death date, if any
				$maxUntil = null;
				foreach ($person->facts() as $fact) {
					if (!in_array($fact->getTag(), self::DEAT_GROUPED_FACTS)) {
						continue;
					}
					
					if ($maxUntil === null) {
						$date = $fact->attribute("DATE");
						if ($date) {
							$maxUntil = GedcomDateInterval::create($fact->attribute("DATE"), $ignorePartialRanges);
						}		
					}
				}
				
				foreach ($person->facts() as $fact) {
					if (!in_array($fact->getTag(), self::BIRT_GROUPED_FACTS)) {
						continue;
					}

					$place = $fact->attribute("PLAC");
					if ($place) {
						$date = $fact->attribute("DATE");
						if ($date) {
							$currentInterval = GedcomDateInterval::create($fact->attribute("DATE"), $ignorePartialRanges);
							if ($interval !== null) {
								$intersected = $interval->intersect($currentInterval);
								if ($intersected !== null) {
									$interval = $intersected;
								} else {
									$interval = $interval->expand($currentInterval);
								}
							} else {
								$interval = $currentInterval;
							}

							$factsWithPlace[] = $fact; 
						}
					}
				}				
				
				if ($interval !== null) {
					//confirmation usually around easter
					//individuals aged 14 or almost 14
					//
					//simply use year of birth + 14/15 years
          $minAge = intval($this->module->getPreference('CONF_MIN_AGE', '13'));
          $maxAge = intval($this->module->getPreference('CONF_MIN_AGE', '14'));
					$interval = $interval->shiftYears($minAge+1, $maxAge+1);
					//still alive? else reset interval to null
					if ($maxUntil) {
						$interval = $interval->maxUntil($maxUntil);
					}
				}
			}
			
			if ((!$isSourced) && ($interval !== null)) {
				$resolvedPlaces = array();
				foreach ($factsWithPlace as $factWithPlace) {
					$resolvedPlaces = array_merge(
									$resolvedPlaces,
									$this->resolvePlace(
                          PlaceStructure::fromFactWithExplicitInterval($factWithPlace, $interval), 
                          ['POLI','RELI']));
				}
				
				$events = $this->getSourceEvents($person->tree(), ['CONF'], $resolvedPlaces);
				foreach ($events as $event) {
					$sourceId = $event->getSourceXref();
					$match = $event->getInterval()->intersect($interval);
					if ($match !== null) {
						
						if ($hasEvent) {
              $gedcom = "1 CONF ". I18N::translate('Missing source for %1$s - Possible source:', GedcomTag::getLabel('CONF'));
						} else {
              $gedcom = "1 CONF ". I18N::translate('Possible source:');
						}
						
						$gedcom .= $match->toGedcomString(2);

						$gedcom .= "\n2 PLAC " . $event->getPlace();
						$gedcom .= "\n2 SOUR @" . $sourceId. "@";

						$research = new VirtualFact($gedcom, $person, 'research');
						$facts[] = $research;
					}//else unexpected, shouldn't have been returned!					
				}
			}
		}
        
    //3a. research suggestion for other sourced individual events?
    $sour_indi_facts = 
            collect(explode(',',$person->tree()->getPreference('SOUR_DATA_EVEN_FACTS', 'BIRT,BAPM,CHR,CONF,MARR,DEAT,BURI')))
            ->intersect(array_keys(GedcomTag::getPicklistFacts('INDI')))
            ->filter(function (string $t) use ($birtTags, $deatTags): bool {
              return !in_array($t, $birtTags) && !in_array($t, $deatTags) && ($t !== 'CONF');
            });
            
            //cannot use ->except because that operates on collection keys!
    
    //3a. research suggestion for sourced family events?
    if (!empty($sour_indi_facts) && (($tags === null) || (array_intersect($tags, $sour_indi_facts)))) {
      foreach ($person->facts() as $fact) {
        if (!$sour_indi_facts->contains($fact->getTag())) {
            continue;
        }

        if ($fact->attribute("SOUR")) {
          continue;
        }

        $place = $fact->attribute("PLAC");
        if ($place) {
          $date = $fact->attribute("DATE");
          if ($date) {
            $interval = GedcomDateInterval::create($fact->attribute("DATE"), $ignorePartialRanges);
            $resolvedPlaces = $this->resolvePlace(
                    PlaceStructure::fromFactWithExplicitInterval($fact, $interval), 
                    ['POLI','RELI']);
            $events = $this->getSourceEvents($person->tree(), [$fact->getTag()], $resolvedPlaces);

            foreach ($events as $event) {
              $sourceId = $event->getSourceXref();
              $match = $interval->intersect($event->getInterval());
              if ($match !== null) {
                $gedcom = "1 ".$fact->getTag()." ". I18N::translate('Missing source for %1$s - Possible source:', GedcomTag::getLabel($fact->getTag()));

                //conceptually a bit nicer, but leads to ugly sorting of facts:
                //EVEN with date 'pulls' up other non-dated events, such as OCCU (cf Functions.sortFacts/Fact.compareType)
                //$gedcom = "1 EVEN Missing source for marriage - Possible source:";
                //$gedcom .= "\n2 TYPE Research Suggestion";

                $gedcom .= $match->toGedcomString(2);

                $gedcom .= "\n2 PLAC " . $event->getPlace();
                $gedcom .= "\n2 SOUR @" . $sourceId. "@";

                $research = new VirtualFact($gedcom, $person, 'research');
                $facts[] = $research;
              }//else unexpected, shouldn't have been returned!					
            }
          }	
        }
      }
		}
    
    $sour_fam_facts = 
            collect(explode(',',$person->tree()->getPreference('SOUR_DATA_EVEN_FACTS', 'BIRT,BAPM,CHR,CONF,MARR,DEAT,BURI')))
            ->intersect(array_keys(GedcomTag::getPicklistFacts('FAM')));

		//3b. research suggestion for sourced family events?
    if (!empty($sour_fam_facts) && (($tags === null) || (array_intersect($tags, $sour_fam_facts)))) {
			foreach ($person->spouseFamilies() as $family) {
				foreach ($family->facts() as $fact) {
          if (!$sour_fam_facts->contains($fact->getTag())) {
            	continue;
          }

					if ($fact->attribute("SOUR")) {
						continue;
					}

					$place = $fact->attribute("PLAC");
					if ($place) {
						$date = $fact->attribute("DATE");
						if ($date) {
							$interval = GedcomDateInterval::create($fact->attribute("DATE"), $ignorePartialRanges);
							$resolvedPlaces = $this->resolvePlace(
                      PlaceStructure::fromFactWithExplicitInterval($fact, $interval), 
                      ['POLI','RELI']);
							$events = $this->getSourceEvents($person->tree(), [$fact->getTag()], $resolvedPlaces);

							foreach ($events as $event) {
								$sourceId = $event->getSourceXref();
								$match = $interval->intersect($event->getInterval());
								if ($match !== null) {
                  $gedcom = "1 ".$fact->getTag()." ". I18N::translate('Missing source for %1$s - Possible source:', GedcomTag::getLabel($fact->getTag()));

									//conceptually a bit nicer, but leads to ugly sorting of facts:
									//EVEN with date 'pulls' up other non-dated events, such as OCCU (cf Functions.sortFacts/Fact.compareType)
									//$gedcom = "1 EVEN Missing source for marriage - Possible source:";
									//$gedcom .= "\n2 TYPE Research Suggestion";

									$gedcom .= $match->toGedcomString(2);

									$gedcom .= "\n2 PLAC " . $event->getPlace();
									$gedcom .= "\n2 SOUR @" . $sourceId. "@";

									$research = new VirtualFact($gedcom, $person, 'research');
									$facts[] = $research;
								}//else unexpected, shouldn't have been returned!					
							}
						}	
					}
				}
			}
		}	
		
		//4. research suggestion for death/burial?
    
		if (!empty($deatTags) && (($tags === null) || (array_intersect($tags, $deatTags)))) {
			//we define not to require any if there is at least one sourced event.
			//also, we cannot provide a suggestion if there is no event of the respective type (because in that case we do not have date & place)
			
			$factsWithPlace = array();
			$interval = null;
			$isSourced = false;
			foreach ($person->facts() as $fact) {
				if (!in_array($fact->getTag(), $deatTags)) {
					continue;
				}
				if ($fact->attribute("SOUR")) {
					$isSourced = true;
					break;
				}
				
				$place = $fact->attribute("PLAC");
				if ($place) {
					$date = $fact->attribute("DATE");
					if ($date) {
						$currentInterval = GedcomDateInterval::create($fact->attribute("DATE"), $ignorePartialRanges);
						
						//intersect to min common interval - 
						//this is to avoid bogus recommendations for fact combinations such as
						//BIRT 01 JAN 1715
						//BAPM AFT 01 JAN 1715 -> matches e.g. 1950 - 1850, even if meaning here is "shortly after BIRT"
						if ($interval !== null) {
							$intersected = $interval->intersect($currentInterval);
							if ($intersected !== null) {
								$interval = $intersected;
							} else {
								$interval = $interval->expand($currentInterval);
							}
						} else {
							$interval = $currentInterval;
						}
						
						$factsWithPlace[] = $fact; 
					}
				}
			}
			
			if ((!$isSourced) && ($interval !== null)) {
				$resolvedPlaces = array();
				foreach ($factsWithPlace as $factWithPlace) {
					$resolvedPlaces = array_merge(
									$resolvedPlaces,
									$this->resolvePlace(
                          PlaceStructure::fromFactWithExplicitInterval($factWithPlace, $interval), 
                          ['POLI','RELI']));
				}

				$events = $this->getSourceEvents($person->tree(), $deatTags, $resolvedPlaces);
				foreach ($events as $event) {
					$sourceId = $event->getSourceXref();
					$match = $event->getInterval()->intersect($interval);
					if ($match !== null) {
						$asType = null;
						//'preferred' order
						foreach ($deatTags as $type) {
							if (in_array($type, $event->getEventTypes())) {
								$asType = $type;
								break;
							}
						}
						$labels = collect($deatTags)
                    ->map(function (string $t) {
                      return GedcomTag::getLabel($t);
                    })->implode('/');
            
						$gedcom = "1 ".$asType." ". I18N::translate('Missing source for %1$s - Possible source:', $labels);

						$gedcom .= $match->toGedcomString(2);

						$gedcom .= "\n2 PLAC " . $event->getPlace();
						$gedcom .= "\n2 SOUR @" . $sourceId. "@";

						$research = new VirtualFact($gedcom, $person, 'research');
						$facts[] = $research;
					}//else unexpected, shouldn't have been returned!					
				}
			}
		}
		
		return $facts;
	}
    	
	public function getSourceEvents($tree, $matchEventTypes, $places) {
		return $this->doGetSourceEvents($tree, $matchEventTypes, $places);
	}

	/**
	 *	 	
	 * @return array (array of key: source id, value: SourceEvent)	 
	 */	 	
	protected function doGetSourceEvents($tree, $matchEventTypes, $places) {
		$events = array();
		
		$sources = array();
		foreach ($places as $eventPlace) {
			$sources2 = $this->searchService->searchSources(array($tree), array($eventPlace));
			foreach ($sources2 as $source) {
        
				//overwrite duplicate results
				$sources[$source->xref()] = $source;
			}
		}
		
		foreach ($sources as $xref => $source) {
			//collect EVEN (similar to FunctionsPrintFacts, with fix for issue #1376)
			preg_match_all('/\n2 EVEN (.*)((\n[3].*)*)/', $source->gedcom(), $evenMatches, PREG_SET_ORDER);
			foreach ($evenMatches as $evenMatch) {
				$eventTypeMatches = false;
				$eventTypes = array();
				foreach (preg_split('/ *, */', $evenMatch[1]) as $event) {
					$eventTypes[] = $event;
					if (in_array($event, $matchEventTypes)) {
						$eventTypeMatches = true;
					}
				}
				
				if ($eventTypeMatches) {
					$dateInterval = GedcomDateInterval::createEmpty();
					if (preg_match('/\n3 DATE (.+)/', $evenMatch[2], $date_match)) {
						$dateInterval = GedcomDateInterval::create($date_match[1]);
					}
					
					if (preg_match('/\n3 PLAC (.+)/', $evenMatch[2], $plac_match)) {
						$matchedPlace = $plac_match[1];
						
						//we no longer include these - potentially lead to large number of matches which aren't that useful:
						//do you really want lots of suggestions for main place "USA"?
						//"Iroquois, Illinois" is ok when looking for "Illinois" as main place
						//if (($matchedPlace === $place) || (preg_match('/' . Gedcom::PLACE_SEPARATOR . preg_quote($place) . '$/', $matchedPlace))) {
						
						foreach ($places as $eventPlace) {
							//"Iroquois, Illinois" is NOT ok when looking for "Illinois" as parent place
							if ($matchedPlace === $eventPlace) {
								$events[] = new SourceEvent($source, $eventTypes, $dateInterval, $matchedPlace);
								break;
							}
						}
					}
				}
			}
		}
		
		return $events;
	}
}

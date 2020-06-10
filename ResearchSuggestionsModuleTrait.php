<?php

namespace Cissee\Webtrees\Module\ResearchSuggestions;

use Fisharebest\Webtrees\GedcomTag;
use Fisharebest\Webtrees\I18N;
use Vesta\ControlPanelUtils\Model\ControlPanelCheckbox;
use Vesta\ControlPanelUtils\Model\ControlPanelFactRestriction;
use Vesta\ControlPanelUtils\Model\ControlPanelPreferences;
use Vesta\ControlPanelUtils\Model\ControlPanelRange;
use Vesta\ControlPanelUtils\Model\ControlPanelSection;
use Vesta\ControlPanelUtils\Model\ControlPanelSubsection;

trait ResearchSuggestionsModuleTrait {

  protected function getMainTitle() {
    return I18N::translate('Vesta Research Suggestions');
  }

  public function getShortDescription() {
    return I18N::translate('A module providing suggestions for additional research, based on available sources.');
  }

  protected function getFullDescription() {
    $description = array();
    $description[] = 
            /* I18N: Module Configuration */I18N::translate('A module providing suggestions for additional research, based on available sources.');
    $description[] = 
            /* I18N: Module Configuration */I18N::translate('Requires the \'%1$s Vesta Common\' module, and the \'%1$s Vesta Facts and events\' module.', $this->getVestaSymbol());
    
    /*
    <h4><?php echo I18N::translate('How to use this module') ?></h4>
		
		<p class="text-muted">
			<?php echo I18N::translate('Q: should I record the source as providing BIRT in addition to CHR (BAPM), if it gives birth dates in addition to christening dates?') ?>
		</p>
		<p class="text-muted">
			<?php echo I18N::translate('A: 1. No need to, births are still attempted to match. 2. Would advise against it conceptually, mainly depends on organization of source. If it\'s ordered by christening, and headlined accordingly, I wouldn\'t.') ?>
			<?php echo I18N::translate('also not: age of death as BIRT, randbemerkung etc') ?>
		</p>
    */
    
    return $description;
  }

  protected function createPrefs() {
    /*
    $generalSub = array();
    $generalSub[] = new ControlPanelSubsection(
            I18N::translate('Displayed title'),
            array(new ControlPanelCheckbox(                    
                I18N::translate('Include the %1$s symbol in the module title', $this->getVestaSymbol()),
                null,
                'VESTA',
                '1')));
    */
    
    $factsSub = array();
    $factsSub[] = new ControlPanelSubsection(
            /* I18N: Module Configuration */I18N::translate('Options'),
            array(new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Allow persistent toggle (user may show/hide research suggestions as additional facts)'),
                null,
                'TAB_TOGGLEABLE_RESEARCH',
                '1')));
    
    $factsSub[] = new ControlPanelSubsection(
            /* I18N: Module Configuration */I18N::translate('Grouped events'),
            array(
        new ControlPanelFactRestriction(
                array_intersect_key(GedcomTag::getPicklistFacts('INDI'), array_flip(ResearchSuggestionsService::BIRT_GROUPED_FACTS)),
                /* I18N: Module Configuration */I18N::translate('Events related to Birth. If there is a source for one of these events, no suggestions will be made for other events in this group. Note that strictly BAPM is not necessarily an event occuring shortly after Birth, but it is often used that way (when CHR would actually be more appropriate, according to the GEDCOM specification). If you only use one of CHR/BAPM, it\'s recommended to deselect the other one here.'),
                'BIRT_GROUPED_FACTS',
                implode(',',ResearchSuggestionsService::BIRT_GROUPED_FACTS)),
        new ControlPanelFactRestriction(
                array_intersect_key(GedcomTag::getPicklistFacts('INDI'), array_flip(ResearchSuggestionsService::DEAT_GROUPED_FACTS)),
                /* I18N: Module Configuration */I18N::translate('Events related to Death. If there is a source for one of these events, no suggestions will be made for other events in this group.'),
                'DEAT_GROUPED_FACTS',
                implode(',',ResearchSuggestionsService::DEAT_GROUPED_FACTS))));
    
    $factsSub[] = new ControlPanelSubsection(
            /* I18N: Module Configuration */I18N::translate('Age range for Confirmation events'),
            array(
        new ControlPanelRange(
                /* I18N: Module Configuration */I18N::translate('Minimal age in years'),
                null,
                10,
                20,
                'CONF_MIN_AGE',
                13),
        new ControlPanelRange(
                /* I18N: Module Configuration */I18N::translate('Maximal age in years'),
                /* I18N: Module Configuration */I18N::translate('Used to calculate date range for suggestions for Confirmation (CONF) events, based on birth or similar event (in case there is no explicit Confirmation event).'),
                10,
                20,
                'CONF_MAX_AGE',
                14)));
    
    $sections = array();
    /*
    $sections[] = new ControlPanelSection(
            I18N::translate('General'),
            null,
            $generalSub);
    */
    
    $sections[] = new ControlPanelSection(
            /* I18N: Module Configuration */I18N::translate('Facts and Events Tab Settings'),
            null,
            $factsSub);

    return new ControlPanelPreferences($sections);
  }
}

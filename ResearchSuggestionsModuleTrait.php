<?php

namespace Cissee\Webtrees\Module\ResearchSuggestions;

use Fisharebest\Webtrees\I18N;
use Vesta\ControlPanel\Model\ControlPanelCheckbox;
use Vesta\ControlPanel\Model\ControlPanelPreferences;
use Vesta\ControlPanel\Model\ControlPanelSection;
use Vesta\ControlPanel\Model\ControlPanelSubsection;

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
            /* I18N: Module Configuration */I18N::translate('A module providing suggestions for additional research, based on available sources.') . ' ' .
            /* I18N: Module Configuration */I18N::translate('...');
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
    $generalSub = array();
    $generalSub[] = new ControlPanelSubsection(
            /* I18N: Module Configuration */I18N::translate('Displayed title'),
            array(new ControlPanelCheckbox(                    
                /* I18N: Module Configuration */I18N::translate('Include the %1$s symbol in the module title', $this->getVestaSymbol()),
                null,
                'VESTA',
                '1')));

    $factsSub = array();
    $factsSub[] = new ControlPanelSubsection(
            /* I18N: Module Configuration */I18N::translate('Options'),
            array(new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Allow persistent toggle (user may show/hide research suggestions as additional facts)'),
                null,
                'TAB_TOGGLEABLE_RESEARCH',
                '1')));
    
    $sections = array();
    $sections[] = new ControlPanelSection(
            /* I18N: Module Configuration */I18N::translate('General'),
            null,
            $generalSub);

    $sections[] = new ControlPanelSection(
            /* I18N: Module Configuration */I18N::translate('Facts and Events Tab Settings'),
            null,
            $factsSub);

    return new ControlPanelPreferences($sections);
  }
}

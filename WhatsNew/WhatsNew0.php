<?php

namespace Cissee\Webtrees\Module\ResearchSuggestions\WhatsNew;

use Cissee\WebtreesExt\WhatsNew\WhatsNewInterface;
use Fisharebest\Webtrees\I18N;

class WhatsNew0 implements WhatsNewInterface {

  public function getMessage(): string {
    return I18N::translate("Vesta Research Suggestions: A new custom module. See the Readme for details.");
  }
}

<?php

namespace Cissee\Webtrees\Module\ResearchSuggestions;

use Cissee\Webtrees\Hook\HookInterfaces\EmptyIndividualFactsTabExtender;
use Cissee\Webtrees\Hook\HookInterfaces\IndividualFactsTabExtenderInterface;
use Cissee\WebtreesExt\AbstractModule;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Services\SearchService;
use Fisharebest\Webtrees\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Vesta\Model\GenericViewElement;
use Vesta\VestaModuleTrait;
use function app;


class ResearchSuggestionsModule extends AbstractModule implements 
  ModuleCustomInterface, 
  ModuleConfigInterface,
  IndividualFactsTabExtenderInterface {

  use ModuleCustomTrait, ModuleConfigTrait, VestaModuleTrait {
    VestaModuleTrait::customTranslations insteadof ModuleCustomTrait;
    VestaModuleTrait::customModuleLatestVersion insteadof ModuleCustomTrait;
    VestaModuleTrait::getAssetAction insteadof ModuleCustomTrait;
    VestaModuleTrait::assetUrl insteadof ModuleCustomTrait;
    
    VestaModuleTrait::getConfigLink insteadof ModuleConfigTrait;
  }
  
  use EmptyIndividualFactsTabExtender;
  use ResearchSuggestionsModuleTrait;

  public function customModuleAuthorName(): string {
    return 'Richard Cissée';
  }

  public function customModuleVersion(): string {
    return file_get_contents(__DIR__ . '/latest-version.txt');
  }

  public function customModuleLatestVersionUrl(): string {
    return 'https://raw.githubusercontent.com/vesta-webtrees-2-custom-modules/research_suggestions/master/latest-version.txt';
  }

  public function customModuleSupportUrl(): string {
    return 'https://cissee.de';
  }

  public function description(): string {
    return $this->getShortDescription();
  }

  public function onBoot(): void {
    app()->instance(ResearchSuggestionsService::class, new ResearchSuggestionsService(
            $this, 
            app(SearchService::class)));
    
    // Replace existing views with our own versions.
    View::registerCustomView('::components/select-source', $this->name() . '::components/select-source');
    
    $this->flashWhatsNew('\Cissee\Webtrees\Module\ResearchSuggestions\WhatsNew', 1);
  }
  
  public function postSelect2SourceAction(ServerRequestInterface $request): ResponseInterface {
    return app(Select2SourceWithSuggestions::class)->handle($request);
  }
  
  /**
   * Where does this module store its resources
   *
   * @return string
   */
  public function resourcesFolder(): string {
    return __DIR__ . '/resources/';
  }

  //IndividualFactsTabExtenderInterface
  
  public function hFactsTabGetOutputBeforeTab(Individual $person) {
    $pre = '<link href="' . $this->assetUrl('css/style.css') . '" type="text/css" rel="stylesheet" />';
		return new GenericViewElement($pre, '');
	}
  
  public function hFactsTabGetStyleadds() {
		$styleadds = array();
		$styleadds['research'] = 'wt-research-fact-pfh collapse'; //see style.css, and hFactsTabGetOutputInDBox
		return $styleadds;
	}

  public function hFactsTabGetOutputInDBox(Individual $person) {
		$toggleableResearch = boolval($this->getPreference('TAB_TOGGLEABLE_RESEARCH', '1'));	
		return $this->getOutputInDescriptionBox($toggleableResearch, 'show-research-suggestions-factstab', 'wt-research-fact-pfh', 'Research Suggestions');
	}
  
  protected function getOutputInDescriptionBox(bool $toggleableRels, string $id, string $targetClass, string $label) {
    ob_start();
    if ($toggleableRels) {
      ?>
      <label>
          <input id="<?php echo $id; ?>" type="checkbox" data-toggle="collapse" data-target=".<?php echo $targetClass; ?>">
          <?php echo I18N::translate($label); ?>
      </label>
      <?php
    }
    
    return new GenericViewElement(ob_get_clean(), '');
  }
  
  public function hFactsTabGetOutputAfterTab(Individual $person) {
    $toggleableResearch = boolval($this->getPreference('TAB_TOGGLEABLE_RESEARCH', '1'));
    return $this->getOutputAfterTab($toggleableResearch, 'show-research-suggestions-factstab');
  }
  
  protected function getOutputAfterTab($toggleableRels, $toggle) {
    $post = "";

    if ($toggleableRels) {
      $post = $this->getScript($toggle);
    }

    return new GenericViewElement('', $post);
  }

  protected function getScript($toggle) {
    ob_start();
    ?>
    <script>
      persistent_toggle("<?php echo $toggle; ?>");
    </script>
    <?php
    return ob_get_clean();
  }
  
  public function hFactsTabGetAdditionalFacts(Individual $person) {
		//TODO make this configurable! here and elsewhere!
		$ignorePartialRanges = true;

		return app(ResearchSuggestionsService::class)->getAdditionalFacts($person, $ignorePartialRanges);
	}
}

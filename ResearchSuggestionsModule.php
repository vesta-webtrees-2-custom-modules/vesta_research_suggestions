<?php

namespace Cissee\Webtrees\Module\ResearchSuggestions;

use Aura\Router\Route;
use Cissee\Webtrees\Hook\HookInterfaces\EmptyIndividualFactsTabExtender;
use Cissee\Webtrees\Hook\HookInterfaces\IndividualFactsTabExtenderInterface;
use Cissee\WebtreesExt\AbstractModule;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Services\SearchService;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Vesta\Model\GenericViewElement;
use Vesta\VestaModuleTrait;
use function app;


class ResearchSuggestionsModule extends AbstractModule implements 
  ModuleCustomInterface, 
  ModuleConfigInterface,
  ModuleGlobalInterface, 
  MiddlewareInterface,
  IndividualFactsTabExtenderInterface {

  use ModuleCustomTrait, ModuleConfigTrait, ModuleGlobalTrait, VestaModuleTrait {
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
    return 'https://raw.githubusercontent.com/vesta-webtrees-2-custom-modules/vesta_research_suggestions/master/latest-version.txt';
  }

  public function customModuleSupportUrl(): string {
    return 'https://cissee.de';
  }

  public function resourcesFolder(): string {
    return __DIR__ . '/resources/';
  }
  
  public function onBoot(): void {
    app()->instance(ResearchSuggestionsService::class, new ResearchSuggestionsService(
            $this, 
            app(SearchService::class)));
    
    // Replace existing views with our own versions.
    View::registerCustomView('::components/select-source', $this->name() . '::components/select-source');

    //TODO Issue #2
    // Replace existing views with our own versions.
    View::registerCustomView('::cards/add-sour-data-even', $this->name() . '::cards/add-sour-data-even');

    //TODO Issue #2
    // Replace existing views with our own versions.
    View::registerCustomView('::admin/trees-preferences', $this->name() . '::admin/trees-preferences');
    View::registerCustomView('::admin/trees-preferences-ext', $this->name() . '::admin/trees-preferences-ext');
    
    $this->flashWhatsNew('\Cissee\Webtrees\Module\ResearchSuggestions\WhatsNew', 1);
  }
  
  public function postSelect2SourceAction(ServerRequestInterface $request): ResponseInterface {
    return app(Select2SourceWithSuggestions::class)->handle($request);
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
		return $this->getOutputInDescriptionBox($toggleableResearch, 'show-research-suggestions-factstab', 'wt-research-fact-pfh', I18N::translate('Research Suggestions'));
	}
  
  protected function getOutputInDescriptionBox(bool $toggleableResearch, string $id, string $targetClass, string $label) {
    ob_start();
    if ($toggleableResearch) {
      ?>
      <label>
          <input id="<?php echo $id; ?>" type="checkbox" data-toggle="collapse" data-target=".<?php echo $targetClass; ?>">
          <?php echo $label; ?>
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
  
  //TODO Issue #2
  public function bodyContent(): string {
    $script = "<script>";
    $script .= "$(document).ready(function() { ";
    $script .= "$('select.select2ordered').select2({ ";
    // Needed for elements that are initially hidden.    
    $script .= "  width: '100%'";  
    $script .= "}); ";
    
    $script .= "$('select.select2ordered').on('select2:select', function (evt) { ";
    
    //preserve insertion order, see https://github.com/select2/select2/issues/3106
    //unfortunately this also affects dropdown order - ugly!
    $script .= "  var id = evt.params.data.id; ";
    $script .= "  var option = $(evt.target).children('[value='+id+']'); ";
    $script .= "  option.detach(); ";
    $script .= "  $(evt.target).append(option).change(); ";
    
    //update actual value
    $script .= "  var idRefSelector = '#' + $(evt.target).attr('id') + '_REF'; ";
    $script .= "  console.log('append: ' + id); ";
    $script .= "  updated = $(idRefSelector).val().split(',').filter(function(item){return item}); ";
    $script .= "  updated.push(id); ";
    $script .= "  $(idRefSelector).val(updated.join()); ";
    $script .= "}); ";
    
    $script .= "$('select.select2ordered').on('select2:unselect', function (evt) { ";
    $script .= "  var id = evt.params.data.id; ";
    
    //we should re-order back to original position here (in dropdown)!
    
    //update actual value
    $script .= "  var idRefSelector = '#' + $(evt.target).attr('id') + '_REF'; ";
    $script .= "  console.log('remove: ' + id); ";
    $script .= "  updated = $(idRefSelector).val().split(','); ";
    $script .= "  updated = updated.filter(function(item){return item !== id}); ";
    $script .= "  $(idRefSelector).val(updated.join()); ";
    $script .= "}); ";
    
    $script .= "}); ";
    $script .= "</script>";
    return $script;
  }
  
  //TODO Issue #2
  public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
    $route = $request->getAttributes()['route'];
    assert($route instanceof Route);
    
    //error_log(print_r($route, true));
        
    if ($route->handler === 'AdminTreesController::preferencesUpdate') {
      $this->preferencesUpdateExt($request);
    }
    
    // Generate the response.
    return $handler->handle($request);
  }
  
  public function preferencesUpdateExt(ServerRequestInterface $request) {
    $tree = $request->getAttribute('tree');
    assert($tree instanceof Tree);

    $params = (array) $request->getParsedBody();    
    $tree->setPreference('SOUR_DATA_EVEN_FACTS', implode(',', $params['SOUR_DATA_EVEN_FACTS'] ?? []));
  }
}

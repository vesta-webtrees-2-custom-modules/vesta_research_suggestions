<?php

namespace Cissee\Webtrees\Module\ResearchSuggestions;

use Aura\Router\Route;
use Cissee\WebtreesExt\AbstractModule;
use Cissee\WebtreesExt\Elements\EventsRecordedExt;
use Cissee\WebtreesExt\Elements\XrefSourceExt;
use Cissee\WebtreesExt\Module\ModuleMetaInterface;
use Cissee\WebtreesExt\Module\ModuleMetaTrait;
use Cissee\WebtreesExt\MoreI18N;
use Fisharebest\Webtrees\Elements\Marriage;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\RequestHandlers\TreePreferencesAction;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\SearchService;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Vesta\Hook\HookInterfaces\EmptyIndividualFactsTabExtender;
use Vesta\Hook\HookInterfaces\IndividualFactsTabExtenderInterface;
use Vesta\Model\GenericViewElement;
use Vesta\VestaModuleTrait;
use function app;
use function view;


class ResearchSuggestionsModule extends AbstractModule implements
    ModuleCustomInterface,
    ModuleMetaInterface,
    ModuleConfigInterface,
    ModuleGlobalInterface,
    MiddlewareInterface,
    IndividualFactsTabExtenderInterface {

    use ModuleCustomTrait, ModuleMetaTrait, ModuleConfigTrait, ModuleGlobalTrait, VestaModuleTrait {
        VestaModuleTrait::customTranslations insteadof ModuleCustomTrait;
        VestaModuleTrait::getAssetAction insteadof ModuleCustomTrait;
        VestaModuleTrait::assetUrl insteadof ModuleCustomTrait;
        VestaModuleTrait::getConfigLink insteadof ModuleConfigTrait;
        ModuleMetaTrait::customModuleVersion insteadof ModuleCustomTrait;
        ModuleMetaTrait::customModuleLatestVersion insteadof ModuleCustomTrait;
    }

    use EmptyIndividualFactsTabExtender;
    use ResearchSuggestionsModuleTrait;

    public function customModuleAuthorName(): string {
        return 'Richard Cissée';
    }

    public function customModuleMetaDatasJson(): string {
        return file_get_contents(__DIR__ . '/metadata.json');
    }

    public function customModuleLatestMetaDatasJsonUrl(): string {
        return 'https://raw.githubusercontent.com/vesta-webtrees-2-custom-modules/vesta_research_suggestions/master/metadata.json';
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

        //define our 'pretty' routes
        //note: potentially problematic in case of name clashes;
        //webtrees isn't interested in solving this properly, see
        //https://www.webtrees.net/index.php/en/forum/2-open-discussion/33687-pretty-urls-in-2-x

        /*
        $router_container = app(RouterContainer::class);
        assert($router_container instanceof RouterContainer);
        $router = $router_container->getMap();
        */

        $router = Registry::routeFactory()->routeMap();

        $router->get(TomSelectSourceWithSuggestions::class, '/tree/{tree}/tom-select-source-with-suggestions', TomSelectSourceWithSuggestions::class);

        // Replace existing views with our own versions.
        //but not everywhere (i.e. not when merging records)
        //used via XrefSourceExt
        View::registerCustomView('::components/select-source-ext', $this->name() . '::components/select-source');

        // Replace existing views with our own versions.
        View::registerCustomView('::lists/sources-table', $this->name() . '::lists/sources-table');

        //TODO Issue #2
        // Replace existing views with our own versions.
        View::registerCustomView('::admin/trees-preferences', $this->name() . '::admin/trees-preferences');
        View::registerCustomView('::admin/trees-preferences-ext', $this->name() . '::admin/trees-preferences-ext');

        //TODO: handle this better!
        $ef = Registry::elementFactory();
        $ef->registerTags(['INDI:MARR' => new Marriage(MoreI18N::xlate('Marriage'))]);

        $ef->registerTags(['SOUR:DATA:EVEN' => new EventsRecordedExt(MoreI18N::xlate('Events'))]);

        //replace XrefSource everywhere
        $ef->registerTags([
            'FAM:*:SOUR'               => new XrefSourceExt(MoreI18N::xlate('Source citation')),
            'FAM:SOUR'                 => new XrefSourceExt(MoreI18N::xlate('Source citation')),
            'INDI:*:SOUR'              => new XrefSourceExt(MoreI18N::xlate('Source citation')),
            'INDI:SOUR'                => new XrefSourceExt(MoreI18N::xlate('Source citation')),
            'NOTE:SOUR'                => new XrefSourceExt(MoreI18N::xlate('Source citation')),
            'OBJE:SOUR'                => new XrefSourceExt(MoreI18N::xlate('Source citation')),
        ]);

        //Gedcom-L
        $ef->registerTags([
            'FAM:*:_ASSO:SOUR'                => new XrefSourceExt(MoreI18N::xlate('Source citation')),
            'INDI:*:_ASSO:SOUR'               => new XrefSourceExt(MoreI18N::xlate('Source citation')),
            '_LOC:EVEN:SOUR'                  => new XrefSourceExt(MoreI18N::xlate('Source citation')),
            '_LOC:NAME:SOUR'                  => new XrefSourceExt(MoreI18N::xlate('Source')),
            '_LOC:SOUR'                       => new XrefSourceExt(MoreI18N::xlate('Source')),
            '_LOC:TYPE:SOUR'                  => new XrefSourceExt(MoreI18N::xlate('Source')),
            '_LOC:_AIDN:SOUR'                 => new XrefSourceExt(MoreI18N::xlate('Source')),
            '_LOC:_DMGD:SOUR'                 => new XrefSourceExt(MoreI18N::xlate('Source')),
            '_LOC:_LOC:SOUR'                  => new XrefSourceExt(MoreI18N::xlate('Source')),
            '_LOC:_POST:SOUR'                 => new XrefSourceExt(MoreI18N::xlate('Source')),
        ]);

        //webtrees
        $ef->registerTags([
            'FAM:*:_ASSO:SOUR'            => new XrefSourceExt(MoreI18N::xlate('Source citation')),
            'INDI:*:_ASSO:SOUR'           => new XrefSourceExt(MoreI18N::xlate('Source citation')),
        ]);

        $this->flashWhatsNew('\Cissee\Webtrees\Module\ResearchSuggestions\WhatsNew', 1);
    }

    //IndividualFactsTabExtenderInterface

    public function hFactsTabGetOutputBeforeTab(
        GedcomRecord $record): GenericViewElement {

        if (sizeof($this->hFactsTabGetAdditionalFacts($record)) === 0) {
            return GenericViewElement::createEmpty();
        }

        $pre = '<link href="' . $this->assetUrl('css/style.css') . '" type="text/css" rel="stylesheet" />';
    return new GenericViewElement($pre, '');
    }

    public function hFactsTabGetStyleadds(
        GedcomRecord $record,
        Fact $fact): array {

    $styleadds = [];
        if ($fact->id() === 'research') {
            $styleadds['research'] = 'wt-research-fact-pfh collapse'; //see style.css, and hFactsTabGetOutputInDBox
        }
    return $styleadds;
    }

    public function hFactsTabGetOutputInDBox(
        GedcomRecord $record): GenericViewElement {

        if (sizeof($this->hFactsTabGetAdditionalFacts($record)) === 0) {
            return GenericViewElement::createEmpty();
        }

    $toggleableResearch = boolval($this->getPreference('TAB_TOGGLEABLE_RESEARCH', '1'));
    return $this->getOutputInDescriptionBox($toggleableResearch, 'show-research-suggestions-factstab', 'wt-research-fact-pfh', I18N::translate('Research Suggestions'));
    }

    protected function getOutputInDescriptionBox(
        bool $toggleableResearch,
        string $id,
        string $targetClass,
        string $label) {

        ob_start();
        if ($toggleableResearch) {
          ?>
          <label>
              <input id="<?php echo $id; ?>" type="checkbox" data-bs-toggle="collapse" data-bs-target=".<?php echo $targetClass; ?>" data-wt-persist="<?php echo $targetClass; ?>" autocomplete="off">
              <?php echo $label; ?>
          </label>
          <?php
        }

        return new GenericViewElement(ob_get_clean(), '');
    }

    public function hFactsTabGetOutputAfterTab(
        GedcomRecord $record,
        bool $ajax): GenericViewElement {

        if (!$ajax) {
            //nothing to do - in fact must not initialize twice!
            return GenericViewElement::createEmpty();
        }

        if (sizeof($this->hFactsTabGetAdditionalFacts($record)) === 0) {
            return GenericViewElement::createEmpty();
        }

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
          webtrees.persistentToggle(document.querySelector('#<?php echo $toggle; ?>'));
        </script>
        <?php
        return ob_get_clean();
    }

    public function hFactsTabGetAdditionalFacts(
        GedcomRecord $record) {

        $cacheKey = ResearchSuggestionsModule::class . '_hFactsTabGetAdditionalFacts_' . $record->tree()->id() . $record->xref();
        $ret = Registry::cache()->array()->remember($cacheKey, static function () use ($record): array {
            //TODO make this configurable! here and elsewhere!
            $ignorePartialRanges = true;

            return app(ResearchSuggestionsService::class)->getAdditionalFacts(
                $record,
                $ignorePartialRanges);
        });

    return $ret;
    }

    //TODO Issue #2
    public function bodyContent(): string {
        //we need additional javascript for tom select sources
        return view($this->name() . '::js/webtreesExt');
    }

    //TODO Issue #2
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler): ResponseInterface {

        $route = $request->getAttribute('route');
        assert($route instanceof Route);

        if ($route->handler === TreePreferencesAction::class) {
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

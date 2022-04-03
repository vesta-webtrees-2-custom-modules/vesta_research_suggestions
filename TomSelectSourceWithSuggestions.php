<?php

declare(strict_types=1);

namespace Cissee\Webtrees\Module\ResearchSuggestions;

use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Http\RequestHandlers\AbstractTomSelectHandler;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
use function app;
use function view;

/**
 * Autocomplete for sources.
 */
class TomSelectSourceWithSuggestions extends AbstractTomSelectHandler
{
        
    /**
     * Perform the search
     *
     * @param Tree   $tree
     * @param string $query
     * @param int    $offset
     * @param int    $limit
     * @param string $at
     *
     * @return Collection<int,array{text:string,value:string}>
     */
    protected function search(Tree $tree, string $query, int $offset, int $limit, string $at): Collection
    {
        //return suggested sources, if there are any
        
        // Create a dummy record          
        $dummy = Registry::individualFactory()->new(
            'xref',
            "0 @xref@ INDI\n1 DEAT Y\n",
            null,
            $tree);

        $fact = new Fact($query, $dummy, '');
        $sourceEvents = app(ResearchSuggestionsService::class)->getSourceSuggestions($fact, $tree);

        $results = $sourceEvents->map(static function (SourceEvent $source) use ($at): array {
              return [
                  'text'  => view('selects/source', ['source' => $source->getSource()]),
                  'value' => $at . $source->getSourceXref() . $at,
              ];
          });
          
        return $results;
    }
}

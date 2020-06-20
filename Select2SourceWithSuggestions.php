<?php

declare(strict_types=1);

namespace Cissee\Webtrees\Module\ResearchSuggestions;

use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Factory;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Services\SearchService;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use function app;
use function response;
use function view;

//adapted from Select2Source
class Select2SourceWithSuggestions {
    
     // For clients that request one page of data at a time.
    private const RESULTS_PER_PAGE = 20;

    /** @var SearchService */
    protected $search_service;

    public function __construct(
        SearchService $search_service
    ) {
        $this->search_service = $search_service;
    }

    //adapted from AbstractSelect2Handler
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $params = (array) $request->getParsedBody();
        $query  = $params['q'] ?? '';
        $at     = (bool) ($params['at'] ?? false);
        $atString = $at ? '@' : '';
        
        if (strlen($query) == 0) {
          //return suggested sources, if there are any          
          $gedcom = $request->getQueryParams()['gedcom'];

          // Create a dummy record
          
          //develop-branch
          /*
          $dummy = app(IndividualFactoryInterface::class)->new(
              'xref',
              "0 @xref@ INDI\n1 DEAT Y\n",
              null,
              $tree
          );          
           */
          
          $dummy = new Individual(
              'xref',
              "0 @xref@ INDI\n1 DEAT Y\n",
              null,
              $tree);

          $fact = new Fact($gedcom, $dummy, '');
          $sourceEvents = app(ResearchSuggestionsService::class)->getSourceSuggestions($fact, $tree);
          
          $results = $sourceEvents->map(static function (SourceEvent $source): array {
              return [
                  'id'    => $atString . $source->getSource()->xref() . $atString,
                  'text'  => view('selects/source', ['source' => $source->getSource()]),
                  'title' => ' ',
              ];
          });
          
        } else {
          $page = (int) ($params['page'] ?? 1);

          // Fetch one more row than we need, so we can know if more rows exist.
          $offset = ($page - 1) * self::RESULTS_PER_PAGE;
          $limit  = self::RESULTS_PER_PAGE + 1;

          // Perform the search.
          $results = $this->search($tree, $query, $offset, $limit, $atString);          
        }


        return response([
            'results'    => $results->slice(0, self::RESULTS_PER_PAGE)->all(),
            'pagination' => [
                'more' => $results->count() > self::RESULTS_PER_PAGE,
            ],
        ]);
    }
    
    /**
     * Perform the search
     *
     * @param Tree   $tree
     * @param string $query
     * @param int    $offset
     * @param int    $limit
     *
     * @return Collection<array<string,string>>
     */
    protected function search(Tree $tree, string $query, int $offset, int $limit, string $at): Collection
    {
        // Search by XREF
        $source = Factory::source()->make($query, $tree);

        if ($source instanceof Source) {
            $results = new Collection([$source]);
        } else {
            $results = $this->search_service->searchSourcesByName([$tree], [$query], $offset, $limit);
        }

        return $results->map(static function (Source $source) use ($at): array {
            return [
                'id'    => $at . $source->xref() . $at,
                'text'  => view('selects/source', ['source' => $source]),
                'title' => ' ',
            ];
        });
    }
}

<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Elements;

use Fisharebest\Webtrees\Elements\XrefSource;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateSourceModal;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use function e;
use function route;
use function trim;
use function view;

/**
 * XREF:SOUR := {Size=1:22}
 * A pointer to, or a cross-reference identifier of, a SOURce record.
 */
class XrefSourceExt extends XrefSource
{

    public function edit(string $id, string $name, string $value, Tree $tree): string
    {
        // Other applications create sources with text, rather than XREFs
        if ($value === '' || preg_match('/^@' . Gedcom::REGEX_XREF . '@$/', $value)) {
            //[RC] view adjusted
            $select = view('components/select-source-ext', [
                'id'     => $id,
                'name'   => $name,
                'source' => Registry::sourceFactory()->make(trim($value, '@'), $tree),
                'tree'   => $tree,
                'at'     => '@',
            ]);

            return
                '<div class="input-group">' .
                '<button class="btn btn-secondary" type="button" data-bs-toggle="modal" data-bs-backdrop="static" data-bs-target="#wt-ajax-modal" data-wt-href="' . e(route(CreateSourceModal::class, ['tree' => $tree->name()])) . '" data-wt-select-id="' . $id . '" title="' . I18N::translate('Create a source') . '">' .
                view('icons/add') .
                '</button>' .
                $select .
                '</div>';
        }

        return $this->editTextArea($id, $name, $value);
    }
}

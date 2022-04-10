<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Elements;

use Fisharebest\Webtrees\Elements\AbstractElement;
use Fisharebest\Webtrees\Elements\UnknownElement;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;
use function array_map;
use function explode;
use function implode;
use function strtoupper;
use function trim;
use function view;

class EventsRecordedExt extends AbstractElement
{
    protected const SUBTAGS = [
        'DATE' => '0:1',
        'PLAC' => '0:1',
    ];

    protected const EVENTS_RECORDED = [
        'INDI:ADOP',
        'INDI:BAPM',
        'INDI:BARM',
        'INDI:BASM',
        'INDI:BIRT',
        'INDI:BLES',
        'INDI:BURI',
        'INDI:CAST',
        'INDI:CHR',
        'INDI:CENS',
        'INDI:CHRA',
        'INDI:CONF',
        'INDI:CREM',
        'INDI:DEAT',
        'INDI:DSCR',
        'INDI:EDUC',
        'INDI:EMIG',
        'INDI:FCOM',
        'INDI:GRAD',
        'INDI:IDNO',
        'INDI:IMMI',
        'INDI:NATI',
        'INDI:NATU',
        'INDI:NCHI',
        'INDI:NMR',
        'INDI:OCCU',
        'INDI:ORDN',
        'INDI:PROB',
        'INDI:PROP',
        'INDI:RELI',
        'INDI:RESI',
        'INDI:RETI',
        'INDI:SSN',
        'INDI:TITL',
        'INDI:WILL',
        'FAM:ANUL',
        'FAM:DIV',
        'FAM:DIVF',
        'FAM:ENGA',
        'FAM:MARB',
        'FAM:MARC',
        'FAM:MARL',
        'FAM:MARS',
        'FAM:MARR',
    ];

    /**
     * Convert a value to a canonical form.
     *
     * @param string $value
     *
     * @return string
     */
    public function canonical(string $value): string
    {
        $value = strtoupper(strtr(parent::canonical($value), [' ' => ',']));

        while (str_contains($value, ',,')) {
            $value = strtr($value, [',,' => ',']);
        }

        return trim($value, ',');
    }

    /**
     * An edit control for this data.
     *
     * @param string $id
     * @param string $name
     * @param string $value
     * @param Tree   $tree
     *
     * @return string
     */
    public function edit(string $id, string $name, string $value, Tree $tree): string
    {
        $factory = Registry::elementFactory();

        //[RC] extended
        $filter = explode(',', $tree->getPreference('SOUR_DATA_EVEN_FACTS', 'BIRT,BAPM,CHR,CONF,MARR,DEAT,BURI'));
        $filter = array_combine($filter, $filter);
    
        $options = Collection::make(self::EVENTS_RECORDED)
            //[RC] extended
            ->filter(function (string $tag) use ($filter): bool {
                $key = explode(':', $tag)[1];
                return array_key_exists($key, $filter);
            })
            ->mapWithKeys(static function (string $tag) use ($factory): array {
                return [explode(':', $tag)[1] => $factory->make($tag)->label()];
            })
            ->sort()
            ->all();

        $id2 = Uuid::uuid4()->toString();

        // Our form element name contains "[]", and multiple selections would create multiple values.
        $hidden = '<input type="hidden" id="' . e($id) . '" name="' . e($name) . '" value="' . e($value) . '" />';
        // Combine them into a single value.
        $js = 'document.getElementById("' . $id2 . '").addEventListener("change", function () { document.getElementById("' . $id . '").value = Array.from(document.getElementById("' . $id2 . '").selectedOptions).map(x => x.value).join(","); });';

        return view('components/select', [
            'class'    => 'tom-select',
            'name'     => '',
            'id'       => $id2,
            'options'  => $options,
            'selected' => explode(',', strtr($value, [' ' => ''])),
        ]) . $hidden . '<script>' . $js . '</script>';
    }

    /**
     * Display the value of this type of element.
     *
     * @param string $value
     * @param Tree   $tree
     *
     * @return string
     */
    public function value(string $value, Tree $tree): string
    {
        $tags = explode(',', $this->canonical($value));

        $events = array_map(static function (string $tag): string {
            foreach (['INDI', 'FAM'] as $record_type) {
                $element = Registry::elementFactory()->make($record_type . ':' . $tag);

                if (!$element instanceof UnknownElement) {
                    return $element->label();
                }
            }

            return e($tag);
        }, $tags);

        return implode(I18N::$list_separator, $events);
    }
}

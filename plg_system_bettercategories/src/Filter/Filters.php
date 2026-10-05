<?php

/**
 * @package     Merserwis.Plugin
 * @subpackage  System.bettercategories
 *
 * Product filters of a store category page: price, Gridbox list fields, technical parameters read
 * from the product texts ("1000 V", "CAT IV", "IP67") and features found in the descriptions
 * ("Bluetooth", "True RMS"), plus "on sale" and "in stock". The visitor's choice is part of the
 * address (?f-producer=sonel,metrel&f-price=100..500), so filtered lists can be linked, paged and
 * sorted. The product cards, sorting and pagination stay Gridbox's own: the list is rendered again
 * with Gridbox's helpers for the matching products only.
 */

namespace Merserwis\Plugin\System\BetterCategories\Filter;

\defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;
use Merserwis\Plugin\System\BetterCategories\Extension\BetterCategories;

final class Filters
{
    public const TYPES     = ['price', 'field', 'param', 'feature', 'sale', 'stock'];
    public const POSITIONS = ['sidebar_left', 'sidebar_right', 'column_top', 'column_bottom', 'top', 'drawer', 'after_categories', 'before_item', 'after_item'];

    /** Unit symbol of each parameter kind. */
    public const UNITS = [
        'v' => 'V', 'a' => 'A', 'w' => 'W', 'va' => 'VA', 'hz' => 'Hz', 'ohm' => 'Ω', 'f' => 'F', 'c' => '°C', 'db' => 'dB',
        'lx' => 'lx', 'pa' => 'Pa', 'wh' => 'Wh', 'ah' => 'Ah', 'm' => 'm', 'rh' => '%RH', 'ms' => 'm/s', 'flow' => 'm³/h',
        'ppm' => 'ppm', 'wm2' => 'W/m²', 'cat' => '', 'ip' => '',
    ];

    /** Kinds shown with an SI prefix (1 kV, 200 GΩ); temperatures, decibels, humidity, categories and ratings never. */
    private const PREFIXED = ['v', 'a', 'w', 'va', 'hz', 'ohm', 'f', 'wh', 'ah', 'm'];

    /** URL prefix of every filter parameter (keeps them apart from Joomla's and Gridbox's own). */
    public const PREFIX = 'f-';

    /** At most this many values per filter are read from an address. */
    private const MAX_VALUES = 50;

    /** Seconds the text index (parameters, features) is kept; it is updated product by product anyway. */
    private const INDEX_MINUTES = 43200;

    private BetterCategories $plugin;

    private Registry $params;

    /** @var array<int, array>|null normalised filter definitions */
    private ?array $defs = null;

    public function __construct(BetterCategories $plugin, Registry $params)
    {
        $this->plugin = $plugin;
        $this->params = $params;
    }

    // ---------------------------------------------------------------- definitions

    /**
     * The filters of the settings, normalised. With an empty list: price and every list field of the store.
     *
     * @return array<int, array{id: string, key: string, type: string, label: string, field: int, dim: string, mode: string, match: string, logic: string, sort: string, collapsed: bool, features: array}>
     */
    public function definitions(int $appId): array
    {
        if ($this->defs !== null) {
            return $this->defs;
        }

        $rows = array_values(array_filter(array_map(fn ($row) => (array) $row, (array) $this->params->get('filters_list', [])), 'is_array'));
        if (!$rows) {
            $rows[] = ['type' => 'price'];
            foreach ($this->gridboxFields($appId) as $field) {
                if (in_array($field->field_type, ['select', 'radio', 'checkbox'], true)) {
                    $rows[] = ['type' => 'field', 'field' => (int) $field->id];
                }
            }
        }

        $fields = $this->gridboxFields($appId);
        $used   = [];
        $defs   = [];
        // a technical-parameter row with several parameters: one filter per parameter (title and
        // address name from the parameter; a title of its own only with a single parameter)
        $expanded = [];
        foreach ($rows as $row) {
            if (($row['type'] ?? '') !== 'param') {
                $expanded[] = $row;
                continue;
            }
            $dims = array_values(array_unique(array_filter(array_map(
                fn ($d) => $d === 'bar' ? 'pa' : (string) $d,
                is_array($row['dim'] ?? null) ? $row['dim'] : explode(',', (string) ($row['dim'] ?? 'v'))
            ), fn ($d) => array_key_exists($d, self::UNITS))));
            foreach ($dims as $dim) {
                $expanded[] = ['dim' => $dim] + (count($dims) > 1 ? ['label' => '', 'key' => ''] : []) + $row;
            }
        }
        $rows = $expanded;

        foreach ($rows as $i => $row) {
            $type = (string) ($row['type'] ?? '');
            if (!in_array($type, self::TYPES, true)) {
                continue;
            }
            $def = [
                'id'        => 'g' . $i,
                'type'      => $type,
                'label'     => trim((string) ($row['label'] ?? '')),
                'field'     => (int) ($row['field'] ?? 0),
                'dim'       => is_array($row['dim'] ?? null) ? (string) reset($row['dim']) : (string) ($row['dim'] ?? 'v'),
                'mode'      => ($row['mode'] ?? 'values') === 'range' ? 'range' : 'values',
                'match'     => in_array($row['match'] ?? 'max', ['max', 'min', 'any'], true) ? (string) ($row['match'] ?? 'max') : 'max',
                'logic'     => ($row['logic'] ?? '') === 'and' ? 'and' : (($row['logic'] ?? '') === 'or' ? 'or' : ($type === 'feature' ? 'and' : 'or')),
                'sort'      => in_array($row['sort'] ?? 'auto', ['auto', 'count', 'alpha'], true) ? (string) ($row['sort'] ?? 'auto') : 'auto',
                'collapsed' => !empty($row['collapsed']) && (string) $row['collapsed'] !== '0',
                // a parameter group appears when at least this many products of the category have it
                'minCount'  => max(1, (int) ($row['min_products'] ?? 2)),
                // only in these categories and their subcategories (empty = everywhere)
                'categories' => array_values(array_filter(array_map('intval', is_array($row['categories'] ?? null) ? $row['categories'] : explode(',', (string) ($row['categories'] ?? ''))))),
                'features'  => [],
                'options'   => [],
            ];

            if ($type === 'field') {
                $field = $fields[$def['field']] ?? null;
                if (!$field) {
                    continue;
                }
                $def['label']     = $def['label'] !== '' ? $def['label'] : (string) $field->label;
                $def['fieldType'] = (string) $field->field_type;
                // option key => [slug, title], in Gridbox's order
                $options = json_decode((string) $field->options);
                foreach ((array) ($options->items ?? []) as $item) {
                    if (is_object($item) && isset($item->key)) {
                        $title = trim((string) ($item->title ?? $item->key));
                        $def['options'][(string) $item->key] = [$this->slug($title, (string) $item->key, $def['options']), $title];
                    }
                }
            } elseif ($type === 'param') {
                if (!array_key_exists($def['dim'], self::UNITS)) {
                    continue;
                }
                if (in_array($def['dim'], ['cat', 'ip'], true)) {
                    $def['mode'] = 'values';
                }
                $def['label'] = $def['label'] !== '' ? $def['label'] : $this->text('filters_label_' . $def['dim'], strtoupper($def['dim']));
            } elseif ($type === 'feature') {
                foreach (preg_split('/\R/u', (string) ($row['features'] ?? '')) ?: [] as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }
                    [$label, $words] = array_pad(array_map('trim', explode('=', $line, 2)), 2, '');
                    $words = array_values(array_filter(array_map('trim', preg_split('/[,;]/u', $words !== '' ? $words : $label) ?: []), fn ($w) => $w !== '' && $w !== '*'));
                    if ($label === '' || !$words || count($def['features']) >= 100) {
                        continue;
                    }
                    $slug = $this->slug($label, 'f' . count($def['features']), array_column($def['features'], null, 'slug'));
                    $def['features'][$slug] = ['slug' => $slug, 'label' => $label, 'pattern' => $this->wordsPattern($words)];
                }
                if (!$def['features']) {
                    continue;
                }
                $def['label'] = $def['label'] !== '' ? $def['label'] : $this->text('filters_label_features', 'Features');
            } else {
                $def['label'] = $def['label'] !== '' ? $def['label'] : $this->text('filters_label_' . $type, ucfirst($type));
            }

            // the name in the address: the "URL name" setting, else the label, unique
            $key = OutputFilter::stringURLSafe(trim((string) ($row['key'] ?? '')) !== '' ? (string) $row['key'] : $def['label']);
            $key = $key !== '' ? substr($key, 0, 40) : $type;
            $base = $key;
            for ($n = 2; isset($used[$key]); $n++) {
                $key = $base . '-' . $n;
            }
            $used[$key] = true;
            $def['key'] = self::PREFIX . $key;
            $defs[]     = $def;
        }

        return $this->defs = $defs;
    }

    /** @return array<int, object> Gridbox fields of the app (id, label, field_type, options) */
    public function gridboxFields(int $appId): array
    {
        static $cache = [];
        if (isset($cache[$appId])) {
            return $cache[$appId];
        }
        $db    = $this->plugin->db();
        $query = $db->createQuery()
            ->select($db->quoteName(['id', 'label', 'field_type', 'options']))
            ->from($db->quoteName('#__gridbox_fields'))
            ->where($db->quoteName('app_id') . ' = ' . $appId)
            ->where($db->quoteName('field_type') . ' IN (' . implode(',', array_map([$db, 'quote'], ['select', 'radio', 'checkbox', 'text'])) . ')');
        try {
            $rows = $db->setQuery($query)->loadObjectList('id') ?: [];
        } catch (\Throwable $e) {
            $rows = [];
        }

        return $cache[$appId] = $rows;
    }

    /** Regular expression of a feature: any of the words, as whole words ("bluetooth", "true rms", "bezprzewod*"). */
    private function wordsPattern(array $words): string
    {
        $parts = [];
        foreach ($words as $word) {
            $open  = str_ends_with($word, '*');
            $word  = rtrim($word, '*');
            // spaces and hyphens inside a phrase match each other ("true rms" = "true-rms" = "TrueRMS")
            $body  = implode('[\s\-]*', array_map(fn ($p) => preg_quote($p, '/'), preg_split('/[\s\-]+/u', $word) ?: [$word]));
            $parts[] = $body . ($open ? '' : '(?![\p{L}\d])');
        }

        return '/(?<![\p{L}\d])(?:' . implode('|', $parts) . ')/iu';
    }

    private function slug(string $title, string $fallback, array $taken): string
    {
        $slug = OutputFilter::stringURLSafe($title);
        $slug = $slug !== '' ? substr($slug, 0, 40) : OutputFilter::stringURLSafe($fallback);
        $slug = $slug !== '' ? $slug : 'o' . substr(md5($title), 0, 6);
        $base = $slug;
        $taken = array_flip(array_map(fn ($o) => is_array($o) ? (string) ($o[0] ?? $o['slug'] ?? '') : (string) $o, $taken));
        for ($n = 2; isset($taken[$slug]); $n++) {
            $slug = $base . '-' . $n;
        }

        return $slug;
    }

    /** A site text: the setting, else the text of the site language, else the English default. */
    public function text(string $key, string $default): string
    {
        $value = trim((string) $this->params->get($key, ''));
        if ($value !== '') {
            return $value;
        }
        $lang = 'PLG_SYSTEM_BETTERCATEGORIES_SITE_' . strtoupper(preg_replace('/^filters_/', '', $key) ?? $key);
        $text = Text::_($lang);

        return $text !== $lang ? $text : $default;
    }

    // ---------------------------------------------------------------- the visitor's choice

    /**
     * Filter values of the request: "f-key=a,b" (also "f-key[]=a&f-key[]=b" of a plain form) and
     * ranges "f-key=10..500" (also "f-key-min" / "f-key-max").
     *
     * @return array<string, array{values?: string[], lo?: float|null, hi?: float|null}>
     */
    public function state(array $defs): array
    {
        $input = Factory::getApplication()->getInput();
        $state = [];
        foreach ($defs as $def) {
            $key = $def['key'];
            if ($this->isRange($def)) {
                $raw = $input->get($key, '', 'raw');
                $raw = is_array($raw) ? (string) reset($raw) : (string) $raw;
                [$lo, $hi] = array_pad(explode('..', $raw, 2), 2, '');
                if ($raw !== '' && !str_contains($raw, '..')) {
                    [$lo, $hi] = [$raw, $raw];
                }
                $lo = $this->typed($def, $lo !== '' ? $lo : (string) $input->getString($key . '-min', ''));
                $hi = $this->typed($def, $hi !== '' ? $hi : (string) $input->getString($key . '-max', ''));
                if ($lo !== null && $hi !== null && $lo > $hi) {
                    [$lo, $hi] = [$hi, $lo];
                }
                if ($lo !== null || $hi !== null) {
                    $state[$key] = ['lo' => $lo, 'hi' => $hi];
                }
                continue;
            }

            $raw    = $input->get($key, null, 'raw');
            $list   = is_array($raw) ? $raw : explode(',', (string) $raw);
            $values = [];
            foreach ($list as $value) {
                if (!is_scalar($value)) {
                    continue;
                }
                $value = preg_replace('/[^a-z0-9.\-]/', '', strtolower((string) $value));
                if ($value !== '' && count($values) < self::MAX_VALUES) {
                    $values[$value] = $value;
                }
            }
            if ($values) {
                $state[$key] = ['values' => array_values($values)];
            }
        }

        return $state;
    }

    public function isRange(array $def): bool
    {
        return $def['type'] === 'price' || ($def['type'] === 'param' && $def['mode'] === 'range');
    }

    /** A typed bound: a number, or for a parameter also a value with a unit of its kind ("16 bar", "2 l/min", "68 °F"). */
    private function typed(array $def, string $raw): ?float
    {
        $n = $this->number($raw);
        if ($n !== null || $def['type'] !== 'param' || trim($raw) === '') {
            return $n;
        }
        foreach (Params::query(substr($raw, 0, 40))['params'] as $p) {
            if ($p['dim'] === $def['dim']) {
                return (float) $p['lo'];
            }
        }

        return null;
    }

    /** A typed number: "1,5", "2.5k", "10 M", "-20" (null when not a number). */
    public function number(string $raw): ?float
    {
        $raw = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim($raw));
        if (!preg_match('/^(-?\d+(?:\.\d+)?)([kKMGmuµ]?)$/u', $raw, $m)) {
            return null;
        }
        $factor = ['' => 1, 'k' => 1e3, 'K' => 1e3, 'M' => 1e6, 'G' => 1e9, 'm' => 1e-3, 'u' => 1e-6, 'µ' => 1e-6][$m[2]] ?? 1;

        return (float) $m[1] * $factor;
    }

    /** Query string of a state ("f-a=x,y&f-b=1..5"), in the order of the filters. */
    public function queryString(array $defs, array $state): string
    {
        $parts = [];
        foreach ($defs as $def) {
            $s = $state[$def['key']] ?? null;
            if (!$s) {
                continue;
            }
            if (isset($s['values'])) {
                $parts[] = $def['key'] . '=' . implode(',', $s['values']);
            } else {
                $parts[] = $def['key'] . '=' . ($s['lo'] !== null ? $this->numberText($s['lo']) : '') . '..' . ($s['hi'] !== null ? $this->numberText($s['hi']) : '');
            }
        }

        return implode('&', $parts);
    }

    private function numberText(float $v): string
    {
        $s = rtrim(rtrim(sprintf('%.6F', $v), '0'), '.');

        return $s === '-0' ? '0' : $s;
    }

    // ---------------------------------------------------------------- products

    /**
     * The products Gridbox lists on the category page (the category and its subcategories, by the
     * primary category or the additional category map), with what the filters need.
     *
     * @return array<int, array{price: float|null, sale: bool, stock: bool, fields: array, params: array, features: array}>
     */
    public function products(int $appId, int $categoryId, array $categories, array $defs): array
    {
        $db    = $this->plugin->db();
        $lang  = Factory::getApplication()->getLanguage()->getTag();
        $query = $this->plugin->visibleProductsQuery()
            ->select(['p.id', 'p.saved_time', 'p.page_category', 'd.price', 'd.sale_price', 'd.variations', 'd.stock'])
            ->innerJoin($db->quoteName('#__gridbox_categories', 'c') . ' ON c.id = p.page_category')
            ->leftJoin($db->quoteName('#__gridbox_store_product_data', 'd') . ' ON d.product_id = p.id')
            ->where('p.app_id = ' . $appId)
            ->where('c.published = 1')
            ->where('c.language IN (' . $db->quote($lang) . ', ' . $db->quote('*') . ')')
            ->where('c.access IN (' . implode(',', $this->plugin->viewLevels()) . ')');

        if ($categoryId > 0) {
            $tree = $this->subtree($categories, $categoryId);
            $set  = implode(',', $tree);
            $query->where('(p.page_category IN (' . $set . ') OR p.id IN (SELECT pm.page_id FROM ' . $db->quoteName('#__gridbox_category_page_map', 'pm')
                . ' WHERE pm.category_id IN (' . $set . ')))');
        }

        $rows     = $db->setQuery($query)->loadObjectList() ?: [];
        $products = [];
        $sales    = $this->plugin->activeSales();
        $times    = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            if (isset($products[$id])) {
                continue;
            }
            [$price, $sale, $stock] = $this->priceInfo($row, $sales);
            $products[$id] = ['price' => $price, 'sale' => $sale, 'stock' => $stock, 'fields' => [], 'params' => [], 'features' => []];
            $times[$id]    = (string) $row->saved_time;
        }
        if (!$products) {
            return [];
        }

        $types = array_column($defs, 'type');
        if (in_array('field', $types, true)) {
            $this->addFieldValues($products, $defs);
        }
        if (in_array('param', $types, true) || in_array('feature', $types, true)) {
            $this->addTextIndex($products, $times, $appId, $defs);
        }

        return $products;
    }

    /** @return int[] the category and all its subcategories */
    private function subtree(array $categories, int $categoryId): array
    {
        $ids   = [$categoryId];
        $queue = [$categoryId];
        $guard = 0;
        while ($queue && $guard++ < 10000) {
            $parent = array_shift($queue);
            foreach ($categories as $cat) {
                if ($cat->parent === $parent && !in_array($cat->id, $ids, true)) {
                    $ids[]   = $cat->id;
                    $queue[] = $cat->id;
                }
            }
        }

        return $ids;
    }

    /**
     * Lowest price of a product (base or variation; the sale price, or the price after the first store
     * sale that applies, as Gridbox shows it), whether it is on sale and whether it is in stock.
     *
     * @return array{0: float|null, 1: bool, 2: bool}
     */
    private function priceInfo(object $row, array $sales): array
    {
        $id         = (int) $row->id;
        $saleCats   = $sales ? $this->plugin->categoryPath((int) $row->page_category) : [];
        $candidates = [['', (string) $row->price, (string) $row->sale_price, (string) $row->stock]];
        $variations = json_decode((string) $row->variations);
        if (is_object($variations)) {
            foreach ($variations as $key => $variation) {
                if (is_object($variation)) {
                    $candidates[] = [(string) $key, (string) ($variation->price ?? ''), (string) ($variation->sale_price ?? ''), (string) ($variation->stock ?? '')];
                }
            }
        }

        $min   = null;
        $sale  = false;
        $stock = false;
        foreach ($candidates as $i => [$variation, $price, $salePrice, $count]) {
            // the product's own stock counts only when it has no variations (Gridbox sells the variations then)
            if (($i > 0 || count($candidates) === 1) && (trim($count) === '' || (float) $count > 0)) {
                $stock = true;
            }
            if (!is_numeric($price) || (float) $price <= 0) {
                continue;
            }
            $value = is_numeric($salePrice) ? (float) $salePrice : $this->plugin->salePrice($sales, (float) $price, $id, $variation, $saleCats);
            if ($value < (float) $price - 1e-9) {
                $sale = true;
            }
            if ($value > 0 && ($min === null || $value < $min)) {
                $min = $value;
            }
        }

        return [$min, $sale, $stock];
    }

    /** Values of the Gridbox fields used by the filters (option slugs; text fields: the text itself). */
    private function addFieldValues(array &$products, array &$defs): void
    {
        $byField = [];
        foreach ($defs as $i => $def) {
            if ($def['type'] === 'field') {
                $byField[$def['field']][] = $i;
            }
        }
        $db    = $this->plugin->db();
        $ids   = array_keys($products);
        foreach (array_chunk($ids, 1000) as $chunk) {
            $query = $db->createQuery()
                ->select($db->quoteName(['page_id', 'field_id', 'value']))
                ->from($db->quoteName('#__gridbox_page_fields'))
                ->where($db->quoteName('page_id') . ' IN (' . implode(',', $chunk) . ')')
                ->where($db->quoteName('field_id') . ' IN (' . implode(',', array_map('intval', array_keys($byField))) . ')');
            try {
                $rows = $db->setQuery($query)->loadObjectList() ?: [];
            } catch (\Throwable $e) {
                $rows = [];
            }
            foreach ($rows as $row) {
                $fieldId = (int) $row->field_id;
                $value   = (string) $row->value;
                if ($value === '' || $value === '[]') {
                    continue;
                }
                foreach ($byField[$fieldId] ?? [] as $i) {
                    $def  = &$defs[$i];
                    $keys = $def['fieldType'] === 'checkbox' ? (array) (json_decode($value, true) ?: []) : [$value];
                    foreach ($keys as $key) {
                        $key = trim((string) $key);
                        if ($key === '') {
                            continue;
                        }
                        if (!isset($def['options'][$key])) {
                            if ($def['fieldType'] !== 'text') {
                                continue;
                            }
                            // a text field: every distinct text is an option
                            $def['options'][$key] = [$this->slug($key, 't' . count($def['options']), $def['options']), $key];
                        }
                        $products[(int) $row->page_id]['fields'][$fieldId][] = $def['options'][$key][0];
                    }
                    unset($def);
                }
            }
        }
        $this->defs = $defs;
    }

    /**
     * Technical parameters and features of every product, read from its title, introduction and
     * description. Kept in the cache per app and updated only for products saved since.
     */
    private function addTextIndex(array &$products, array $times, int $appId, array $defs): void
    {
        $patterns = [];
        foreach ($defs as $def) {
            foreach ($def['features'] as $slug => $feature) {
                $patterns[$def['id'] . ':' . $slug] = $feature['pattern'];
            }
        }
        ksort($patterns);

        $cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)
            ->createCacheController('output', ['defaultgroup' => 'plg_system_bettercategories', 'lifetime' => self::INDEX_MINUTES, 'caching' => true]);
        // the version of the reading rules is part of the key: a changed Params.php reads everything again
        $key   = md5('filter-index|' . BetterCategories::ASSET_VERSION . '|' . (int) @filemtime(__DIR__ . '/Params.php') . '|' . $appId . '|' . md5(json_encode($patterns)));
        $index = $cache->get($key);
        $index = is_array($index) ? $index : [];

        $stale = [];
        foreach ($times as $id => $time) {
            if (($index[$id]['t'] ?? null) !== $time) {
                $stale[] = $id;
            }
        }

        if ($stale) {
            $db = $this->plugin->db();
            foreach (array_chunk($stale, 100) as $chunk) {
                $query = $db->createQuery()
                    ->select($db->quoteName(['id', 'title', 'intro_text', 'params']))
                    ->from($db->quoteName('#__gridbox_pages'))
                    ->where($db->quoteName('id') . ' IN (' . implode(',', $chunk) . ')');
                foreach ($db->setQuery($query)->loadObjectList() ?: [] as $row) {
                    $text = $this->plainText((string) $row->title . "\n" . (string) $row->intro_text . "\n" . (string) $row->params);
                    $byDim = [];
                    foreach (Params::extract($text, false)['params'] as $p) {
                        if (count($byDim[$p['dim']] ?? []) < 60) {
                            $byDim[$p['dim']][] = [(float) $p['lo'], (float) $p['hi']];
                        }
                    }
                    $found = [];
                    foreach ($patterns as $name => $pattern) {
                        if (preg_match($pattern, $text)) {
                            $found[] = $name;
                        }
                    }
                    $index[(int) $row->id] = ['t' => $times[(int) $row->id], 'p' => $byDim, 'f' => $found];
                }
            }
            try {
                $cache->store($index, $key);
            } catch (\Throwable $e) {
            }
        }

        foreach ($products as $id => &$product) {
            $product['params'] = $index[$id]['p'] ?? [];
            foreach ($index[$id]['f'] ?? [] as $name) {
                $product['features'][$name] = true;
            }
        }
        unset($product);
    }

    /** Text of a product: tags removed (with a space in their place), entities decoded, spaces collapsed. */
    private function plainText(string $html): string
    {
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    // ---------------------------------------------------------------- matching and counts

    /**
     * The products matching the state, and for every filter its options with counts (counted over the
     * products matching all the other filters; an "all of" filter also its own choice).
     *
     * @return array{ids: int[], facets: array<string, array>, state: array}
     */
    public function apply(array $products, array $defs, array $state): array
    {
        $values = [];
        foreach ($defs as $def) {
            foreach ($products as $id => $product) {
                $values[$def['key']][$id] = $this->valuesOf($def, $product);
            }
        }

        // values no option has (an old link, a typed address) are dropped, as if not chosen
        foreach ($defs as $def) {
            $key = $def['key'];
            if (!isset($state[$key]['values'])) {
                continue;
            }
            $known = match ($def['type']) {
                'field'   => array_column($def['options'], 0),
                'feature' => array_map('strval', array_keys($def['features'])),
                'param'   => array_map('strval', array_merge([], ...array_values($values[$key]))),
                default   => ['1'],
            };
            $state[$key]['values'] = array_values(array_intersect($state[$key]['values'], $known));
            if (!$state[$key]['values']) {
                unset($state[$key]);
            }
        }

        $pass = [];
        foreach ($defs as $def) {
            $s = $state[$def['key']] ?? null;
            if (!$s) {
                continue;
            }
            $pass[$def['key']] = [];
            foreach ($products as $id => $product) {
                if ($this->matches($def, $s, $values[$def['key']][$id])) {
                    $pass[$def['key']][$id] = true;
                }
            }
        }

        $active = array_keys($pass);
        $ids    = [];
        foreach (array_keys($products) as $id) {
            $ok = true;
            foreach ($active as $key) {
                if (!isset($pass[$key][$id])) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $ids[] = $id;
            }
        }

        $facets = [];
        foreach ($defs as $def) {
            $own  = $def['key'];
            $base = [];
            foreach (array_keys($products) as $id) {
                $ok = true;
                foreach ($active as $key) {
                    if (($key !== $own || $def['logic'] === 'and') && !isset($pass[$key][$id])) {
                        $ok = false;
                        break;
                    }
                }
                if ($ok) {
                    $base[] = $id;
                }
            }
            $facets[$own] = $this->facet($def, $state[$own] ?? null, $base, $values[$own], $products);
        }

        return ['ids' => $ids, 'facets' => $facets, 'state' => $state];
    }

    /** The product's values for a filter: option slugs, or numbers / [lo, hi] ranges for a range filter. */
    private function valuesOf(array $def, array $product): array
    {
        switch ($def['type']) {
            case 'price':
                return $product['price'] !== null ? [$product['price']] : [];
            case 'sale':
                return $product['sale'] ? ['1'] : [];
            case 'stock':
                return $product['stock'] ? ['1'] : [];
            case 'field':
                return array_values(array_unique($product['fields'][$def['field']] ?? []));
            case 'feature':
                $out = [];
                foreach (array_keys($def['features']) as $slug) {
                    if (isset($product['features'][$def['id'] . ':' . $slug])) {
                        $out[] = $slug;
                    }
                }

                return $out;
            case 'param':
                $list = $product['params'][$def['dim']] ?? [];
                if (!$list) {
                    return [];
                }
                if ($def['match'] === 'max') {
                    $nums = [max(array_column($list, 1))];
                } elseif ($def['match'] === 'min') {
                    $nums = [min(array_column($list, 0))];
                } else {
                    if ($def['mode'] === 'range') {
                        return $list;
                    }
                    $nums = array_unique(array_merge(array_column($list, 0), array_column($list, 1)));
                }
                if ($def['mode'] === 'range') {
                    return array_values($nums);
                }

                return array_values(array_unique(array_map(fn ($n) => $this->valueSlug($n), $nums)));
        }

        return [];
    }

    private function valueSlug(float $n): string
    {
        $s = $this->numberText(round($n, 6));

        return str_replace('-', 'm', $s);
    }

    private function matches(array $def, array $s, array $values): bool
    {
        if (isset($s['values'])) {
            if ($def['logic'] === 'and') {
                return !array_diff($s['values'], $values);
            }

            return (bool) array_intersect($s['values'], $values);
        }

        $lo = $s['lo'] ?? null;
        $hi = $s['hi'] ?? null;
        if ($def['type'] === 'price') {
            // the visitor types prices in the currency shown: back to the store's base currency
            $rate = $this->rate();
            $lo   = $lo !== null ? $lo / $rate : null;
            $hi   = $hi !== null ? $hi / $rate : null;
        }
        $eps = fn (?float $v) => $v === null ? 0.0 : max(1e-9, abs($v) * 1e-6);
        foreach ($values as $v) {
            [$a, $b] = is_array($v) ? [(float) $v[0], (float) $v[1]] : [(float) $v, (float) $v];
            if (($lo === null || $b >= $lo - $eps($lo)) && ($hi === null || $a <= $hi + $eps($hi))) {
                return true;
            }
        }

        return false;
    }

    private function rate(): float
    {
        $rate = (float) ($this->plugin->storeCurrency()->rate ?? 1);

        return $rate > 0 ? $rate : 1.0;
    }

    /** Options (or the range) of one filter over the given products. */
    private function facet(array $def, ?array $selected, array $base, array $values, array $products): array
    {
        if ($this->isRange($def)) {
            $min = $max = null;
            foreach ($base as $id) {
                foreach ($values[$id] as $v) {
                    [$a, $b] = is_array($v) ? [(float) $v[0], (float) $v[1]] : [(float) $v, (float) $v];
                    $min = $min === null ? $a : min($min, $a);
                    $max = $max === null ? $b : max($max, $b);
                }
            }
            if ($def['type'] === 'price' && $min !== null) {
                $rate = $this->rate();
                $min *= $rate;
                $max *= $rate;
            }

            return ['range' => true, 'min' => $min, 'max' => $max, 'lo' => $selected['lo'] ?? null, 'hi' => $selected['hi'] ?? null, 'count' => count($base),
                'have' => count(array_filter($values))];
        }

        $counts = [];
        foreach ($base as $id) {
            foreach ($values[$id] as $slug) {
                $counts[$slug] = ($counts[$slug] ?? 0) + 1;
            }
        }
        $chosen = array_flip($selected['values'] ?? []);

        // every option: label, count, chosen
        $options = [];
        switch ($def['type']) {
            case 'field':
                foreach ($def['options'] as [$slug, $title]) {
                    $options[$slug] = $title;
                }
                break;
            case 'feature':
                foreach ($def['features'] as $slug => $feature) {
                    $options[$slug] = $feature['label'];
                }
                break;
            case 'sale':
            case 'stock':
                $options['1'] = $this->text('filters_label_' . $def['type'] . '_option', $def['type'] === 'sale' ? 'On sale only' : 'In stock only');
                break;
            case 'param':
                $all = [];
                foreach ($products as $id => $product) {
                    foreach ($values[$id] ?? [] as $slug) {
                        $all[$slug] = true;
                    }
                }
                $slugs = array_keys($all + $chosen);
                usort($slugs, fn ($a, $b) => (float) str_replace('m', '-', (string) $a) <=> (float) str_replace('m', '-', (string) $b));
                foreach ($slugs as $slug) {
                    $options[(string) $slug] = $this->formatValue($def['dim'], (float) str_replace('m', '-', (string) $slug));
                }
                break;
        }

        $out = [];
        foreach ($options as $slug => $label) {
            $slug  = (string) $slug;
            $count = $counts[$slug] ?? 0;
            $isOn  = isset($chosen[$slug]);
            if (!$isOn && !isset($counts[$slug]) && $def['type'] === 'field' && !$this->anyProductHas($values, $slug)) {
                // an option no product of this category has at all: never shown
                continue;
            }
            $out[$slug] = ['label' => $label, 'count' => $count, 'on' => $isOn];
        }

        if ($def['sort'] === 'count') {
            uasort($out, fn ($a, $b) => $b['count'] <=> $a['count'] ?: strnatcasecmp($a['label'], $b['label']));
        } elseif ($def['sort'] === 'alpha' || ($def['sort'] === 'auto' && $def['type'] === 'field' && ($def['fieldType'] ?? '') === 'text')) {
            uasort($out, fn ($a, $b) => strnatcasecmp($a['label'], $b['label']));
        }

        return ['range' => false, 'options' => $out, 'count' => count($base), 'have' => count(array_filter($values))];
    }

    private function anyProductHas(array $values, string $slug): bool
    {
        foreach ($values as $list) {
            if (in_array($slug, $list, true)) {
                return true;
            }
        }

        return false;
    }

    /** "1000 V", "2,5 kV", "200 GΩ", "-20 °C", "CAT IV", "IP67". */
    public function formatValue(string $dim, float $v): string
    {
        if ($dim === 'cat') {
            return 'CAT ' . (['', 'I', 'II', 'III', 'IV'][(int) $v] ?? (string) (int) $v);
        }
        if ($dim === 'ip') {
            return 'IP' . str_pad((string) (int) $v, 2, '0', STR_PAD_LEFT);
        }
        if ($dim === 'pa') {
            // pressure as written in HVAC: up to 10 kPa in Pa, then kPa, from 1 bar in bar
            $abs = abs($v);
            [$v, $unit] = $abs >= 1e5 ? [$v / 1e5, 'bar'] : ($abs >= 1e4 ? [$v / 1e3, 'kPa'] : [$v, 'Pa']);

            return str_replace('.', $this->decimalPoint(), rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.')) . "\u{00A0}" . $unit;
        }
        $prefix = '';
        if (in_array($dim, self::PREFIXED, true) && $v != 0.0) {
            $abs = abs($v);
            foreach ([[1e9, 'G'], [1e6, 'M'], [1e4, 'k']] as [$f, $p]) {
                if ($abs >= $f) {
                    $v /= ($p === 'k' ? 1e3 : $f);
                    $prefix = $p;
                    break;
                }
            }
            if ($prefix === '' && $abs < 1) {
                foreach ([[1e-3, 'm'], [1e-6, 'µ'], [1e-9, 'n'], [1e-12, 'p']] as [$f, $p]) {
                    if ($abs >= $f * 0.9999) {
                        $v /= $f;
                        $prefix = $p;
                        break;
                    }
                }
            }
        }
        $text = rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
        $text = str_replace('.', $this->decimalPoint(), $text);
        $unit = self::UNITS[$dim] ?? '';

        return $text . ($unit !== '' ? "\u{00A0}" . $prefix . $unit : '');
    }

    private function decimalPoint(): string
    {
        $tag = Factory::getApplication()->getLanguage()->getTag();

        return str_starts_with($tag, 'en') || str_starts_with($tag, 'zh') || str_starts_with($tag, 'hi') ? '.' : ',';
    }
}

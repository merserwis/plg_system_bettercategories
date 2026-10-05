<?php

/**
 * @package     Merserwis.Plugin
 * @subpackage  System.bettercategories
 *
 * Filters on a store category page: the panel (a GET form, so it works without JavaScript too),
 * the bar above the products (total, chosen filters as removable chips, the button of the drawer)
 * and the product list of Gridbox rendered again for the matching products.
 */

namespace Merserwis\Plugin\System\BetterCategories\Filter;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use Merserwis\Plugin\System\BetterCategories\Extension\BetterCategories;

final class Page
{
    /** Where the list of subcategories goes when it is placed "above the products" (an element: Gridbox's minifying drops comments). */
    public const MARK = '<span class="bcf-mark" hidden></span>';

    /** The start of MARK, as searched for. */
    public const MARK_START = '<span class="bcf-mark"';

    private BetterCategories $plugin;

    private Registry $params;

    private Filters $filters;

    private string $device;

    public function __construct(BetterCategories $plugin, Registry $params, string $device)
    {
        $this->plugin  = $plugin;
        $this->params  = $params;
        $this->device  = $device;
        $this->filters = new Filters($plugin, $params);
    }

    /** Whether the address carries a filter of this page (also when the panel is not shown). */
    public static function hasFilters(object $input): bool
    {
        foreach (array_keys($input->getArray()) as $key) {
            if (str_starts_with((string) $key, Filters::PREFIX)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The page with the filters: null when nothing changes (no filters for this page).
     *
     * @param  bool  $listHidden  the product list is hidden under the subcategories (and no filter is chosen)
     */
    public function process(string $body, int $appId, int $categoryId, array $categories, bool $listHidden): ?string
    {
        $bodyStart = stripos($body, '<body');
        if ($bodyStart === false || !GridboxList::available()) {
            return null;
        }
        if (!preg_match('#<div\b[^>]*\sclass="(?:[^"]*\s)?ba-item-blog-posts[\s"][^>]*>#i', $body, $m, PREG_OFFSET_CAPTURE, $bodyStart)) {
            return null;
        }
        $elStart = $m[0][1];
        $elEnd   = self::elementEnd($body, $elStart);
        if ($elEnd === null || !preg_match('/\sid="([A-Za-z0-9_-]+)"/', $m[0][0], $idm)) {
            return null;
        }
        $elementId = $idm[1];

        $defs = $this->forCategory($this->filters->definitions($appId), $categoryId, $categories);
        if (!$defs) {
            return null;
        }
        $state = $this->filters->state($defs);
        if ($listHidden && !$state) {
            return null;
        }

        $products = $this->filters->products($appId, $categoryId, $categories, $defs);
        $defs     = $this->forCategory($this->filters->definitions($appId), $categoryId, $categories);
        $min      = max(0, (int) $this->params->get('filters_min_products', 2));
        if (!$state && count($products) < max(1, $min)) {
            return null;
        }
        $result = $this->filters->apply($products, $defs, $state);
        $state  = $result['state'];
        $query  = $this->filters->queryString($defs, $state);

        $element = substr($body, $elStart, $elEnd - $elStart);
        $total   = count($result['ids']);
        if ($state) {
            [$item, $source] = GridboxList::elementConfig($appId, $elementId, $element);
            if (!$item) {
                $this->plugin->log('Filters: settings of the product list element ' . $elementId . ' not found (app ' . $appId . ')', Log::WARNING);

                return null;
            }

            $list    = GridboxList::render($appId, $categoryId, $item, $result['ids'], $query);
            $total   = $list['count'];
            $element = $this->replaceInner($element, 'ba-blog-posts-wrapper', $list['posts']);
            $element = $this->replaceInner($element, 'ba-blog-posts-pagination-wrapper', $list['pagination']);
            $element = $this->sortingUrl($element, $query);
        }

        $position = (string) $this->params->get('filters_position', 'sidebar_left');
        $position = in_array($position, Filters::POSITIONS, true) ? $position : 'sidebar_left';
        $drawer   = $position === 'drawer' || ($this->device === 'mobile' && $this->params->get('filters_mobile', 'drawer') === 'drawer');
        $classes  = 'bcf-pos-' . str_replace('_', '-', $position) . ' bcf-dev-' . $this->device
            . ($drawer ? ' bcf-is-drawer' : '') . ($this->params->get('filters_mobile', 'drawer') === 'drawer' ? ' bcf-m-drawer' : '')
            . ((string) $this->params->get('filters_apply', 'auto') === 'auto' ? ' bcf-auto' : '');
        $style    = $this->styleVars();

        // in one row above the products every filter opens as a menu: all closed at first
        $form  = $this->form($defs, $result['facets'], $state, $total, $classes, $style, $position === 'top' && !$drawer);
        $bar   = $this->bar($defs, $result['facets'], $state, $total, $drawer);
        // the subcategories (inserted later, "above the products") go above the bar: see MARK
        $block = self::MARK . $bar . $element;

        if ($position === 'sidebar_left' || $position === 'sidebar_right') {
            $block = '<div class="bcf-root bcf-layout ' . $classes . '"' . $style . '>'
                . ($position === 'sidebar_left' ? $form : '')
                . '<div class="bcf-main">' . $block . '</div>'
                . ($position === 'sidebar_right' ? $form : '')
                . '</div>';
            $body = substr($body, 0, $elStart) . $block . substr($body, $elEnd);
        } elseif ($position === 'top' || $position === 'drawer') {
            // the subcategories above the filters
            $block = '<div class="bcf-root ' . $classes . '"' . $style . '>' . self::MARK . $form . $bar . $element . '</div>';
            $body  = substr($body, 0, $elStart) . $block . substr($body, $elEnd);
        } else {
            $block = '<div class="bcf-root ' . $classes . '"' . $style . '>' . ($drawer ? $form : '') . $block . '</div>';
            $body  = substr($body, 0, $elStart) . $block . substr($body, $elEnd);
            if (!$drawer) {
                $at = $this->anchor($body, $position, $bodyStart);
                if ($at === null) {
                    // the chosen element is not on the page: the panel goes above the products
                    $body = str_replace($block, '<div class="bcf-root ' . $classes . '"' . $style . '>' . $form . self::MARK . $bar . $element . '</div>', $body);
                } else {
                    $body = substr($body, 0, $at) . '<div class="bcf-side ' . $classes . '"' . $style . '>' . $form . '</div>' . substr($body, $at);
                }
            }
        }

        return $this->head($body, (bool) $state);
    }

    /** The filters of this category: those without a category limit, and those limited to it or one of its parents. */
    private function forCategory(array $defs, int $categoryId, array $categories): array
    {
        $path = [];
        for ($id = $categoryId, $guard = 0; $id > 0 && $guard < 50; $guard++) {
            $path[$id] = true;
            $id = $categories[$id]->parent ?? 0;
        }

        return array_values(array_filter($defs, fn ($def) => !$def['categories'] || array_intersect_key(array_flip($def['categories']), $path)));
    }

    // ---------------------------------------------------------------- page parts

    /** Offset where the panel goes for "in the side column", "after the categories element" / before or after an element. */
    private function anchor(string $body, string $position, int $from): ?int
    {
        if ($position === 'column_top' || $position === 'column_bottom') {
            $list = strpos($body, '<div class="bcf-root ', $from);

            return $list === false ? null : $this->sideColumn($body, $list, $position === 'column_bottom');
        }
        if ($position === 'after_categories') {
            $pattern = '#<div\b[^>]*\sclass="(?:[^"]*\s)?ba-item-categories[\s"]#i';
        } else {
            $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $this->params->get('filters_item_id', ''));
            if ($id === '') {
                return null;
            }
            $pattern = '#<div\b[^>]*\sid="' . preg_quote($id, '#') . '"#i';
        }
        if (!preg_match($pattern, $body, $m, PREG_OFFSET_CAPTURE, $from)) {
            return null;
        }

        return $position === 'before_item' ? $m[0][1] : self::elementEnd($body, $m[0][1]);
    }

    /**
     * The start (or end) of the content of the column next to the one with the product list: the
     * Gridbox row of the list is divided into columns (e.g. 3 + 9), the side one holds the category
     * tree, contacts and the like. The column before the list is preferred, else the one after it.
     */
    private function sideColumn(string $body, int $list, bool $bottom): ?int
    {
        // the <div>s open at the product list, innermost last
        $open = [];
        if (!preg_match_all('#<(/?)div\b([^>]*)>#i', substr($body, 0, $list), $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return null;
        }
        foreach ($tags as $tag) {
            if ($tag[1][0] === '/') {
                array_pop($open);
            } else {
                $open[] = [$tag[0][1], $tag[2][0], $tag[0][1] + strlen($tag[0][0])];
            }
        }
        for ($i = count($open) - 1; $i >= 0; $i--) {
            if (!preg_match('/\sclass="(?:[^"]*\s)?column-wrapper[\s"]/', $open[$i][1])) {
                continue;
            }
            // the columns of this row: its direct child <div>s
            $children = [];
            $offset   = $open[$i][2];
            $end      = self::elementEnd($body, $open[$i][0]) ?? strlen($body);
            while ($offset < $end && preg_match('#<div\b[^>]*>#i', $body, $m, PREG_OFFSET_CAPTURE, $offset) && $m[0][1] < $end) {
                $childEnd = self::elementEnd($body, $m[0][1]);
                if ($childEnd === null) {
                    break;
                }
                $children[] = [$m[0][1], $childEnd];
                $offset     = $childEnd;
            }
            $before = $after = null;
            foreach ($children as [$a, $b]) {
                if ($b <= $list) {
                    $before = [$a, $b];
                } elseif ($a > $list && $after === null) {
                    $after = [$a, $b];
                }
            }
            $column = $before ?? $after;
            if ($column === null) {
                continue;
            }
            // inside the Gridbox column (.ba-grid-column) of that wrapper
            if (!preg_match('#<div\b[^>]*\sclass="(?:[^"]*\s)?ba-grid-column[\s"][^>]*>#i', $body, $m, PREG_OFFSET_CAPTURE, $column[0]) || $m[0][1] >= $column[1]) {
                return null;
            }
            if (!$bottom) {
                return $m[0][1] + strlen($m[0][0]);
            }
            $columnEnd = self::elementEnd($body, $m[0][1]);

            return $columnEnd === null ? null : $columnEnd - strlen('</div>');
        }

        return null;
    }

    /** Stylesheet and script of the filters, and "noindex" for filtered lists (setting). */
    private function head(string $body, bool $filtered): string
    {
        $root = Uri::root(true) . '/media/plg_system_bettercategories/';
        $ver  = fn (string $file) => BetterCategories::ASSET_VERSION . '.' . (int) @filemtime(JPATH_ROOT . '/media/plg_system_bettercategories/' . $file);
        $css  = '<link rel="stylesheet" href="' . $root . 'css/filters.css?' . $ver('css/filters.css') . '">';
        $js   = '<script src="' . $root . 'js/filters.js?' . $ver('js/filters.js') . '" defer></script>';
        $meta = '';
        if ($filtered && $this->params->get('filters_noindex', 1)) {
            // a filtered list is a variant of the category page: followed, not indexed
            $body = preg_replace('#<meta\s+name="robots"[^>]*>#i', '', $body) ?? $body;
            $meta = '<meta name="robots" content="noindex, follow">';
        }
        $pos = stripos($body, '</head>');
        if ($pos !== false) {
            $body = substr($body, 0, $pos) . $meta . $css . $js . substr($body, $pos);
        }

        return $body;
    }

    private function styleVars(): string
    {
        $vars = [];
        $width = (int) $this->params->get('filters_width', 260);
        if ($width >= 160 && $width <= 600) {
            $vars[] = '--bcf-width:' . $width . 'px';
        }
        foreach (['filters_accent' => '--bcf-accent', 'filters_bg' => '--bcf-bg', 'filters_color' => '--bcf-color'] as $key => $var) {
            $color = trim((string) $this->params->get($key, ''));
            if (preg_match('/^#[0-9a-f]{3,8}$/i', $color)) {
                $vars[] = $var . ':' . $color;
            }
        }
        $size = (int) $this->params->get('filters_font_size', 15);
        if ($size >= 10 && $size <= 30) {
            $vars[] = '--bcf-font:' . $size . 'px';
        }

        return $vars ? ' style="' . implode(';', $vars) . '"' : '';
    }

    /** The address of the category with the given filters (and the visitor's sorting). */
    private function url(array $defs, array $state): string
    {
        $base  = Uri::getInstance()->toString(['path']);
        $query = $this->filters->queryString($defs, $state);
        $sort  = (string) Factory::getApplication()->getInput()->getString('sort-by', '');
        if ($sort !== '' && preg_match('/^[a-z-]+$/', $sort)) {
            $query .= ($query !== '' ? '&' : '') . 'sort-by=' . $sort;
        }

        return $base . ($query !== '' ? '?' . $query : '');
    }

    private function form(array $defs, array $facets, array $state, int $total, string $classes, string $style, bool $menus = false): string
    {
        $e     = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $t     = fn (string $key, string $default) => $e($this->filters->text($key, $default));
        $more  = max(0, (int) $this->params->get('filters_more', 6));
        $hide  = (bool) $this->params->get('filters_hide_empty', 1);
        $count = (bool) $this->params->get('filters_counts', 1);

        $groups = '';
        foreach ($defs as $def) {
            $facet = $facets[$def['key']] ?? null;
            $s     = $state[$def['key']] ?? null;
            if (!$facet) {
                continue;
            }
            $body  = '';
            $badge = 0;
            // a parameter only a product or two of the category mention (setting) is left out
            if ($def['type'] === 'param' && !$s && ($facet['have'] ?? 0) < $def['minCount']) {
                continue;
            }
            if ($facet['range']) {
                if ($facet['min'] === null && !$s) {
                    continue;
                }
                $badge = $s ? 1 : 0;
                [$unit, $round] = $def['type'] === 'price'
                    ? [trim((string) ($this->plugin->storeCurrency()->symbol ?? '')), true]
                    : [Filters::UNITS[$def['dim']] ?? '', false];
                // pressure is shown as written in HVAC (Pa, kPa, bar) and typed back the same way
                $words = $def['type'] === 'param' && $def['dim'] === 'pa';
                if ($words) {
                    $unit = '';
                }
                $num = fn (float $v) => $words ? str_replace("\u{00A0}", ' ', $this->filters->formatValue('pa', $v)) : $this->shortNumber($v);
                $ph  = fn (?float $v, bool $up) => $v === null ? '' : ($round ? (string) ($up ? ceil($v) : floor($v)) : $num($v));
                $body = '<div class="bcf-range">'
                    . '<label class="bcf-range-field"><span class="bcf-range-label">' . $t('filters_label_from', 'From') . '</span>'
                    . '<input type="text" inputmode="decimal" autocomplete="off" name="' . $e($def['key']) . '-min" value="' . $e($s && $s['lo'] !== null ? $num($s['lo']) : '') . '" placeholder="' . $e($ph($facet['min'], false)) . '"></label>'
                    . '<span class="bcf-range-sep" aria-hidden="true">–</span>'
                    . '<label class="bcf-range-field"><span class="bcf-range-label">' . $t('filters_label_to', 'To') . '</span>'
                    . '<input type="text" inputmode="decimal" autocomplete="off" name="' . $e($def['key']) . '-max" value="' . $e($s && $s['hi'] !== null ? $num($s['hi']) : '') . '" placeholder="' . $e($ph($facet['max'], true)) . '"></label>'
                    . ($unit !== '' ? '<span class="bcf-unit">' . $e($unit) . '</span>' : '')
                    . '</div>';
            } else {
                $items = '';
                $shown = 0;
                $extra = 0;
                foreach ($facet['options'] as $slug => $o) {
                    if ($hide && $o['count'] === 0 && !$o['on']) {
                        continue;
                    }
                    $isExtra = $more > 0 && $shown >= $more && !$o['on'];
                    $extra  += $isExtra ? 1 : 0;
                    $shown++;
                    $badge  += $o['on'] ? 1 : 0;
                    $items  .= '<li class="bcf-option' . ($isExtra ? ' bcf-extra' : '') . ($o['count'] === 0 ? ' is-empty' : '') . '"><label>'
                        . '<input type="checkbox" name="' . $e($def['key']) . '[]" value="' . $e((string) $slug) . '"' . ($o['on'] ? ' checked' : '') . ($o['count'] === 0 && !$o['on'] ? ' disabled' : '') . '>'
                        . '<span class="bcf-option-label">' . $e($o['label']) . '</span>'
                        . ($count ? '<span class="bcf-count">' . $o['count'] . '</span>' : '')
                        . '</label></li>';
                }
                if ($items === '') {
                    continue;
                }
                $body = '<ul class="bcf-options">' . $items . '</ul>';
                if ($extra > 0) {
                    $body .= '<button type="button" class="bcf-more" aria-expanded="false" data-more="' . $t('filters_label_more', 'Show more') . ' (' . $extra . ')" data-less="' . $t('filters_label_less', 'Show less') . '">'
                        . $t('filters_label_more', 'Show more') . ' (' . $extra . ')</button>';
                }
            }
            $open    = !$menus && (!$def['collapsed'] || $badge > 0);
            $groups .= '<details class="bcf-group bcf-group--' . $e($def['type']) . '" data-bcf-key="' . $e($def['key']) . '"' . ($open ? ' open' : '') . '>'
                . '<summary class="bcf-group-title"><span class="bcf-group-name">' . $e($def['label']) . '</span>'
                . ($badge > 0 ? '<span class="bcf-badge">' . $badge . '</span>' : '') . '</summary>'
                . '<div class="bcf-group-body">' . $body . '</div></details>';
        }

        $sort = (string) Factory::getApplication()->getInput()->getString('sort-by', '');
        $show = $this->filters->text('filters_label_show', 'Show %d products');

        return '<div class="bcf-panel ' . $classes . '"' . $style . ' data-bcf-panel>'
            . '<div class="bcf-backdrop" data-bcf-close></div>'
            . '<form class="bcf-form" id="bcf-form" method="get" action="' . $e(Uri::getInstance()->toString(['path'])) . '" data-bcf-form aria-label="' . $t('filters_title', 'Filters') . '">'
            . '<div class="bcf-head"><span class="bcf-title">' . $t('filters_title', 'Filters') . '</span>'
            . '<button type="button" class="bcf-close" data-bcf-close aria-label="' . $t('filters_label_close', 'Close') . '">'
            . '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button></div>'
            . '<div class="bcf-groups">' . $groups . '</div>'
            . '<div class="bcf-foot">'
            . '<button type="submit" class="bcf-apply">' . $t('filters_label_apply', 'Apply') . '</button>'
            . ($state ? '<a class="bcf-reset" href="' . $e($this->url($defs, [])) . '" data-bcf-link rel="nofollow">' . $t('filters_label_clear', 'Clear all') . '</a>' : '')
            . '<button type="button" class="bcf-show" data-bcf-close>' . $e(str_replace('%d', (string) $total, $show)) . '</button>'
            . '</div>'
            . ($sort !== '' && preg_match('/^[a-z-]+$/', $sort) ? '<input type="hidden" name="sort-by" value="' . $e($sort) . '">' : '')
            . '</form></div>';
    }

    /** The bar above the products: drawer button, number of products, chosen filters, "clear all". */
    private function bar(array $defs, array $facets, array $state, int $total, bool $drawer): string
    {
        $e      = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $chips  = '';
        $chosen = 0;
        foreach ($defs as $def) {
            $s = $state[$def['key']] ?? null;
            if (!$s) {
                continue;
            }
            if (isset($s['values'])) {
                foreach ($s['values'] as $value) {
                    $label = $facets[$def['key']]['options'][$value]['label'] ?? null;
                    if ($label === null) {
                        continue;
                    }
                    $rest = $state;
                    $rest[$def['key']]['values'] = array_values(array_diff($s['values'], [$value]));
                    if (!$rest[$def['key']]['values']) {
                        unset($rest[$def['key']]);
                    }
                    $chips .= $this->chip($def['type'] === 'sale' || $def['type'] === 'stock' ? $label : $def['label'] . ': ' . $label, $this->url($defs, $rest));
                    $chosen++;
                }
            } else {
                $unit = $def['type'] === 'price' ? trim((string) ($this->plugin->storeCurrency()->symbol ?? '')) : (Filters::UNITS[$def['dim']] ?? '');
                $show = fn (float $v) => $this->shortNumber($v);
                if ($def['type'] === 'param' && $def['dim'] === 'pa') {
                    [$unit, $show] = ['', fn (float $v) => $this->filters->formatValue('pa', $v)];
                }
                $text = $s['lo'] !== null && $s['hi'] !== null && abs($s['lo'] - $s['hi']) < 1e-9
                    ? $show($s['lo'])
                    : ($s['lo'] !== null ? $show($s['lo']) : '…') . ' – ' . ($s['hi'] !== null ? $show($s['hi']) : '…');
                $text .= $unit !== '' ? "\u{00A0}" . $unit : '';
                $rest = $state;
                unset($rest[$def['key']]);
                $chips .= $this->chip($def['label'] . ': ' . $text, $this->url($defs, $rest));
                $chosen++;
            }
        }

        $label = $this->filters->text('filters_label_results', '%d products');
        $out   = '<div class="bcf-bar" data-bcf-bar>'
            . '<button type="button" class="bcf-toggle" data-bcf-open aria-controls="bcf-form" aria-expanded="false"' . ($drawer ? '' : ' hidden') . '>'
            . '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
            . '<span>' . $e($this->filters->text('filters_label_button', 'Filters')) . '</span>'
            . ($chosen ? '<span class="bcf-badge">' . $chosen . '</span>' : '') . '</button>';
        if ($state) {
            $out .= '<span class="bcf-total" role="status">' . $e(str_replace('%d', (string) $total, $label)) . '</span>'
                . $chips
                . '<a class="bcf-chip bcf-chip--clear" href="' . $e($this->url($defs, [])) . '" data-bcf-link rel="nofollow">' . $e($this->filters->text('filters_label_clear', 'Clear all')) . '</a>';
            if ($total === 0) {
                $out .= '<p class="bcf-none">' . $e($this->filters->text('filters_label_none', 'No products match the selected filters.')) . '</p>';
            }
        }

        return $out . '</div>';
    }

    private function chip(string $label, string $url): string
    {
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

        return '<a class="bcf-chip" href="' . $e($url) . '" data-bcf-link rel="nofollow" aria-label="' . $e($this->filters->text('filters_label_remove', 'Remove') . ': ' . $label) . '">'
            . '<span>' . $e($label) . '</span><svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg></a>';
    }

    /** "1000", "2.5k", "200G" (typed back in the same form). */
    private function shortNumber(float $v): string
    {
        $abs = abs($v);
        foreach ([[1e9, 'G'], [1e6, 'M'], [1e4, 'k']] as [$f, $p]) {
            if ($abs >= $f) {
                $d = $p === 'k' ? 1e3 : $f;

                return rtrim(rtrim(number_format($v / $d, 3, '.', ''), '0'), '.') . $p;
            }
        }

        return rtrim(rtrim(number_format($v, $abs < 1 ? 6 : 2, '.', ''), '0'), '.');
    }

    // ---------------------------------------------------------------- the Gridbox element

    /** The content of the first <div class="… $class …"> inside $html replaced. */
    private function replaceInner(string $html, string $class, string $content): string
    {
        if (!preg_match('#<div\b[^>]*\sclass="(?:[^"]*\s)?' . preg_quote($class, '#') . '[\s"][^>]*>#i', $html, $m, PREG_OFFSET_CAPTURE)) {
            return $html;
        }
        $start = $m[0][1];
        $end   = self::elementEnd($html, $start);
        if ($end === null) {
            return $html;
        }
        $open = $m[0][1] + strlen($m[0][0]);

        return substr($html, 0, $open) . $content . '</div>' . substr($html, $end);
    }

    /** The sorting menu keeps the filters: its address gets them before "sort-by=". */
    private function sortingUrl(string $html, string $query): string
    {
        if ($query === '') {
            return $html;
        }

        return preg_replace_callback('#(<select\b[^>]*\bclass="blog-posts-sorting"[^>]*\bdata-url=")([^"]*)(")#i', function ($m) use ($query) {
            $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
            if (!str_ends_with($url, 'sort-by=')) {
                return $m[0];
            }
            $base = substr($url, 0, -strlen('sort-by='));
            $url  = $base . $query . '&sort-by=';

            return $m[1] . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . $m[3];
        }, $html) ?? $html;
    }

    /** Offset right after the </div> closing the <div> that starts at $start (div nesting counted). */
    public static function elementEnd(string $html, int $start): ?int
    {
        $depth  = 0;
        $offset = $start;
        while (preg_match('#<(/?)div\b[^>]*>#i', $html, $tag, PREG_OFFSET_CAPTURE, $offset)) {
            $depth += $tag[1][0] === '/' ? -1 : 1;
            $offset = $tag[0][1] + strlen($tag[0][0]);
            if ($depth === 0) {
                return $offset;
            }
        }

        return null;
    }
}

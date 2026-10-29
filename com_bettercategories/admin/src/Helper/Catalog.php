<?php

/**
 * @package     Merserwis.Component
 * @subpackage  com_bettercategories
 *
 * The map of Better Categories' administrator: which page shows which part of the plugin, in which section
 * of the control panel, with which icon, and which of its settings say that it is on. The settings themselves
 * are the fieldsets of the plugin's manifest (plg_system_bettercategories/bettercategories.xml): a fieldset
 * with page="x" is a tab of page x, in the order of the file. Two kinds of pages:
 *
 *  - the parts set and tuned from time to time (the list, its tiles, the subcategories, the descriptions,
 *    the navigation, the filters): each with its live preview where it has one, all in the administrator menu;
 *  - the configuration: what is set once and forgotten, one page with a tab for each part.
 *
 * Quick setup: the few switches a new site decides first, each one setting one or more settings of the
 * plugin, with the value most sites should have.
 */

namespace Merserwis\Component\BetterCategories\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\Registry\Registry;

final class Catalog
{
    /** the sections of the control panel, in order */
    public const GROUPS = ['list', 'page', 'products'];

    /**
     * The pages: group (section of the control panel), icon (Joomla's icon-*), on (the settings that say the
     * part works, any of them, "name=value" for a list that is on at one value; none: always there, no dot),
     * settings (the tab where the part is switched on, the one the control panel's "off" note links to).
     */
    public const PAGES = [
        'list'          => ['group' => 'list',     'icon' => 'list',     'on' => [], 'settings' => 'list_content'],
        'tiles'         => ['group' => 'list',     'icon' => 'images',   'on' => ['display=tiles'], 'settings' => 'tiles'],
        'subcategories' => ['group' => 'list',     'icon' => 'tree-2',   'on' => ['sub_mode'], 'settings' => 'subcategories'],
        'descriptions'  => ['group' => 'page',     'icon' => 'file-alt', 'on' => ['desc_enabled'], 'settings' => 'catdesc'],
        'navigation'    => ['group' => 'page',     'icon' => 'compass',  'on' => ['leaf_siblings', 'gb_categories_rows'], 'settings' => 'navigation'],
        'filters'       => ['group' => 'products', 'icon' => 'filter',   'on' => ['filters_enabled'], 'settings' => 'filters'],
        'config'        => ['group' => '',         'icon' => 'cog',      'on' => []],
    ];

    /** the tabs of the configuration page (fieldsets with page="config"), as tiles of the control panel */
    public const CONFIG = [
        'basic'  => ['icon' => 'info-circle', 'on' => []],
        'seo'    => ['icon' => 'code',        'on' => ['schema_itemlist', 'schema_breadcrumb']],
        'backup' => ['icon' => 'download',    'on' => []],
    ];

    /**
     * Quick setup, in sections: switch => [the settings it sets, the recommended value]. A switch of
     * several settings is on when all of them are; only a switch that was changed is saved, so a
     * setting tuned on its own page is not overwritten by a switch nobody touched. 'values' names the
     * off and on value of a setting that is a list rather than a yes/no (the list as text / tiles):
     * such a switch is off at the off value and on at any other, so a choice made on the page
     * (subcategories in a drawer) shows as on and is kept.
     */
    public const SETUP = [
        'list' => [
            'show_counter'     => ['set' => ['show_counter'], 'recommended' => 1],
            'hide_empty'       => ['set' => ['hide_empty'], 'recommended' => 1],
            'qs_tiles'         => ['set' => ['display'], 'recommended' => 0, 'values' => ['display' => ['text', 'tiles']]],
            'thumbnails'       => ['set' => ['thumbnails'], 'recommended' => 1],
            'first_row_eager'  => ['set' => ['first_row_eager'], 'recommended' => 1],
            'qs_subcategories' => ['set' => ['sub_mode'], 'recommended' => 0, 'values' => ['sub_mode' => ['none', 'below']]],
        ],
        'page' => [
            'desc_enabled'       => ['set' => ['desc_enabled'], 'recommended' => 0],
            'desc_fold'          => ['set' => ['desc_fold'], 'recommended' => 0],
            'leaf_siblings'      => ['set' => ['leaf_siblings'], 'recommended' => 0],
            'gb_categories_rows' => ['set' => ['gb_categories_rows'], 'recommended' => 0],
            'qs_schema'          => ['set' => ['schema_itemlist', 'schema_breadcrumb'], 'recommended' => 1, 'values' => ['schema_breadcrumb' => ['no', 'auto']]],
        ],
        'products' => [
            'filters_enabled'  => ['set' => ['filters_enabled'], 'recommended' => 0],
            'filters_noindex'  => ['set' => ['filters_noindex'], 'recommended' => 1],
            'list_view_toggle' => ['set' => ['list_view_toggle'], 'recommended' => 1],
            'show_all'         => ['set' => ['show_all'], 'recommended' => 1],
        ],
    ];

    /** the page of a name, or null */
    public static function page(string $name): ?array
    {
        return self::PAGES[$name] ?? null;
    }

    /**
     * A setting that is on: anything but empty, 0, off, none or no (switches, lists such as "none / below / drawer",
     * texts); "name=value": the setting has that value.
     */
    public static function isOn(Registry $params, array $defaults, string $key): bool
    {
        $want = null;
        if (str_contains($key, '=')) {
            [$key, $want] = explode('=', $key, 2);
        }
        $value = $params->get($key, $defaults[$key] ?? '');
        if (\is_array($value) || \is_object($value)) {
            return $want === null && (bool) $value;
        }
        $value = strtolower(trim((string) $value));

        return $want !== null ? $value === $want : !\in_array($value, ['', '0', 'off', 'none', 'no'], true);
    }

    /** 'on' when any of the settings is on, 'off' when none is, '' for a page without them */
    public static function state(array $keys, Registry $params, array $defaults): string
    {
        if (!$keys) {
            return '';
        }
        foreach ($keys as $key) {
            if (self::isOn($params, $defaults, $key)) {
                return 'on';
            }
        }

        return 'off';
    }

    /**
     * The tiles of the control panel: group => [[name, icon, state, link], …], the configuration as a group of its own.
     *
     * @return array<string, list<array{name: string, icon: string, state: string, link: string, config: bool}>>
     */
    public static function tiles(Registry $params, array $defaults): array
    {
        $groups = array_fill_keys(self::GROUPS, []);
        foreach (self::PAGES as $name => $page) {
            if ($page['group'] === '') {
                continue;
            }
            $groups[$page['group']][] = ['name' => $name, 'icon' => $page['icon'], 'state' => self::state($page['on'], $params, $defaults),
                'link' => 'index.php?option=com_bettercategories&view=page&page=' . $name, 'config' => false];
        }
        $groups['config'] = [];
        foreach (self::CONFIG as $name => $tab) {
            $groups['config'][] = ['name' => $name, 'icon' => $tab['icon'], 'state' => self::state($tab['on'], $params, $defaults),
                'link' => 'index.php?option=com_bettercategories&view=page&page=config&tab=' . $name, 'config' => true];
        }

        return array_filter($groups);
    }

    /**
     * The state of a quick setup switch from the settings: on when every setting it sets is on (a list:
     * when it is not at its off value).
     */
    public static function setupValue(array $item, Registry $params, array $defaults): int
    {
        foreach ($item['set'] as $key) {
            if (isset($item['values'][$key])) {
                $value = $params->get($key, $defaults[$key] ?? '');
                if (!\is_scalar($value) || (string) $value === '' || (string) $value === (string) $item['values'][$key][0]) {
                    return 0;
                }
            } elseif (!self::isOn($params, $defaults, $key)) {
                return 0;
            }
        }

        return 1;
    }

    /**
     * The settings a quick setup sends: only the switches whose value differs from the one shown
     * (the initial values come back with the form).
     *
     * @param array<string, mixed> $posted  switch => 0|1
     * @param array<string, mixed> $initial switch => 0|1, as the page showed it
     *
     * @return array<string, int|string> setting => 0|1 (or the value of a list)
     */
    public static function setupChanges(array $posted, array $initial): array
    {
        $out = [];
        foreach (self::SETUP as $items) {
            foreach ($items as $switch => $item) {
                if (!\array_key_exists($switch, $posted) || !\is_scalar($posted[$switch])) {
                    continue;
                }
                $value = (int) $posted[$switch] === 1 ? 1 : 0;
                if (\array_key_exists($switch, $initial) && \is_scalar($initial[$switch]) && (int) $initial[$switch] === $value) {
                    continue;
                }
                foreach ($item['set'] as $key) {
                    $out[$key] = $item['values'][$key][$value] ?? $value;
                }
            }
        }

        return $out;
    }
}

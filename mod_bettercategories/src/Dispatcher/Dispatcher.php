<?php

/**
 * @package     Merserwis.Module
 * @subpackage  mod_bettercategories
 */

namespace Merserwis\Module\BetterCategories\Site\Dispatcher;

\defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\Event\Event;

/**
 * The module asks the Better Categories system plugin for the list (same styles, cache and links as
 * on the store pages); the plugin must be enabled.
 */
class Dispatcher extends AbstractModuleDispatcher
{
    private const OVERRIDES = ['heading', 'display', 'tile_style', 'orientation', 'columns', 'columns_tablet', 'columns_mobile', 'sub_mode', 'price_from'];

    /** The settings of the store menu (menu_<key>), handed to the plugin. */
    private const MENU = ['style', 'label', 'depth', 'images', 'counts', 'price', 'columns', 'leaves', 'trigger', 'accent'];

    protected function getLayoutData(): array
    {
        $data   = parent::getLayoutData();
        $params = $data['params'];

        $overrides = [];
        foreach (self::OVERRIDES as $key) {
            $overrides[$key] = trim((string) $params->get($key, ''));
        }

        // 1.11.0: the store menu (mega menu) instead of the list
        $menu = [];
        if ($params->get('kind', 'list') === 'menu') {
            foreach (self::MENU as $key) {
                $menu[$key] = trim((string) $params->get('menu_' . $key, ''));
            }
        }

        $event = new Event('onBetterCategoriesModule', [
            'app'       => (int) $params->get('app_id', 0),
            'category'  => (int) $params->get('category', 0),
            'module'    => (int) ($data['module']->id ?? 0),
            'overrides' => $overrides,
            'menu'      => $menu,
            'html'      => '',
        ]);
        $this->getApplication()->getDispatcher()->dispatch('onBetterCategoriesModule', $event);
        $data['listHtml'] = (string) $event->getArgument('html', '');

        return $data;
    }
}

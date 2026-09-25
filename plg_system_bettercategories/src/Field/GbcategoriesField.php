<?php

/**
 * @package     Merserwis.Plugin
 * @subpackage  System.bettercategories
 */

namespace Merserwis\Plugin\System\BetterCategories\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseInterface;

/**
 * Categories of the Gridbox store apps, as an indented tree ("App: Parent / Child").
 */
class GbcategoriesField extends ListField
{
    protected $type = 'Gbcategories';

    protected function getOptions()
    {
        $options = parent::getOptions();

        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->createQuery()
                ->select(['c.id', 'c.title', 'c.parent', 'c.app_id', $db->quoteName('a.title', 'app_title')])
                ->from($db->quoteName('#__gridbox_categories', 'c'))
                ->innerJoin($db->quoteName('#__gridbox_app', 'a') . ' ON a.id = c.app_id')
                ->where($db->quoteName('a.type') . ' = ' . $db->quote('products'))
                ->order('c.app_id ASC, c.order_list ASC, c.id ASC');
            $rows = $db->setQuery($query)->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            return $options;
        }

        $byParent = [];
        foreach ($rows as $row) {
            $byParent[(int) $row->app_id][(int) $row->parent][] = $row;
        }

        foreach ($byParent as $appId => $tree) {
            $walk = function (int $parent, int $level) use (&$walk, &$options, $tree) {
                foreach ($tree[$parent] ?? [] as $row) {
                    $options[] = HTMLHelper::_('select.option', (int) $row->id,
                        $row->app_title . ': ' . str_repeat('— ', $level) . $row->title);
                    if ($level < 30) {
                        $walk((int) $row->id, $level + 1);
                    }
                }
            };
            $walk(0, 0);
        }

        return $options;
    }
}

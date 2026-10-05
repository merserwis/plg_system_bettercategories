<?php

namespace Merserwis\Plugin\System\BetterCategories\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseInterface;

/** Gridbox fields of the store apps a filter can use: lists, radio buttons, checkboxes and short texts. */
class GbfieldsField extends ListField
{
    protected $type = 'Gbfields';

    protected function getOptions()
    {
        $options = parent::getOptions();

        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->createQuery()
                ->select(['f.id', 'f.label', 'f.field_type', $db->quoteName('a.title', 'app_title')])
                ->from($db->quoteName('#__gridbox_fields', 'f'))
                ->innerJoin($db->quoteName('#__gridbox_app', 'a') . ' ON a.id = f.app_id')
                ->where($db->quoteName('a.type') . ' = ' . $db->quote('products'))
                ->where($db->quoteName('f.field_type') . ' IN (' . implode(',', array_map([$db, 'quote'], ['select', 'radio', 'checkbox', 'text'])) . ')')
                ->order('f.app_id ASC, f.id ASC');
            $rows = $db->setQuery($query)->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            return $options;
        }

        foreach ($rows as $row) {
            $options[] = HTMLHelper::_('select.option', (int) $row->id, $row->app_title . ': ' . $row->label . ' (' . $row->field_type . ')');
        }

        return $options;
    }
}

<?php

/**
 * @package     Merserwis.Plugin
 * @subpackage  System.bettercategories
 */

namespace Merserwis\Plugin\System\BetterCategories\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Merserwis\Plugin\System\BetterCategories\Extension\BetterCategories;

/**
 * Administrator tools in the plugin settings: export / import of the settings (mode="settings")
 * and generating / deleting the tile thumbnails (mode="thumbs"). Actions run through com_ajax.
 */
class BctoolsField extends FormField
{
    protected $type = 'Bctools';

    protected function getInput()
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        // the file time in the version: browsers take a changed file even when only the file was replaced
        $ver = fn (string $file): string => BetterCategories::ASSET_VERSION . '.' . (int) @filemtime(JPATH_ROOT . '/media/plg_system_bettercategories/' . $file);
        $wa->registerAndUseStyle('plg_system_bettercategories.admin', 'plg_system_bettercategories/admin.css', ['version' => $ver('css/admin.css')]);
        $wa->registerAndUseScript('plg_system_bettercategories.tools', 'plg_system_bettercategories/tools.js', ['version' => $ver('js/tools.js')], ['defer' => true], ['core']);
        foreach (['EXPORT_DONE', 'IMPORT_CONFIRM', 'IMPORT_DONE', 'IMPORT_READING', 'THUMBS_RUNNING', 'THUMBS_DONE', 'THUMBS_CLEAR_CONFIRM', 'THUMBS_CLEARED', 'WORKING', 'FAILED', 'HELP'] as $key) {
            Text::script('PLG_SYSTEM_BETTERCATEGORIES_TOOLS_' . $key);
        }

        $url  = 'index.php?option=com_ajax&amp;plugin=bettercategories&amp;group=system&amp;format=json';
        $mode = (string) ($this->element['mode'] ?? 'settings');
        $btn  = fn (string $action, string $label, string $class = 'btn-outline-secondary') => '<button type="button" class="btn btn-sm ' . $class . '" data-bctools-action="' . $action . '">'
            . Text::_('PLG_SYSTEM_BETTERCATEGORIES_TOOLS_' . $label) . '</button>';

        if ($mode === 'thumbs') {
            $body = '<p class="small text-muted mb-2">' . Text::_('PLG_SYSTEM_BETTERCATEGORIES_TOOLS_THUMBS_DESC') . '</p>'
                . '<div class="bcat-tools-buttons">' . $btn('thumbs', 'THUMBS_GENERATE', 'btn-primary') . $btn('thumbs_clear', 'THUMBS_CLEAR') . '</div>';
        } else {
            $body = '<p class="small text-muted mb-2">' . Text::_('PLG_SYSTEM_BETTERCATEGORIES_TOOLS_SETTINGS_DESC') . '</p>'
                . '<div class="bcat-tools-buttons">' . $btn('export', 'EXPORT_ALL', 'btn-primary') . $btn('export_styles', 'EXPORT_STYLES') . $btn('import', 'IMPORT') . '</div>'
                . '<label class="bcat-tools-check"><input type="checkbox" data-bctools-styles> ' . Text::_('PLG_SYSTEM_BETTERCATEGORIES_TOOLS_IMPORT_STYLES_ONLY') . '</label>'
                . '<input type="file" accept=".json,application/json" hidden data-bctools-file>';
        }

        return '<div class="bcat-tools" data-bctools="' . htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') . '" data-bctools-url="' . $url . '">'
            . '<strong class="d-block mb-1">' . Text::_('PLG_SYSTEM_BETTERCATEGORIES_TOOLS_' . ($mode === 'thumbs' ? 'THUMBS' : 'SETTINGS') . '_TITLE') . '</strong>'
            . $body . '<div class="bcat-tools-status small" aria-live="polite"></div></div>';
    }

    protected function getLabel()
    {
        return '';
    }
}

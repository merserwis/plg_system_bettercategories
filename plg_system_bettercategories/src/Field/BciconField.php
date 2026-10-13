<?php

/**
 * @package     Merserwis.Plugin
 * @subpackage  System.bettercategories
 *
 * An icon: Font Awesome Free classes chosen in a searchable window ("fa-solid fa-fire"), or classes
 * of another icon font the site loads, typed in ("zmdi zmdi-fire"). A file of the media manager is a
 * separate media field next to it.
 */

namespace Merserwis\Plugin\System\BetterCategories\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Merserwis\Plugin\System\BetterCategories\Extension\BetterCategories;

class BciconField extends FormField
{
    protected $type = 'Bcicon';

    protected function getInput()
    {
        $wa  = Factory::getApplication()->getDocument()->getWebAssetManager();
        $ver = fn (string $file): string => BetterCategories::ASSET_VERSION . '.' . (int) @filemtime(JPATH_ROOT . '/media/plg_system_bettercategories/' . $file);
        $wa->registerAndUseStyle('plg_system_bettercategories.admin', 'plg_system_bettercategories/admin.css', ['version' => $ver('css/admin.css')]);
        $wa->registerAndUseScript('plg_system_bettercategories.icons', 'plg_system_bettercategories/icons.js', ['version' => $ver('js/icons.js')], ['defer' => true], ['core']);
        foreach (['CHOOSE', 'CLEAR', 'SEARCH', 'ALL', 'SOLID', 'REGULAR', 'BRANDS', 'MORE', 'NONE_FOUND', 'TITLE', 'CLOSE', 'COUNT', 'HINT'] as $key) {
            Text::script('PLG_SYSTEM_BETTERCATEGORIES_ICON_' . $key);
        }
        $e    = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $base = rtrim(Uri::root(true), '/') . '/media/plg_system_bettercategories/icons/fa';

        return '<div class="bcicon" data-bcicon data-bcicon-base="' . $e($base) . '">'
            . '<span class="bcicon-preview" data-bcicon-preview aria-hidden="true"></span>'
            . '<input type="text" class="form-control bcicon-input" name="' . $e($this->name) . '" id="' . $e($this->id) . '" value="' . $e($this->value) . '"'
            . ' placeholder="' . $e(Text::_('PLG_SYSTEM_BETTERCATEGORIES_ICON_HINT')) . '" autocomplete="off" spellcheck="false" data-bcicon-input>'
            . '<button type="button" class="btn btn-sm btn-primary" data-bcicon-open title="' . $e(Text::_('PLG_SYSTEM_BETTERCATEGORIES_ICON_CHOOSE')) . '">'
            . '<span class="icon-search" aria-hidden="true"></span><span class="visually-hidden">' . $e(Text::_('PLG_SYSTEM_BETTERCATEGORIES_ICON_CHOOSE')) . '</span></button>'
            . '<button type="button" class="btn btn-sm btn-outline-secondary" data-bcicon-clear title="' . $e(Text::_('PLG_SYSTEM_BETTERCATEGORIES_ICON_CLEAR')) . '">'
            . '<span class="icon-times" aria-hidden="true"></span><span class="visually-hidden">' . $e(Text::_('PLG_SYSTEM_BETTERCATEGORIES_ICON_CLEAR')) . '</span></button>'
            . '</div>';
    }
}

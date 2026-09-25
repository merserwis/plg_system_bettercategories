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

/**
 * Live preview column of the plugin settings: the list rendered by the plugin itself (com_ajax)
 * from the unsaved form values, in an iframe that can be switched to tablet and phone widths.
 */
class BcpreviewField extends FormField
{
    protected $type = 'Bcpreview';

    protected function getInput()
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('plg_system_bettercategories.admin', 'plg_system_bettercategories/admin.css');
        $wa->registerAndUseScript('plg_system_bettercategories.preview', 'plg_system_bettercategories/preview.js', [], ['defer' => true], ['core']);

        $devices = '';
        foreach (['desktop' => 'PLG_SYSTEM_BETTERCATEGORIES_PREVIEW_DESKTOP', 'tablet' => 'PLG_SYSTEM_BETTERCATEGORIES_PREVIEW_TABLET', 'mobile' => 'PLG_SYSTEM_BETTERCATEGORIES_PREVIEW_MOBILE'] as $device => $label) {
            $devices .= '<button type="button" class="btn btn-sm ' . ($device === 'desktop' ? 'btn-primary' : 'btn-outline-secondary') . '" data-bcat-device="' . $device . '">'
                . Text::_($label) . '</button>';
        }

        return '<div class="bcat-preview" data-bcat-url="index.php?option=com_ajax&amp;plugin=bettercategories&amp;group=system&amp;format=json">'
            . '<div class="bcat-preview-head"><strong>' . Text::_('PLG_SYSTEM_BETTERCATEGORIES_PREVIEW') . '</strong>'
            . '<div class="btn-group" role="group">' . $devices . '</div></div>'
            . '<label class="bcat-preview-cat">' . Text::_('PLG_SYSTEM_BETTERCATEGORIES_PREVIEW_CATEGORY')
            . ' <select class="form-select form-select-sm" data-bcat-category><option value="0">…</option></select></label>'
            . '<div class="bcat-preview-frame"><iframe title="' . Text::_('PLG_SYSTEM_BETTERCATEGORIES_PREVIEW') . '" loading="lazy"></iframe></div>'
            . '<div class="bcat-preview-status small text-muted" aria-live="polite"></div>'
            . '</div>';
    }

    protected function getLabel()
    {
        return '';
    }
}

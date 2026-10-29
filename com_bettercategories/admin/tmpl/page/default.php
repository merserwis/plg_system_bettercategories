<?php

/**
 * @package     Merserwis.Component
 * @subpackage  com_bettercategories
 *
 * A page of settings: the fieldsets of the page as tabs (vertical for the configuration), or the one
 * fieldset alone. data-bc-partial: the form holds the settings of this page only, so the live preview and
 * the tools of the plugin take the others from the saved settings.
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Merserwis\Component\BetterCategories\Administrator\Helper\Catalog;

/** @var \Merserwis\Component\BetterCategories\Administrator\View\Page\HtmlView $this */

// a tab as Joomla's form of the plugin draws it (fieldset.options-form, its description, div.form-grid): the plugin's styles
// put the live preview in a column of its own beside the settings
$fieldset = function (string $name, string $label) {
    $html = '<fieldset id="fieldset-' . $this->escape($name) . '" class="options-form"><legend>' . Text::_($label) . '</legend>';
    if (($this->descriptions[$name] ?? '') !== '') {
        $html .= '<div class="alert alert-info"><span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden">' . Text::_('INFO') . '</span> '
            . Text::_($this->descriptions[$name]) . '</div>';
    }

    return $html . '<div class="form-grid">' . $this->form->renderFieldset($name) . '</div></fieldset>';
};

$page   = $this->page;
$names  = array_keys($this->fieldsets);
$tabsId = 'bcc-tabs-' . $page;
// the tab where a part is switched on (the "off" note links to it)
$settings = (string) (Catalog::page($page)['settings'] ?? '');
if ($settings === '' || !\in_array($settings, $names, true)) {
    $settings = $names[0] ?? '';
}
?>
<form action="<?php echo Route::_('index.php?option=com_bettercategories&view=page&page=' . $page); ?>" method="post" name="adminForm" id="adminForm"
    class="form-validate bcc-page bcc-page-<?php echo $this->escape($page); ?>" data-cancel-task="page.cancel" data-bc-partial="1" data-bc-fields="<?php echo $this->escape(implode(',', $this->names)); ?>"
    aria-label="<?php echo $this->escape(Text::_('COM_BETTERCATEGORIES_PAGE_' . strtoupper($page))); ?>">

    <?php if (!$this->plugin || !(int) $this->plugin->enabled) : ?>
        <div class="alert alert-warning">
            <?php echo Text::_('COM_BETTERCATEGORIES_PLUGIN_OFF'); ?>
            <a href="<?php echo Route::_('index.php?option=com_bettercategories'); ?>"><?php echo Text::_('COM_BETTERCATEGORIES_MENU_CPANEL'); ?></a>
        </div>
    <?php endif; ?>

    <p class="bcc-page-desc"><?php echo Text::_('COM_BETTERCATEGORIES_PAGE_' . strtoupper($page) . '_DESC'); ?></p>

    <?php if ($this->state === 'off') : ?>
        <div class="alert alert-info">
            <?php echo Text::_('COM_BETTERCATEGORIES_TOOL_OFF'); ?>
            <?php if ($settings !== '' && ($this->active ?: ($names[0] ?? '')) !== $settings) : ?>
                <a href="<?php echo Route::_('index.php?option=com_bettercategories&view=page&page=' . $page . '&tab=' . $settings); ?>"><?php echo Text::_('COM_BETTERCATEGORIES_TOOL_OFF_LINK'); ?></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!$this->form) : ?>
        <div class="alert alert-danger"><?php echo Text::_('COM_BETTERCATEGORIES_ERROR_PAGE'); ?></div>
    <?php elseif (\count($names) === 1) : ?>
        <?php echo $fieldset($names[0], $this->fieldsets[$names[0]]); ?>
    <?php else : ?>
        <?php
        $options = ['active' => $this->active ?: $names[0], 'breakpoint' => 768, 'orientation' => $page === 'config' ? 'vertical' : 'horizontal'];
        if ($this->active === '') {
            // the tab last open on this page comes back, unless the address names one
            $options['recall'] = true;
        }
        echo HTMLHelper::_('uitab.startTabSet', $tabsId, $options);
        foreach ($this->fieldsets as $name => $label) {
            echo HTMLHelper::_('uitab.addTab', $tabsId, $name, Text::_($label));
            echo $fieldset($name, $label);
            echo HTMLHelper::_('uitab.endTab');
        }
        echo HTMLHelper::_('uitab.endTabSet');
        ?>
    <?php endif; ?>

    <input type="hidden" name="task" value="">
    <input type="hidden" name="page" value="<?php echo $this->escape($page); ?>">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

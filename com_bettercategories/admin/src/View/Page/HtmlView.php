<?php

/**
 * @package     Merserwis.Component
 * @subpackage  com_bettercategories
 */

namespace Merserwis\Component\BetterCategories\Administrator\View\Page;

\defined('_JEXEC') or die;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Merserwis\Component\BetterCategories\Administrator\Helper\Catalog;
use Merserwis\Component\BetterCategories\Administrator\Helper\Settings;

/**
 * A page of settings: a part of the plugin (its tabs, each with the live preview where it has one) or the
 * configuration (a vertical tab for each part that is set once). The fields are the plugin's own, so their
 * help, the preview, the icon picker and the tools work as they did in the plugin's settings.
 */
class HtmlView extends BaseHtmlView
{
    protected string $page = '';

    protected ?Form $form = null;

    /** @var array<string, string> fieldset => label */
    protected array $fieldsets = [];

    /** @var array<string, string> fieldset => description (a language key, '' for none) */
    protected array $descriptions = [];

    protected string $active = '';

    protected string $state = '';

    protected ?object $plugin = null;

    protected bool $canSave = false;

    /** @var list<string> the settings of this page (the live preview takes the others from the saved settings) */
    protected array $names = [];

    public function display($tpl = null): void
    {
        // also when reached by another task than display (page.display, setup.display): the settings are the plugin's
        if (!$this->getCurrentUser()->authorise('core.manage', 'com_plugins')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        Settings::loadLanguage();
        $app             = Factory::getApplication();
        $this->page      = $app->getInput()->getCmd('page', '');
        $this->plugin    = Settings::plugin();
        // the pages are made of the plugin's own fields: without the plugin there is nothing to show or to save
        $this->form      = Settings::pluginReady() ? Settings::form($this->page) : null;
        $info               = Settings::fieldsetInfo($this->page);
        $this->fieldsets    = array_map(fn (array $set) => $set['label'], $info);
        $this->descriptions = array_map(fn (array $set) => $set['description'], $info);
        $tab             = $app->getInput()->getCmd('tab', '');
        $this->active    = isset($this->fieldsets[$tab]) ? $tab : '';
        $params          = Settings::params();
        $this->state     = Catalog::state(Catalog::page($this->page)['on'] ?? [], $params, Settings::defaults());
        $this->canSave   = $this->getCurrentUser()->authorise('core.edit', 'com_plugins');

        if ($this->form) {
            $this->names = Settings::fieldNames($this->form);
            // values typed before a save that did not pass come back
            $kept = $app->getUserState('com_bettercategories.page.' . $this->page);
            $this->form->bind(['params' => \is_array($kept) ? array_replace($params->toArray(), $kept) : $params->toArray()]);
        }

        $wa = $this->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_bettercategories');
        $wa->useStyle('com_bettercategories.admin')->useScript('keepalive')->useScript('form.validate');
        if ($this->form) {
            // the help ("?") of every setting and the tools of the rows of filters, as in the plugin's own form
            \Merserwis\Plugin\System\BetterCategories\Field\BctoolsField::assets();
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        $icon = Catalog::page($this->page)['icon'] ?? 'folder-open';
        ToolbarHelper::title(Text::_('COM_BETTERCATEGORIES') . ': ' . Text::_('COM_BETTERCATEGORIES_PAGE_' . strtoupper($this->page)), $icon);
        $toolbar = $this->getDocument()->getToolbar();
        if ($this->canSave && $this->form) {
            $toolbar->apply('page.apply');
            $toolbar->save('page.save');
        }
        $toolbar->cancel('page.cancel', 'JTOOLBAR_CLOSE');
    }
}

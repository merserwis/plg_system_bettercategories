<?php

/**
 * @package     Merserwis.Component
 * @subpackage  com_bettercategories
 */

namespace Merserwis\Component\BetterCategories\Administrator\View\Cpanel;

\defined('_JEXEC') or die;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Merserwis\Component\BetterCategories\Administrator\Helper\Catalog;
use Merserwis\Component\BetterCategories\Administrator\Helper\Settings;

/**
 * The control panel: the parts of the plugin as tiles in sections (with a dot that says whether each is on),
 * the configuration as a section of its own, the quick setup offered first on a fresh site, and on the side
 * the state of Better Categories and the buttons used most.
 */
class HtmlView extends BaseHtmlView
{
    /** @var array<string, list<array>> */
    protected array $tiles = [];

    protected ?object $plugin = null;

    protected bool $isNew = false;

    protected bool $gridbox = false;

    /** @var array{apps: int, categories: int}|null */
    protected ?array $store = null;

    protected int $on = 0;

    protected int $total = 0;

    protected string $version = '';

    protected bool $canEdit = false;

    protected bool $canClear = false;

    public function display($tpl = null): void
    {
        // also when reached by another task than display (page.display, setup.display): the settings are the plugin's
        if (!$this->getCurrentUser()->authorise('core.manage', 'com_plugins')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        Settings::loadLanguage();
        $this->plugin  = Settings::plugin();
        $params        = Settings::params();
        $this->gridbox = Settings::hasGridbox();
        $this->store   = Settings::storeFigures();
        $this->tiles   = Catalog::tiles($params, Settings::defaults());
        $this->isNew   = Settings::isNew($params);
        foreach ($this->tiles as $tiles) {
            foreach ($tiles as $tile) {
                if ($tile['state'] !== '') {
                    $this->total++;
                    $this->on += $tile['state'] === 'on' ? 1 : 0;
                }
            }
        }
        $this->version  = (string) (Settings::manifest()?->version ?? '');
        $user           = $this->getCurrentUser();
        $this->canEdit  = $user->authorise('core.edit.state', 'com_plugins');
        // the button "clear the cache" is answered by the plugin (not offered while it is off), for who may change its settings
        $this->canClear = Settings::pluginReady() && $user->authorise('core.edit', 'com_plugins');

        $wa = $this->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_bettercategories');
        $wa->useStyle('com_bettercategories.admin');
        if ($this->canClear) {
            $this->getDocument()->addScriptOptions('com_bettercategories.cpanel', [
                'ajax'  => 'index.php?option=com_ajax&plugin=bettercategories&group=system&format=json',
                'texts' => ['CACHE_CLEARED' => Text::_('COM_BETTERCATEGORIES_CACHE_CLEARED'), 'FAILED' => Text::_('COM_BETTERCATEGORIES_ERROR_ACTION')],
            ]);
            $wa->useScript('com_bettercategories.cpanel');
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_BETTERCATEGORIES') . ': ' . Text::_('COM_BETTERCATEGORIES_MENU_CPANEL'), 'folder-open');
        $toolbar = $this->getDocument()->getToolbar();
        $toolbar->linkButton('wand', 'COM_BETTERCATEGORIES_MENU_SETUP')->url('index.php?option=com_bettercategories&view=setup');
        $toolbar->linkButton('cog', 'COM_BETTERCATEGORIES_MENU_CONFIG')->url('index.php?option=com_bettercategories&view=page&page=config');
    }
}

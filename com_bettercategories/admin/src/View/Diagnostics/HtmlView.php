<?php

/**
 * @package     Merserwis.Component
 * @subpackage  com_bettercategories
 */

namespace Merserwis\Component\BetterCategories\Administrator\View\Diagnostics;

\defined('_JEXEC') or die;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Merserwis\Component\BetterCategories\Administrator\Helper\Diagnostics;
use Merserwis\Component\BetterCategories\Administrator\Helper\Settings;

/**
 * The Diagnostics page: the checks in sections, the latest errors of the site and of the plugin, the report to
 * copy, and safe mode turned on or off.
 */
class HtmlView extends BaseHtmlView
{
    /** @var array<string, list<array>> */
    protected array $sections = [];

    /** @var list<array> */
    protected array $errors = [];

    /** @var list<array> */
    protected array $logs = [];

    protected string $report = '';

    protected bool $safeMode = false;

    protected bool $canSwitch = false;

    /** @var array{ok: int, warn: int, error: int} */
    protected array $counts = ['ok' => 0, 'warn' => 0, 'error' => 0];

    public function display($tpl = null): void
    {
        if (!$this->getCurrentUser()->authorise('core.manage', 'com_plugins')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        Settings::loadLanguage();
        $this->sections  = Diagnostics::checks();
        $this->errors    = Diagnostics::entries(Diagnostics::ERRORS_LOG, 10);
        $this->logs      = Diagnostics::entries(Diagnostics::PLUGIN_LOG, 10);
        $this->report    = Diagnostics::report($this->sections, $this->errors, $this->logs);
        $this->safeMode  = Diagnostics::safeMode();
        $this->canSwitch = $this->getCurrentUser()->authorise('core.edit.state', 'com_plugins');
        foreach ($this->sections as $rows) {
            foreach ($rows as $row) {
                if (isset($this->counts[$row['status']])) {
                    $this->counts[$row['status']]++;
                }
            }
        }

        $wa = $this->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_bettercategories');
        $wa->useStyle('com_bettercategories.admin')->useScript('com_bettercategories.diagnostics');
        $this->getDocument()->addScriptOptions('com_bettercategories.diagnostics', ['texts' => ['COPIED' => Text::_('COM_BETTERCATEGORIES_DIAG_COPIED')]]);

        ToolbarHelper::title(Text::_('COM_BETTERCATEGORIES') . ': ' . Text::_('COM_BETTERCATEGORIES_MENU_DIAGNOSTICS'), 'heartbeat');
        $toolbar = $this->getDocument()->getToolbar();
        $toolbar->linkButton('refresh', 'COM_BETTERCATEGORIES_DIAG_REFRESH')->url('index.php?option=com_bettercategories&view=diagnostics');
        $toolbar->linkButton('arrow-left', 'COM_BETTERCATEGORIES_MENU_CPANEL')->url('index.php?option=com_bettercategories');

        parent::display($tpl);
    }
}

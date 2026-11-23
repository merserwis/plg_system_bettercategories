<?php

/**
 * @package     Merserwis.Component
 * @subpackage  com_bettercategories
 */

namespace Merserwis\Component\BetterCategories\Administrator\View\Stats;

\defined('_JEXEC') or die;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;
use Merserwis\Component\BetterCategories\Administrator\Helper\Settings;
use Merserwis\Plugin\System\BetterCategories\Filter\Stats;

/**
 * The statistics of the filters (1.11.0): which filters and values the visitors chose in a period, in which
 * categories, and how often a choice left no product, against the period before. Counted by the plugin
 * (Filter\Stats); here only read.
 */
class HtmlView extends BaseHtmlView
{
    /** the periods offered, in days: the longest and the one before it fit in the history kept (400 days) */
    public const PERIODS = [7, 30, 90, 180];

    protected int $days = 30;

    protected array $summary = [];

    protected string $from = '';

    protected string $to = '';

    protected bool $counting = false;

    /** Better Search's e-mail report: null = Better Search not installed, false = its report off, true = on */
    protected ?bool $report = null;

    protected bool $inReport = false;

    public function display($tpl = null): void
    {
        if (!$this->getCurrentUser()->authorise('core.manage', 'com_plugins')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        Settings::loadLanguage();
        $params         = Settings::params();
        $this->counting = (bool) $params->get('filters_enabled', 0) && (bool) $params->get('filter_stats', 1);
        $this->inReport = (bool) $params->get('filter_stats_report', 1);
        $days           = Factory::getApplication()->getInput()->getInt('days', 30);
        $this->days     = \in_array($days, self::PERIODS, true) ? $days : 30;

        // the days of the site's time zone, as they are counted; today included
        try {
            $zone = new \DateTimeZone((string) Factory::getApplication()->get('offset', 'UTC') ?: 'UTC');
        } catch (\Throwable $e) {
            $zone = new \DateTimeZone('UTC');
        }
        $today      = new \DateTimeImmutable('today', $zone);
        $this->to   = $today->modify('+1 day')->format('Y-m-d');
        $this->from = $today->modify('-' . ($this->days - 1) . ' days')->format('Y-m-d');
        $db         = Factory::getContainer()->get(DatabaseInterface::class);
        $this->summary = class_exists(Stats::class) ? Stats::summary($db, $this->from, $this->to, 25)
            : ['views' => 0, 'empty' => 0, 'prevViews' => 0, 'prevEmpty' => 0, 'filters' => [], 'values' => [], 'categories' => [], 'emptyValues' => []];

        try {
            $bs = $db->setQuery($db->createQuery()->select($db->quoteName('params'))->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('bettersearch')))->loadResult();
            $this->report = $bs === null ? null : (bool) ((json_decode((string) $bs, true) ?: [])['report_enabled'] ?? 0);
        } catch (\Throwable $e) {
            $this->report = null;
        }

        $wa = $this->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_bettercategories');
        $wa->useStyle('com_bettercategories.admin');

        ToolbarHelper::title(Text::_('COM_BETTERCATEGORIES') . ': ' . Text::_('COM_BETTERCATEGORIES_MENU_STATS'), 'chart');
        $toolbar = $this->getDocument()->getToolbar();
        $toolbar->linkButton('filter', 'COM_BETTERCATEGORIES_MENU_FILTERS')->url('index.php?option=com_bettercategories&view=page&page=filters');
        $toolbar->linkButton('arrow-left', 'COM_BETTERCATEGORIES_MENU_CPANEL')->url('index.php?option=com_bettercategories');

        parent::display($tpl);
    }

    /** "▲ +12%", "▼ -5%", "new" or "–": a rise is green when it is good news */
    public function change(int $now, int $before, bool $good = true): string
    {
        if ($before <= 0) {
            return $now > 0 ? '<span class="text-success">' . Text::_('COM_BETTERCATEGORIES_STATS_NEW') . '</span>' : '–';
        }
        $pct = (int) round(($now - $before) / $before * 100);

        return $pct === 0 ? '±0%' : '<span class="' . (($pct > 0) === $good ? 'text-success' : 'text-danger') . '">' . ($pct > 0 ? '▲ +' : '▼ ') . $pct . '%</span>';
    }
}

<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$s     = $this->summary;
$n     = fn ($v) => number_format((float) $v, 0, ',', "\u{00A0}");
$label = fn (array $r, string $key = 'label') => $this->escape($r[$key] !== '' ? $r[$key] : ($r['value'] ?? $r['filter'] ?? '—'));
$table = function (string $title, string $desc, array $head, array $rows): string {
    $html = '<section class="card mb-3"><h2 class="card-header h4 m-0">' . $title . '</h2><div class="card-body p-0">'
        . ($desc !== '' ? '<p class="small text-muted px-3 pt-3 mb-2">' . $desc . '</p>' : '')
        . '<table class="table table-sm table-striped mb-0"><thead><tr>';
    foreach ($head as [$h, $right]) {
        $html .= '<th scope="col"' . ($right ? ' class="text-end"' : '') . '>' . $h . '</th>';
    }

    return $html . '</tr></thead><tbody>' . implode('', $rows) . '</tbody></table></div></section>';
};
?>
<div class="bcc-stats">
    <p class="bcc-page-desc"><?php echo Text::_('COM_BETTERCATEGORIES_STATS_DESC'); ?></p>

    <?php if (!$this->counting) : ?>
        <div class="alert alert-warning"><?php echo Text::_('COM_BETTERCATEGORIES_STATS_OFF'); ?>
            <a href="<?php echo Route::_('index.php?option=com_bettercategories&view=page&page=filters'); ?>"><?php echo Text::_('COM_BETTERCATEGORIES_MENU_FILTERS'); ?></a></div>
    <?php endif; ?>

    <?php if ($this->report === null) : ?>
        <div class="alert alert-light"><?php echo Text::_('COM_BETTERCATEGORIES_STATS_REPORT_NO_BS'); ?></div>
    <?php elseif (!$this->report) : ?>
        <div class="alert alert-light"><?php echo Text::_('COM_BETTERCATEGORIES_STATS_REPORT_BS_OFF'); ?></div>
    <?php else : ?>
        <div class="alert alert-light"><?php echo Text::_($this->inReport ? 'COM_BETTERCATEGORIES_STATS_REPORT_ON' : 'COM_BETTERCATEGORIES_STATS_REPORT_OFF'); ?></div>
    <?php endif; ?>

    <nav class="mb-3" aria-label="<?php echo Text::_('COM_BETTERCATEGORIES_STATS_PERIOD'); ?>">
        <div class="btn-group" role="group">
            <?php foreach ($this::PERIODS as $days) : ?>
                <a class="btn <?php echo $days === $this->days ? 'btn-primary' : 'btn-outline-primary'; ?>"
                   href="<?php echo Route::_('index.php?option=com_bettercategories&view=stats&days=' . $days); ?>"
                   <?php echo $days === $this->days ? 'aria-current="page"' : ''; ?>><?php echo Text::sprintf('COM_BETTERCATEGORIES_STATS_DAYS', $days); ?></a>
            <?php endforeach; ?>
        </div>
    </nav>

    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body">
            <div class="small text-muted"><?php echo Text::_('COM_BETTERCATEGORIES_STATS_VIEWS'); ?></div>
            <div class="fs-3 fw-bold"><?php echo $n($s['views']); ?></div>
            <div class="small"><?php echo $this->change($s['views'], $s['prevViews']); ?></div>
        </div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body">
            <div class="small text-muted"><?php echo Text::_('COM_BETTERCATEGORIES_STATS_EMPTY'); ?></div>
            <div class="fs-3 fw-bold"><?php echo $n($s['empty']); ?></div>
            <div class="small"><?php echo $s['views'] ? round(100 * $s['empty'] / $s['views'], 1) . '% · ' : ''; ?><?php echo $this->change($s['empty'], $s['prevEmpty'], false); ?></div>
        </div></div></div>
    </div>

    <?php if (!$s['views']) : ?>
        <div class="alert alert-info"><?php echo Text::_('COM_BETTERCATEGORIES_STATS_NO_DATA'); ?></div>
    <?php else : ?>
        <div class="row">
            <div class="col-xl-6">
                <?php
                $rows = [];
                foreach ($s['filters'] as $r) {
                    $rows[] = '<tr><td>' . $label($r) . ' <code class="small">' . $this->escape($r['filter']) . '</code></td><td class="text-end">' . $n($r['uses'])
                        . '</td><td class="text-end">' . $this->change($r['uses'], $r['prev']) . '</td></tr>';
                }
                echo $table(Text::_('COM_BETTERCATEGORIES_STATS_FILTERS'), Text::_('COM_BETTERCATEGORIES_STATS_FILTERS_DESC'),
                    [[Text::_('COM_BETTERCATEGORIES_STATS_COL_FILTER'), false], [Text::_('COM_BETTERCATEGORIES_STATS_COL_USES'), true], [Text::_('COM_BETTERCATEGORIES_STATS_COL_CHANGE'), true]], $rows);

                $rows = [];
                foreach ($s['categories'] as $r) {
                    $rows[] = '<tr><td>' . $label($r) . '</td><td class="text-end">' . $n($r['uses']) . '</td><td class="text-end">'
                        . ($r['empty'] ? '<span class="text-danger fw-bold">' . $n($r['empty']) . '</span>' : '0') . '</td></tr>';
                }
                echo $table(Text::_('COM_BETTERCATEGORIES_STATS_CATEGORIES'), '',
                    [[Text::_('COM_BETTERCATEGORIES_STATS_COL_CATEGORY'), false], [Text::_('COM_BETTERCATEGORIES_STATS_COL_USES'), true], [Text::_('COM_BETTERCATEGORIES_STATS_COL_EMPTY'), true]], $rows);
                ?>
            </div>
            <div class="col-xl-6">
                <?php
                $rows = [];
                foreach ($s['values'] as $r) {
                    $rows[] = '<tr><td>' . $label($r) . '</td><td>' . $label($r, 'value_label') . '</td><td class="text-end">' . $n($r['uses']) . '</td></tr>';
                }
                echo $table(Text::_('COM_BETTERCATEGORIES_STATS_VALUES'), Text::_('COM_BETTERCATEGORIES_STATS_VALUES_DESC'),
                    [[Text::_('COM_BETTERCATEGORIES_STATS_COL_FILTER'), false], [Text::_('COM_BETTERCATEGORIES_STATS_COL_VALUE'), false], [Text::_('COM_BETTERCATEGORIES_STATS_COL_USES'), true]], $rows);

                if ($s['emptyValues']) {
                    $rows = [];
                    foreach ($s['emptyValues'] as $r) {
                        $rows[] = '<tr><td>' . $label($r) . '</td><td>' . $label($r, 'value_label') . '</td><td class="text-end"><span class="text-danger fw-bold">'
                            . $n($r['empty']) . '</span> / ' . $n($r['uses']) . '</td></tr>';
                    }
                    echo $table(Text::_('COM_BETTERCATEGORIES_STATS_EMPTY_VALUES'), Text::_('COM_BETTERCATEGORIES_STATS_EMPTY_VALUES_DESC'),
                        [[Text::_('COM_BETTERCATEGORIES_STATS_COL_FILTER'), false], [Text::_('COM_BETTERCATEGORIES_STATS_COL_VALUE'), false], [Text::_('COM_BETTERCATEGORIES_STATS_COL_EMPTY'), true]], $rows);
                }
                ?>
            </div>
        </div>
    <?php endif; ?>
    <p class="small text-muted"><?php echo Text::_('COM_BETTERCATEGORIES_STATS_PRIVACY'); ?></p>
</div>

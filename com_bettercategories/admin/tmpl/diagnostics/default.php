<?php

/**
 * @package     Merserwis.Component
 * @subpackage  com_bettercategories
 *
 * The Diagnostics page: a summary, the checks in sections (a row: state, name, value, what to do), the latest
 * "500" errors of the site and lines of the plugin's log, safe mode, and the report to copy for support.
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Merserwis\Component\BetterCategories\Administrator\View\Diagnostics\HtmlView $this */

$icons = ['ok' => 'icon-check', 'warn' => 'icon-warning', 'error' => 'icon-times', 'info' => 'icon-info-circle'];
$text  = fn (string $s): string => preg_match('/^[A-Z0-9_]+$/', $s) ? Text::_($s) : $s;
$list  = function (array $entries, string $empty): string {
    if (!$entries) {
        return '<p class="text-muted mb-0">' . Text::_($empty) . '</p>';
    }
    $html = '<ol class="bcc-diag-log list-unstyled mb-0">';
    foreach ($entries as $e) {
        // the stack trace is long: the message first, the rest when opened
        [$head, $rest] = array_pad(explode(' | ', $e['message'], 2), 2, '');
        $html .= '<li><div class="small text-muted">' . $this->escape($e['time'] . ' · ' . $e['where']) . '</div>';
        $html .= $rest === '' ? '<code>' . $this->escape($head) . '</code>'
            : '<details><summary><code>' . $this->escape($head) . '</code></summary><pre class="small mb-0">' . $this->escape(str_replace(' | ', "\n", $rest)) . '</pre></details>';
        $html .= '</li>';
    }

    return $html . '</ol>';
};
?>
<div class="bcc-diag">
    <p class="bcc-page-desc"><?php echo Text::_('COM_BETTERCATEGORIES_DIAG_DESC'); ?></p>

    <div class="alert <?php echo $this->counts['error'] ? 'alert-danger' : ($this->counts['warn'] ? 'alert-warning' : 'alert-success'); ?>">
        <?php echo Text::sprintf('COM_BETTERCATEGORIES_DIAG_SUMMARY', $this->counts['ok'], $this->counts['warn'], $this->counts['error']); ?>
    </div>

    <div class="row">
        <div class="col-xl-8">
            <?php foreach ($this->sections as $section => $rows) : ?>
                <section class="card mb-3">
                    <h2 class="card-header h4 m-0"><?php echo Text::_('COM_BETTERCATEGORIES_DIAG_SECTION_' . strtoupper($section)); ?></h2>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0 bcc-diag-table">
                            <tbody>
                                <?php foreach ($rows as $row) : ?>
                                    <tr class="bcc-diag-<?php echo $this->escape($row['status']); ?>">
                                        <td class="bcc-diag-state"><span class="<?php echo $icons[$row['status']] ?? 'icon-info-circle'; ?>" aria-hidden="true"></span>
                                            <span class="visually-hidden"><?php echo Text::_('COM_BETTERCATEGORIES_DIAG_STATUS_' . strtoupper($row['status'])); ?></span></td>
                                        <th scope="row"><?php echo Text::_($row['label']); ?></th>
                                        <td><?php echo $this->escape($row['value']); ?>
                                            <?php if ($row['hint'] !== '') : ?>
                                                <div class="small bcc-diag-hint"><?php echo $this->escape($text($row['hint'])); ?></div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endforeach; ?>

            <section class="card mb-3">
                <h2 class="card-header h4 m-0"><?php echo Text::_('COM_BETTERCATEGORIES_DIAG_SITE_ERRORS'); ?></h2>
                <div class="card-body">
                    <p class="small text-muted"><?php echo Text::_('COM_BETTERCATEGORIES_DIAG_SITE_ERRORS_DESC'); ?></p>
                    <?php echo $list($this->errors, 'COM_BETTERCATEGORIES_DIAG_NO_ERRORS'); ?>
                </div>
            </section>

            <section class="card mb-3">
                <h2 class="card-header h4 m-0"><?php echo Text::_('COM_BETTERCATEGORIES_DIAG_PLUGIN_LOG'); ?></h2>
                <div class="card-body">
                    <?php echo $list($this->logs, 'COM_BETTERCATEGORIES_DIAG_NO_ERRORS'); ?>
                </div>
            </section>
        </div>

        <div class="col-xl-4">
            <section class="card mb-3 <?php echo $this->safeMode ? 'bcc-check' : ''; ?>">
                <h2 class="card-header h4 m-0"><span class="icon-shield-alt" aria-hidden="true"></span> <?php echo Text::_('COM_BETTERCATEGORIES_DIAG_SAFE'); ?></h2>
                <div class="card-body">
                    <p><?php echo Text::_($this->safeMode ? 'COM_BETTERCATEGORIES_DIAG_SAFE_IS_ON' : 'COM_BETTERCATEGORIES_DIAG_SAFE_IS_OFF'); ?></p>
                    <p class="small text-muted"><?php echo Text::_('COM_BETTERCATEGORIES_DIAG_SAFE_DESC'); ?></p>
                    <?php if ($this->canSwitch) : ?>
                        <form action="<?php echo Route::_('index.php?option=com_bettercategories&view=diagnostics'); ?>" method="post">
                            <button type="submit" class="btn <?php echo $this->safeMode ? 'btn-success' : 'btn-warning'; ?>">
                                <?php echo Text::_($this->safeMode ? 'COM_BETTERCATEGORIES_DIAG_SAFE_TURN_OFF' : 'COM_BETTERCATEGORIES_DIAG_SAFE_TURN_ON'); ?></button>
                            <input type="hidden" name="task" value="<?php echo $this->safeMode ? 'diagnostics.safeOff' : 'diagnostics.safeOn'; ?>">
                            <?php echo HTMLHelper::_('form.token'); ?>
                        </form>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card mb-3">
                <h2 class="card-header h4 m-0"><?php echo Text::_('COM_BETTERCATEGORIES_DIAG_REPORT'); ?></h2>
                <div class="card-body">
                    <p class="small text-muted"><?php echo Text::_('COM_BETTERCATEGORIES_DIAG_REPORT_DESC'); ?></p>
                    <textarea class="form-control font-monospace small mb-2" rows="12" readonly id="bcc-diag-report"><?php echo $this->escape($this->report); ?></textarea>
                    <button type="button" class="btn btn-primary" data-bcc-copy="bcc-diag-report">
                        <span class="icon-copy" aria-hidden="true"></span> <?php echo Text::_('COM_BETTERCATEGORIES_DIAG_COPY'); ?></button>
                </div>
            </section>
        </div>
    </div>
</div>

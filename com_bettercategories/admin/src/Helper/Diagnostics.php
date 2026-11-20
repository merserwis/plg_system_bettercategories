<?php

/**
 * @package     Merserwis.Component
 * @subpackage  com_bettercategories
 *
 * The Diagnostics page (1.9.1): what a site needs for Better Categories, checked in one place — versions,
 * the state of the plugin, the server, Joomla's configuration — and the latest errors: those of the plugin
 * and the "500 Whoops" errors of the whole site, which the plugin keeps (Joomla writes them nowhere by
 * default). Made for sites on a hosting without access to the server's logs: the administrator copies the
 * report and sends it. Reads only; nothing here depends on the plugin's own classes, so the page works
 * with the plugin off, in safe mode or with its files damaged.
 */

namespace Merserwis\Component\BetterCategories\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

final class Diagnostics
{
    /** the plugin's own log (see BetterCategories::LOG_FILE) */
    public const PLUGIN_LOG = 'plg_system_bettercategories.php';

    /** the "500" errors of the site (see the plugin's services/provider.php) */
    public const ERRORS_LOG = 'plg_system_bettercategories_errors.php';

    /** the file that puts the plugin in safe mode (see the plugin's services/provider.php) */
    public static function safeModeFile(): string
    {
        return JPATH_PLUGINS . '/system/bettercategories/safe-mode';
    }

    public static function safeMode(): bool
    {
        return is_file(self::safeModeFile());
    }

    /**
     * Whether the plugin's code can be loaded: '' when it can, else the error (a file broken by an interrupted
     * upload). In safe mode the plugin's classes are not loaded by the request, so this really loads them.
     */
    public static function codeError(): string
    {
        try {
            // every class of the plugin (src/<folder>/<Class>.php), so a damaged file anywhere is found
            $src = JPATH_PLUGINS . '/system/bettercategories/src';
            foreach (glob($src . '/*/*.php') ?: [] as $file) {
                $class = 'Merserwis\\Plugin\\System\\BetterCategories\\' . basename(\dirname($file)) . '\\' . basename($file, '.php');
                if (!class_exists($class) && !interface_exists($class) && !trait_exists($class)) {
                    return Text::_('COM_BETTERCATEGORIES_DIAG_MISSING') . ': ' . self::relative($file);
                }
            }
            if (!is_file($src . '/Extension/BetterCategories.php')) {
                return Text::_('COM_BETTERCATEGORIES_DIAG_MISSING') . ': ' . self::relative($src . '/Extension/BetterCategories.php');
            }
        } catch (\Throwable $e) {
            return \get_class($e) . ': ' . $e->getMessage() . ' @ ' . self::relative($e->getFile()) . ':' . $e->getLine();
        }

        return '';
    }

    /** turns safe mode on or off; an error message, or '' */
    public static function setSafeMode(bool $on): string
    {
        $file = self::safeModeFile();
        if (!is_dir(\dirname($file))) {
            return Text::_('COM_BETTERCATEGORIES_ERROR_PLUGIN');
        }
        // off only when the plugin's code loads: else the next page would end with "500" again
        if (!$on && is_file($file) && ($error = self::codeError()) !== '') {
            return Text::sprintf('COM_BETTERCATEGORIES_DIAG_SAFE_OFF_BROKEN', $error);
        }
        $ok = $on ? (is_file($file) || @file_put_contents($file, '') !== false) : (!is_file($file) || @unlink($file));

        return $ok ? '' : Text::sprintf('COM_BETTERCATEGORIES_DIAG_SAFE_FAILED', self::relative($file));
    }

    /**
     * The checks in sections: section key => rows of [label, value, status (ok, warn, error, info), hint].
     *
     * @return array<string, list<array{label: string, value: string, status: string, hint: string}>>
     */
    public static function checks(): array
    {
        $sections = [];
        foreach (['versions', 'plugin', 'server', 'joomla'] as $section) {
            try {
                $sections[$section] = self::{'check' . ucfirst($section)}();
            } catch (\Throwable $e) {
                $sections[$section] = [self::row('COM_BETTERCATEGORIES_DIAG_FAILED', $e->getMessage(), 'error')];
            }
        }

        return $sections;
    }

    private static function checkVersions(): array
    {
        $rows = [];
        $own  = [];
        foreach (['package' => ['package', 'pkg_bettercategories', ''], 'plugin' => ['plugin', 'bettercategories', 'system'],
            'component' => ['component', 'com_bettercategories', ''], 'module' => ['module', 'mod_bettercategories', '']] as $part => [$type, $element, $folder]) {
            $own[$part] = self::extensionVersion($type, $element, $folder);
        }
        // the module has versions of its own (it changes seldom): only the package, the plugin and the component go together
        $known = array_filter([$own['package'], $own['plugin'], $own['component']], fn ($v) => $v !== null);
        $value = implode(', ', array_map(fn ($part, $v) => Text::_('COM_BETTERCATEGORIES_DIAG_PART_' . strtoupper($part)) . ' ' . ($v ?? '—'), array_keys($own), $own));
        $same  = \count(array_unique($known)) <= 1 && $own['plugin'] !== null;
        $rows[] = self::row('COM_BETTERCATEGORIES_DIAG_BC', $value, $same ? 'ok' : 'warn', $same ? '' : 'COM_BETTERCATEGORIES_DIAG_BC_HINT');

        $rows[] = self::row('COM_BETTERCATEGORIES_DIAG_JOOMLA', JVERSION, version_compare(JVERSION, '6.0', '>=') ? 'ok' : 'warn',
            version_compare(JVERSION, '6.0', '>=') ? '' : 'COM_BETTERCATEGORIES_DIAG_JOOMLA_HINT');
        $rows[] = self::row('COM_BETTERCATEGORIES_DIAG_PHP', PHP_VERSION, version_compare(PHP_VERSION, '8.2', '>=') ? 'ok' : 'error',
            version_compare(PHP_VERSION, '8.2', '>=') ? '' : 'COM_BETTERCATEGORIES_DIAG_PHP_HINT');

        $gridbox = self::extensionVersion('component', 'com_gridbox', '');
        $on      = ComponentHelper::isEnabled('com_gridbox');
        $rows[]  = self::row('COM_BETTERCATEGORIES_DIAG_GRIDBOX', $gridbox === null ? Text::_('COM_BETTERCATEGORIES_DIAG_MISSING')
            : $gridbox . ($on ? '' : ' (' . Text::_('COM_BETTERCATEGORIES_STATE_OFF') . ')'), $gridbox !== null && $on ? 'ok' : 'error',
            $gridbox !== null && $on ? '' : 'COM_BETTERCATEGORIES_NO_GRIDBOX');

        $db     = Factory::getContainer()->get(DatabaseInterface::class);
        $rows[] = self::row('COM_BETTERCATEGORIES_DIAG_DATABASE', $db->getServerType() . ' ' . $db->getVersion(), 'info');

        return $rows;
    }

    private static function checkPlugin(): array
    {
        $rows   = [];
        $plugin = Settings::plugin();
        $on     = $plugin && (int) $plugin->enabled === 1;
        $rows[] = self::row('COM_BETTERCATEGORIES_STATUS_PLUGIN', Text::_($plugin ? ($on ? 'COM_BETTERCATEGORIES_STATUS_PLUGIN_ON' : 'COM_BETTERCATEGORIES_STATUS_PLUGIN_OFF')
            : 'COM_BETTERCATEGORIES_DIAG_MISSING'), $on ? 'ok' : 'error', $plugin ? ($on ? '' : 'COM_BETTERCATEGORIES_PLUGIN_OFF') : 'COM_BETTERCATEGORIES_NO_PLUGIN');

        $safe   = self::safeMode();
        $rows[] = self::row('COM_BETTERCATEGORIES_DIAG_SAFE', Text::_($safe ? 'COM_BETTERCATEGORIES_STATE_ON' : 'COM_BETTERCATEGORIES_STATE_OFF'),
            $safe ? 'warn' : 'ok', $safe ? Text::sprintf('COM_BETTERCATEGORIES_DIAG_SAFE_ON_HINT', self::relative(self::safeModeFile())) : '');

        // in safe mode the plugin's code is not loaded by the site: whether it can be (before turning safe mode off)
        if ($safe) {
            $error  = self::codeError();
            $rows[] = self::row('COM_BETTERCATEGORIES_DIAG_CODE', $error === '' ? Text::_('COM_BETTERCATEGORIES_DIAG_CODE_OK') : $error,
                $error === '' ? 'ok' : 'error', $error === '' ? '' : 'COM_BETTERCATEGORIES_DIAG_FILES_HINT');
        }

        // files an interrupted upload or a partial update leaves out
        $missing = [];
        foreach (['plugins/system/bettercategories/services/provider.php', 'plugins/system/bettercategories/src/Extension/BetterCategories.php',
            'plugins/system/bettercategories/bettercategories.xml', 'media/plg_system_bettercategories/js/tools.js',
            'media/plg_system_bettercategories/css/filters.css', 'administrator/components/com_bettercategories/services/provider.php'] as $file) {
            if (!is_file(JPATH_ROOT . '/' . $file)) {
                $missing[] = $file;
            }
        }
        $rows[] = self::row('COM_BETTERCATEGORIES_DIAG_FILES', $missing ? implode(', ', $missing) : Text::_('COM_BETTERCATEGORIES_DIAG_FILES_OK'),
            $missing ? 'error' : 'ok', $missing ? 'COM_BETTERCATEGORIES_DIAG_FILES_HINT' : '');

        // the stores the plugin works on
        $params = Settings::params();
        $ids    = array_values(array_filter(array_map('intval', explode(',', (string) $params->get('app_ids', '')))));
        $apps   = [];
        try {
            $db   = Factory::getContainer()->get(DatabaseInterface::class);
            $apps = $db->setQuery($db->createQuery()->select($db->quoteName(['id', 'title', 'type']))->from($db->quoteName('#__gridbox_app')))
                ->loadObjectList('id') ?: [];
        } catch (\Throwable $e) {
        }
        $names = [];
        $bad   = [];
        foreach ($ids ?: array_keys(array_filter($apps, fn ($a) => $a->type === 'products')) as $id) {
            if (isset($apps[$id]) && $apps[$id]->type === 'products') {
                $names[] = $apps[$id]->title . ' (' . $id . ')';
            } else {
                $bad[] = (string) $id;
            }
        }
        $value  = ($ids ? '' : Text::_('COM_BETTERCATEGORIES_DIAG_STORES_ALL') . ': ') . ($names ? implode(', ', $names) : '—');
        $rows[] = self::row('COM_BETTERCATEGORIES_STATUS_STORES', $value . ($bad ? ' · ' . Text::sprintf('COM_BETTERCATEGORIES_DIAG_STORES_BAD', implode(', ', $bad)) : ''),
            $bad || !$names ? 'warn' : 'ok', $bad || !$names ? 'COM_BETTERCATEGORIES_DIAG_STORES_HINT' : '');

        return $rows;
    }

    private static function checkServer(): array
    {
        $rows   = [];
        $limit  = (string) ini_get('memory_limit');
        $bytes  = self::bytes($limit);
        $rows[] = self::row('COM_BETTERCATEGORIES_DIAG_MEMORY', $limit, $bytes > 0 && $bytes < 128 * 1048576 ? 'warn' : 'ok',
            $bytes > 0 && $bytes < 128 * 1048576 ? 'COM_BETTERCATEGORIES_DIAG_MEMORY_HINT' : '');
        $rows[] = self::row('COM_BETTERCATEGORIES_DIAG_TIME', (string) ini_get('max_execution_time') . ' s', 'info');

        $gd     = \function_exists('gd_info') ? gd_info() : null;
        $webp   = \function_exists('imagewebp');
        $rows[] = self::row('COM_BETTERCATEGORIES_DIAG_GD', $gd ? (string) ($gd['GD Version'] ?? 'GD') . ($webp ? ', WebP' : '') : Text::_('COM_BETTERCATEGORIES_DIAG_MISSING'),
            $webp ? 'ok' : 'warn', $webp ? '' : 'COM_BETTERCATEGORIES_DIAG_GD_HINT');

        $app = Factory::getApplication();
        foreach (['COM_BETTERCATEGORIES_DIAG_DIR_THUMBS' => JPATH_ROOT . '/media/plg_system_bettercategories/thumbs',
            'COM_BETTERCATEGORIES_DIAG_DIR_CACHE' => (string) ($app->get('cache_path', '') ?: JPATH_SITE . '/cache'),
            'COM_BETTERCATEGORIES_DIAG_DIR_ADMIN_CACHE' => JPATH_ADMINISTRATOR . '/cache',
            'COM_BETTERCATEGORIES_DIAG_DIR_LOGS' => self::logDir()] as $label => $dir) {
            // a folder not made yet (the thumbnails before the first one) is fine when it can be made
            $ok     = is_dir($dir) ? is_writable($dir) : is_writable(\dirname($dir));
            $rows[] = self::row($label, self::relative($dir), $ok ? 'ok' : 'error', $ok ? '' : 'COM_BETTERCATEGORIES_DIAG_DIR_HINT');
        }

        // log files grow without end (deprecated.php with debug on): a full disk stops the site
        $size = 0;
        foreach (glob(self::logDir() . '/*') ?: [] as $file) {
            $size += is_file($file) ? (int) @filesize($file) : 0;
        }
        $rows[] = self::row('COM_BETTERCATEGORIES_DIAG_LOG_SIZE', self::size($size), $size > 100 * 1048576 ? 'warn' : 'ok',
            $size > 100 * 1048576 ? 'COM_BETTERCATEGORIES_DIAG_LOG_SIZE_HINT' : '');

        return $rows;
    }

    private static function checkJoomla(): array
    {
        $app    = Factory::getApplication();
        $rows   = [];
        $debug  = (int) $app->get('debug', 0) === 1;
        $rows[] = self::row('COM_BETTERCATEGORIES_DIAG_DEBUG', Text::_($debug ? 'JON' : 'JOFF'), $debug ? 'warn' : 'ok', $debug ? 'COM_BETTERCATEGORIES_DIAG_DEBUG_HINT' : '');
        $level  = (string) $app->get('error_reporting', 'default');
        $loud   = \in_array($level, ['maximum', 'development'], true);
        $rows[] = self::row('COM_BETTERCATEGORIES_DIAG_ERRORS', $level, $loud ? 'warn' : 'ok', $loud ? 'COM_BETTERCATEGORIES_DIAG_DEBUG_HINT' : '');
        $every  = (int) $app->get('log_everything', 0) === 1;
        $rows[] = self::row('COM_BETTERCATEGORIES_DIAG_LOG_EVERYTHING', Text::_($every ? 'JON' : 'JOFF'), 'info');
        $rows[] = self::row('COM_BETTERCATEGORIES_DIAG_CACHING', Text::_((int) $app->get('caching', 0) ? 'JON' : 'JOFF'), 'info');
        $rows[] = self::row('COM_BETTERCATEGORIES_DIAG_SEF', Text::_((int) $app->get('sef', 1) ? 'JON' : 'JOFF') . ' / '
            . Text::_('COM_BETTERCATEGORIES_DIAG_SEF_REWRITE') . ': ' . Text::_((int) $app->get('sef_rewrite', 0) ? 'JON' : 'JOFF'), 'info');

        return $rows;
    }

    /**
     * The latest entries of a log file of the plugin, newest first: [time, place, message]. Only the end of the
     * file is read (a log may be large).
     *
     * @return list<array{time: string, where: string, message: string}>
     */
    public static function entries(string $name, int $max = 10): array
    {
        $file = self::logDir() . '/' . $name;
        if (!is_file($file) || !is_readable($file)) {
            return [];
        }
        $size = (int) @filesize($file);
        $fh   = @fopen($file, 'rb');
        if (!$fh) {
            return [];
        }
        if ($size > 131072) {
            fseek($fh, -131072, SEEK_END);
            // the read starts inside a line: it is dropped
            fgets($fh);
        }
        $text = (string) stream_get_contents($fh);
        fclose($fh);

        $out = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || str_starts_with($line, '<?php')) {
                continue;
            }
            $cols = explode("\t", $line);
            if ($name === self::ERRORS_LOG) {
                // time, place, message
                if (\count($cols) < 3 || !preg_match('/^\d{4}-\d\d-\d\dT/', $cols[0])) {
                    continue;
                }
                $out[] = ['time' => $cols[0], 'where' => $cols[1], 'message' => implode(' ', \array_slice($cols, 2))];
            } else {
                // Joomla's formatted text: datetime, priority, client ip, category, message (the ip is left out)
                if (\count($cols) < 5 || !preg_match('/^\d{4}-\d\d-\d\d/', $cols[0])) {
                    continue;
                }
                $out[] = ['time' => $cols[0], 'where' => $cols[1], 'message' => implode(' ', \array_slice($cols, 4))];
            }
        }

        return array_reverse(\array_slice($out, -$max));
    }

    /** the report to copy: every check and the latest errors, as plain text (no addresses of visitors) */
    public static function report(array $sections, array $errors, array $logs): string
    {
        $lines = ['Better Categories for Gridbox — ' . Text::_('COM_BETTERCATEGORIES_MENU_DIAGNOSTICS') . ' — ' . gmdate('Y-m-d H:i') . ' UTC', ''];
        foreach ($sections as $section => $rows) {
            $lines[] = '## ' . Text::_('COM_BETTERCATEGORIES_DIAG_SECTION_' . strtoupper($section));
            foreach ($rows as $row) {
                $lines[] = '[' . strtoupper($row['status']) . '] ' . Text::_($row['label']) . ': ' . $row['value'];
            }
            $lines[] = '';
        }
        foreach (['COM_BETTERCATEGORIES_DIAG_SITE_ERRORS' => $errors, 'COM_BETTERCATEGORIES_DIAG_PLUGIN_LOG' => $logs] as $title => $entries) {
            $lines[] = '## ' . Text::_($title);
            foreach (\array_slice($entries, 0, 5) as $e) {
                $lines[] = $e['time'] . ' ' . $e['where'] . ' ' . mb_substr($e['message'], 0, 1500);
            }
            $lines[] = $entries ? '' : '—';
        }

        return implode("\n", $lines);
    }

    private static function row(string $label, string $value, string $status, string $hint = ''): array
    {
        return ['label' => $label, 'value' => $value, 'status' => $status, 'hint' => $hint];
    }

    private static function extensionVersion(string $type, string $element, string $folder): ?string
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery()
            ->select($db->quoteName('manifest_cache'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote($type))
            ->where($db->quoteName('element') . ' = ' . $db->quote($element));
        if ($folder !== '') {
            $query->where($db->quoteName('folder') . ' = ' . $db->quote($folder));
        }
        $json = $db->setQuery($query, 0, 1)->loadResult();
        if (!\is_string($json)) {
            return null;
        }
        $data = json_decode($json);

        return \is_object($data) && isset($data->version) ? (string) $data->version : '?';
    }

    private static function logDir(): string
    {
        return rtrim((string) Factory::getApplication()->get('log_path', JPATH_ADMINISTRATOR . '/logs'), '/\\');
    }

    private static function relative(string $path): string
    {
        return str_starts_with($path, JPATH_ROOT . '/') ? substr($path, \strlen(JPATH_ROOT) + 1) : $path;
    }

    private static function bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }
        $n = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $n * 1073741824,
            'm' => $n * 1048576,
            'k' => $n * 1024,
            default => $n,
        };
    }

    private static function size(int $bytes): string
    {
        foreach (['GB' => 1073741824, 'MB' => 1048576, 'KB' => 1024] as $unit => $n) {
            if ($bytes >= $n) {
                return round($bytes / $n, 1) . ' ' . $unit;
            }
        }

        return $bytes . ' B';
    }
}

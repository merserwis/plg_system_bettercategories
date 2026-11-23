<?php

/**
 * @package     Merserwis.Plugin
 * @subpackage  System.bettercategories
 *
 * Statistics of the filters (1.11.0): which filters and values the visitors choose, in which categories,
 * and how often a choice leaves no product. No personal data: counts per day, category, filter and value.
 *
 * Counted by the visitor's browser, not by the page: a store keeps filtered pages in a page cache (MerTools),
 * and a page served from it runs no PHP. The page carries what it shows (the store, the category, the chosen
 * filters and values, the number of products found), signed with the site's secret; filters.js sends it once
 * per page and browser session (navigator.sendBeacon) and the plugin counts it when the signature holds.
 * A forged or changed choice is not counted; a page of a robot is not either (robots seldom run scripts, and
 * known ones are refused by their user agent).
 *
 * The rows of one day: filter '' and value '' = a filtered page of the category (label = its title), filter
 * <key> and value '' = the filter was used, filter <key> and value <slug> = that value was chosen (a range,
 * price or parameter from–to, is counted as its filter only). uses = pages, no_results = pages with no product.
 */

namespace Merserwis\Plugin\System\BetterCategories\Filter;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

final class Stats
{
    public const TABLE = '#__bettercategories_filter_stats';

    /** Days of history kept: the longest period offered (180 days) and the one before it, with room to spare. */
    private const KEEP_DAYS = 400;

    /** A signed page is accepted for this many days after it was made (a page cache keeps pages for hours, not weeks). */
    private const VALID_DAYS = 7;

    /** The same page from one address and browser is counted once in this many minutes; one of them at most MAX_PER_HOUR pages an hour. */
    private const REPEAT_MINUTES = 30;

    private const MAX_PER_HOUR = 300;

    /** Rows at most (the oldest go first). */
    private const MAX_ROWS = 300000;

    /** Filters and values of one page counted at most. */
    private const MAX_ITEMS = 24;

    /** User agents that are robots, previews and tools, not visitors. */
    private const ROBOTS = '/bot|crawl|spider|slurp|preview|headless|lighthouse|pagespeed|facebookexternalhit|curl|wget|python|java\/|httpclient|monitor/i';

    /**
     * What a filtered page shows, signed: the attributes of the bar above the list (data-bcf-stat, data-bcf-sig),
     * '' when nothing is chosen.
     *
     * @param  array  $defs    the filters of the category (Filters::definitions())
     * @param  array  $state   the visitor's choice (Filters::state(), after apply())
     * @param  array  $facets  the options with their labels (apply()['facets'])
     */
    public static function attributes(int $appId, int $categoryId, string $categoryTitle, array $defs, array $state, array $facets, int $total, string $day = '', string $query = ''): string
    {
        if (!$state) {
            return '';
        }
        $items = [];
        foreach ($defs as $def) {
            $s = $state[$def['key']] ?? null;
            if (!$s || count($items) >= self::MAX_ITEMS) {
                continue;
            }
            $label = mb_substr((string) $def['label'], 0, 120);
            if (!isset($s['values'])) {
                $items[] = [$def['key'], $label, '', ''];
                continue;
            }
            foreach ($s['values'] as $value) {
                if (count($items) >= self::MAX_ITEMS) {
                    break;
                }
                $valueLabel = (string) ($facets[$def['key']]['options'][$value]['label'] ?? $value);
                $items[]    = [$def['key'], $label, mb_substr((string) $value, 0, 60), mb_substr(strip_tags($valueLabel), 0, 120)];
            }
        }
        if (!$items) {
            return '';
        }
        // d: the day the page was made (a signed page is accepted VALID_DAYS); q: the whole choice, ranges with their ends, so two
        // ranges of one filter are two pages (counted as the filter only)
        $data = rtrim(strtr(base64_encode((string) json_encode(['a' => $appId, 'c' => $categoryId, 't' => mb_substr($categoryTitle, 0, 120), 'n' => $total, 'f' => $items,
            'd' => $day, 'q' => substr(md5($query), 0, 12)],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');

        $url = \Joomla\CMS\Uri\Uri::root(true) . '/index.php?option=com_ajax&group=system&plugin=bettercategories&format=json';

        return ' data-bcf-stat="' . htmlspecialchars($data, ENT_QUOTES, 'UTF-8') . '" data-bcf-sig="' . self::sign($data) . '" data-bcf-stat-url="'
            . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"';
    }

    private static function sign(string $data): string
    {
        return substr(hash_hmac('sha256', $data, (string) Factory::getApplication()->get('secret', '') . '|bettercategories-filter-stats'), 0, 32);
    }

    /**
     * A page sent by filters.js: counted when its signature holds and the browser is no robot.
     *
     * @return bool counted
     */
    public static function receive(DatabaseInterface $db, string $data, string $sig, string $userAgent, string $day, string $address = ''): bool
    {
        if ($data === '' || strlen($data) > 8000 || !preg_match('/^[A-Za-z0-9_-]+$/', $data) || !hash_equals(self::sign($data), $sig)
            || $userAgent === '' || preg_match(self::ROBOTS, $userAgent)) {
            return false;
        }
        $page = json_decode((string) base64_decode(strtr($data, '-_', '+/')), true);
        if (!is_array($page) || !is_array($page['f'] ?? null)) {
            return false;
        }
        // an old page (a signed choice kept and sent again and again) is not counted
        $made = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($page['d'] ?? ''));
        $today = \DateTimeImmutable::createFromFormat('!Y-m-d', $day);
        if (!$made || !$today || $made > $today->modify('+1 day') || $made < $today->modify('-' . self::VALID_DAYS . ' days')) {
            return false;
        }
        if (!self::allowed($address, $sig)) {
            return false;
        }
        $empty = (int) ($page['n'] ?? 1) === 0 ? 1 : 0;
        $rows  = [[(int) ($page['a'] ?? 0), (int) ($page['c'] ?? 0), '', '', (string) ($page['t'] ?? ''), '']];
        $used  = [];
        foreach (array_slice($page['f'], 0, self::MAX_ITEMS) as $item) {
            if (!is_array($item) || count($item) !== 4) {
                continue;
            }
            [$key, $label, $value, $valueLabel] = array_map('strval', $item);
            if (!isset($used[$key])) {
                $used[$key] = true;
                $rows[]     = [$rows[0][0], $rows[0][1], $key, '', $label, ''];
            }
            if ($value !== '') {
                $rows[] = [$rows[0][0], $rows[0][1], $key, $value, $label, $valueLabel];
            }
        }

        $values = [];
        foreach ($rows as [$app, $category, $filter, $value, $label, $valueLabel]) {
            $values[] = '(' . implode(', ', [$db->quote($day), $app, $category, $db->quote(mb_substr($filter, 0, 64)), $db->quote(mb_substr($value, 0, 64)),
                $db->quote(mb_substr($label, 0, 190)), $db->quote(mb_substr($valueLabel, 0, 190)), 1, $empty]) . ')';
        }
        $sql = 'INSERT INTO ' . $db->quoteName(self::TABLE) . ' (day, app_id, category_id, filter, value, label, value_label, uses, no_results) VALUES ' . implode(', ', $values)
            . ' ON DUPLICATE KEY UPDATE uses = uses + 1, no_results = no_results + VALUES(no_results), label = VALUES(label), value_label = VALUES(value_label)';
        try {
            $db->setQuery($sql)->execute();
        } catch (\Throwable $e) {
            // the table is made on install; made here when it is missing (a copy of the files, a failed install)
            self::ensureTable($db);
            $db->setQuery($sql)->execute();
        }
        if (random_int(1, 200) === 1) {
            self::trim($db);
        }

        return true;
    }

    /**
     * The same signed page sent again from one address within REPEAT_MINUTES, or more than MAX_PER_HOUR pages an hour
     * from it: not counted. The address is kept only as a salted hash in the cache (a file per address and page, gone
     * with the cache), never in the statistics.
     */
    private static function allowed(string $address, string $sig): bool
    {
        if ($address === '') {
            return true;
        }
        try {
            $secret = (string) Factory::getApplication()->get('secret', '');
            $who    = substr(hash_hmac('sha256', $address, $secret . '|bc-stats-address'), 0, 24);
            $hour   = gmdate('YmdH');
            $cache  = Factory::getContainer()->get(\Joomla\CMS\Cache\CacheControllerFactoryInterface::class)
                ->createCacheController('output', ['defaultgroup' => 'plg_system_bettercategories_stats', 'lifetime' => self::REPEAT_MINUTES, 'caching' => true]);
            if ($cache->get('p-' . $who . '-' . $sig)) {
                return false;
            }
            $count = (int) $cache->get('h-' . $who . '-' . $hour);
            if ($count >= self::MAX_PER_HOUR) {
                return false;
            }
            $cache->store('1', 'p-' . $who . '-' . $sig);
            $cache->store((string) ($count + 1), 'h-' . $who . '-' . $hour);
        } catch (\Throwable $e) {
            // no cache: counted as before
        }

        return true;
    }

    public static function ensureTable(DatabaseInterface $db): void
    {
        $db->setQuery('CREATE TABLE IF NOT EXISTS ' . $db->quoteName(self::TABLE) . ' ('
            . ' day DATE NOT NULL,'
            . ' app_id INT NOT NULL DEFAULT 0,'
            . ' category_id INT NOT NULL DEFAULT 0,'
            . " filter VARCHAR(64) NOT NULL DEFAULT '',"
            . " value VARCHAR(64) NOT NULL DEFAULT '',"
            . " label VARCHAR(190) NOT NULL DEFAULT '',"
            . " value_label VARCHAR(190) NOT NULL DEFAULT '',"
            . ' uses INT UNSIGNED NOT NULL DEFAULT 0,'
            . ' no_results INT UNSIGNED NOT NULL DEFAULT 0,'
            . ' PRIMARY KEY (day, app_id, category_id, filter, value),'
            . ' KEY idx_filter (filter, value, day)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci')->execute();
    }

    public static function dropTable(DatabaseInterface $db): void
    {
        $db->setQuery('DROP TABLE IF EXISTS ' . $db->quoteName(self::TABLE))->execute();
    }

    /** A year of history and MAX_ROWS rows at most. */
    private static function trim(DatabaseInterface $db): void
    {
        try {
            $db->setQuery('DELETE FROM ' . $db->quoteName(self::TABLE) . ' WHERE day < ' . $db->quote(gmdate('Y-m-d', time() - self::KEEP_DAYS * 86400)))->execute();
            $over = (int) $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName(self::TABLE))->loadResult() - self::MAX_ROWS;
            if ($over > 0) {
                $db->setQuery('DELETE FROM ' . $db->quoteName(self::TABLE) . ' ORDER BY day ASC LIMIT ' . $over)->execute();
            }
        } catch (\Throwable $e) {
        }
    }

    // ---------------------------------------------------------------- reading

    /**
     * The figures of a period [from, to) (days Y-m-d) and of the period of the same length before it.
     *
     * @return array{views: int, empty: int, prevViews: int, prevEmpty: int, filters: list<array>, values: list<array>, categories: list<array>, emptyValues: list<array>}
     *         filters: filter, label, uses, prev; values: filter, label, value, value_label, uses, empty; categories: category_id,
     *         label, uses, empty; emptyValues: as values, the ones that most often left no product
     */
    public static function summary(DatabaseInterface $db, string $from, string $to, int $limit = 10): array
    {
        $out = ['views' => 0, 'empty' => 0, 'prevViews' => 0, 'prevEmpty' => 0, 'filters' => [], 'values' => [], 'categories' => [], 'emptyValues' => []];
        $days  = max(1, (int) (new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->days);
        $pFrom = (new \DateTimeImmutable($from))->modify('-' . $days . ' days')->format('Y-m-d');
        $t     = $db->quoteName(self::TABLE);
        $range = fn (string $a, string $b) => ' WHERE day >= ' . $db->quote($a) . ' AND day < ' . $db->quote($b);
        $limit = max(1, min(100, $limit));

        try {
            $now  = $db->setQuery('SELECT IFNULL(SUM(uses), 0), IFNULL(SUM(no_results), 0) FROM ' . $t . $range($from, $to) . " AND filter = ''")->loadRow() ?: [0, 0];
            $prev = $db->setQuery('SELECT IFNULL(SUM(uses), 0), IFNULL(SUM(no_results), 0) FROM ' . $t . $range($pFrom, $from) . " AND filter = ''")->loadRow() ?: [0, 0];
        } catch (\Throwable $e) {
            // no table yet: nothing counted
            return $out;
        }
        [$out['views'], $out['empty'], $out['prevViews'], $out['prevEmpty']] = [(int) $now[0], (int) $now[1], (int) $prev[0], (int) $prev[1]];
        if (!$out['views']) {
            return $out;
        }

        // the latest label of a filter or value (a renamed filter keeps its key)
        $latest = fn (string $col) => 'SUBSTRING_INDEX(GROUP_CONCAT(' . $col . " ORDER BY day DESC SEPARATOR '\\n'), '\\n', 1)";

        $filters = $db->setQuery('SELECT filter, ' . $latest('label') . ' AS label, SUM(uses) AS uses, SUM(no_results) AS nores FROM ' . $t . $range($from, $to)
            . " AND filter <> '' AND value = '' GROUP BY filter ORDER BY uses DESC, filter ASC LIMIT " . $limit)->loadAssocList() ?: [];
        $before = [];
        if ($filters) {
            $in = implode(',', array_map(fn ($r) => $db->quote((string) $r['filter']), $filters));
            foreach ($db->setQuery('SELECT filter, SUM(uses) FROM ' . $t . $range($pFrom, $from) . " AND value = '' AND filter IN (" . $in . ') GROUP BY filter')->loadRowList() ?: [] as [$f, $n]) {
                $before[(string) $f] = (int) $n;
            }
        }
        foreach ($filters as $row) {
            $out['filters'][] = ['filter' => (string) $row['filter'], 'label' => (string) $row['label'], 'uses' => (int) $row['uses'], 'empty' => (int) $row['nores'],
                'prev' => $before[(string) $row['filter']] ?? 0];
        }

        $values = fn (string $order, string $having) => array_map(fn ($r) => ['filter' => (string) $r['filter'], 'label' => (string) $r['label'], 'value' => (string) $r['value'],
            'value_label' => (string) $r['value_label'], 'uses' => (int) $r['uses'], 'empty' => (int) $r['nores']],
            $db->setQuery('SELECT filter, value, ' . $latest('label') . ' AS label, ' . $latest('value_label') . ' AS value_label, SUM(uses) AS uses, SUM(no_results) AS nores FROM '
                . $t . $range($from, $to) . " AND value <> '' GROUP BY filter, value " . $having . ' ORDER BY ' . $order . ' LIMIT ' . $limit)->loadAssocList() ?: []);
        $out['values']      = $values('uses DESC, filter ASC, value ASC', '');
        $out['emptyValues'] = $values('nores DESC, uses DESC, filter ASC', 'HAVING nores > 0');

        foreach ($db->setQuery('SELECT category_id, ' . $latest('label') . ' AS label, SUM(uses) AS uses, SUM(no_results) AS nores FROM ' . $t . $range($from, $to)
            . " AND filter = '' GROUP BY category_id ORDER BY uses DESC, category_id ASC LIMIT " . $limit)->loadAssocList() ?: [] as $row) {
            $out['categories'][] = ['category_id' => (int) $row['category_id'], 'label' => (string) $row['label'], 'uses' => (int) $row['uses'], 'empty' => (int) $row['nores']];
        }

        return $out;
    }

    /**
     * The section of an e-mail report (Better Search's, through onBetterSearchReport): the same table styles.
     *
     * @param  array  $ui  esc, n, change (callables), th, td, tdr, h2 (style attributes) of the report;
     *                     missing ones get plain defaults
     * @param  callable(string): string  $t  the texts (a language key without its prefix => text)
     */
    public static function reportHtml(array $summary, array $ui, callable $t): string
    {
        $esc    = $ui['esc'] ?? fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $n      = $ui['n'] ?? fn ($v) => number_format((float) $v, 0, ',', "\u{00A0}");
        $change = $ui['change'] ?? fn (int $a, int $b, bool $good = true) => '';
        $th     = $ui['th'] ?? 'style="text-align:left;padding:6px 8px;border-bottom:2px solid #e5e7eb;font-size:12px;color:#6b7280;text-transform:uppercase"';
        $td     = $ui['td'] ?? 'style="padding:6px 8px;border-bottom:1px solid #f0f0f0"';
        $tdr    = $ui['tdr'] ?? 'style="padding:6px 8px;border-bottom:1px solid #f0f0f0;text-align:right;white-space:nowrap"';
        $h2     = $ui['h2'] ?? 'style="font-size:16px;margin:28px 0 8px;color:#111"';
        $note   = 'style="margin:16px 0 8px;color:#6b7280;font-size:13px"';

        $html = '<h2 ' . $h2 . '>' . $esc($t('REPORT_TITLE')) . '</h2>';
        if (!$summary['views']) {
            return $html . '<p ' . $note . '>' . $esc($t('REPORT_NO_DATA')) . '</p>';
        }
        $html .= '<table role="presentation" style="width:100%;border-collapse:separate;border-spacing:6px"><tr>';
        foreach ([['REPORT_VIEWS', $summary['views'], $summary['prevViews'], true], ['REPORT_EMPTY', $summary['empty'], $summary['prevEmpty'], false]] as [$key, $value, $old, $good]) {
            $html .= '<td style="background:#f6f8fa;border-radius:8px;padding:10px 12px;vertical-align:top"><div style="font-size:12px;color:#6b7280">' . $esc($t($key))
                . '</div><div style="font-size:22px;font-weight:700">' . $n($value) . '</div><div style="font-size:12px">' . $change((int) $value, (int) $old, $good) . '</div></td>';
        }
        $html .= '</tr></table>';

        $table = function (array $head, array $rows) use ($th): string {
            $out = '<table style="width:100%;border-collapse:collapse"><tr>';
            foreach ($head as $h) {
                $out .= '<th ' . $th . '>' . $h . '</th>';
            }

            return $out . '</tr>' . implode('', $rows) . '</table>';
        };

        if ($summary['filters']) {
            $rows = [];
            foreach ($summary['filters'] as $r) {
                $rows[] = '<tr><td ' . $td . '>' . $esc($r['label']) . '</td><td ' . $tdr . '>' . $n($r['uses']) . '</td><td ' . $tdr . '>' . $change($r['uses'], $r['prev']) . '</td></tr>';
            }
            $html .= '<p ' . $note . '>' . $esc($t('REPORT_FILTERS')) . '</p>' . $table([$esc($t('COL_FILTER')), $esc($t('COL_USES')), $esc($t('COL_CHANGE'))], $rows);
        }
        if ($summary['values']) {
            $rows = [];
            foreach ($summary['values'] as $r) {
                $rows[] = '<tr><td ' . $td . '>' . $esc($r['label']) . '</td><td ' . $td . '>' . $esc($r['value_label']) . '</td><td ' . $tdr . '>' . $n($r['uses']) . '</td></tr>';
            }
            $html .= '<p ' . $note . '>' . $esc($t('REPORT_VALUES')) . '</p>' . $table([$esc($t('COL_FILTER')), $esc($t('COL_VALUE')), $esc($t('COL_USES'))], $rows);
        }
        if ($summary['categories']) {
            $rows = [];
            foreach ($summary['categories'] as $r) {
                $rows[] = '<tr><td ' . $td . '>' . $esc($r['label']) . '</td><td ' . $tdr . '>' . $n($r['uses']) . '</td><td ' . $tdr . '>'
                    . ($r['empty'] ? '<b style="color:#b91c1c">' . $n($r['empty']) . '</b>' : '0') . '</td></tr>';
            }
            $html .= '<p ' . $note . '>' . $esc($t('REPORT_CATEGORIES')) . '</p>' . $table([$esc($t('COL_CATEGORY')), $esc($t('COL_USES')), $esc($t('COL_EMPTY'))], $rows);
        }
        if ($summary['emptyValues']) {
            $rows = [];
            foreach ($summary['emptyValues'] as $r) {
                $rows[] = '<tr><td ' . $td . '>' . $esc($r['label']) . '</td><td ' . $td . '>' . $esc($r['value_label']) . '</td><td ' . $tdr . '><b style="color:#b91c1c">'
                    . $n($r['empty']) . '</b> / ' . $n($r['uses']) . '</td></tr>';
            }
            $html .= '<p ' . $note . '>' . $esc($t('REPORT_EMPTY_VALUES')) . '</p>' . $table([$esc($t('COL_FILTER')), $esc($t('COL_VALUE')), $esc($t('COL_EMPTY'))], $rows);
        }

        return $html;
    }
}

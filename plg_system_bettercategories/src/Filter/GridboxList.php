<?php

/**
 * @package     Merserwis.Plugin
 * @subpackage  System.bettercategories
 *
 * The Gridbox product list (element "blog posts" of a store category page) rendered again for the
 * products a filter lets through, with Gridbox's own helpers and templates: the same cards, sorting,
 * page size and pagination type as the element, only the product set is smaller.
 */

namespace Merserwis\Plugin\System\BetterCategories\Filter;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

final class GridboxList
{
    private const HELPER = '\\Balbooa\\Component\\Gridbox\\Site\\Helper\\GridboxHelper';

    /** Orders an element may be set to in Gridbox (anything else from the address is not used). */
    private const ORDERS = ['created', 'hits', 'title', 'order_list', 'random', 'id', 'price-low-high', 'price-high-low',
        'newest', 'popular', 'highest-rated', 'most-reviewed', 'event-date'];

    public static function available(): bool
    {
        $helper = self::HELPER;

        return class_exists($helper) && method_exists($helper, 'getBlogPostsQuery') && method_exists($helper, 'getRecentPostsHTML');
    }

    /** Settings of a Gridbox element of the app layout (null when not found). */
    public static function elementConfig(int $appId, string $elementId): ?object
    {
        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->createQuery()
                ->select($db->quoteName(['app_items', 'type']))
                ->from($db->quoteName('#__gridbox_app'))
                ->where($db->quoteName('id') . ' = ' . $appId);
            $row   = $db->setQuery($query)->loadObject();
            $json  = (string) ($row->app_items ?? '');
            if ($json === '' && $row && preg_match('/^[a-z_-]+$/', (string) $row->type)) {
                // the default layout of the app type, as Gridbox reads it (BlogModel)
                $file = JPATH_ROOT . '/components/com_gridbox/tmpl/layout/apps/' . $row->type . '/app.json';
                $json = is_file($file) ? (string) file_get_contents($file) : '';
            }
            $items = json_decode($json);
            $item  = is_object($items) ? ($items->{$elementId} ?? null) : null;
        } catch (\Throwable $e) {
            $item = null;
        }
        if (!is_object($item)) {
            $helper = self::HELPER;
            $item   = class_exists($helper) && is_object($helper::$editItem ?? null) && ($helper::$editItem->type ?? '') === 'blog-posts' ? $helper::$editItem : null;
        }

        return $item;
    }

    /**
     * Cards, pagination and the number of products of the filtered list.
     *
     * @param  int[]   $ids     products let through (already visible ones)
     * @param  string  $filter  query string of the filters ("f-a=x&f-b=1..5"), kept in the pagination links
     *
     * @return array{posts: string, pagination: string, count: int}
     */
    public static function render(int $appId, int $categoryId, object $item, array $ids, string $filter): array
    {
        $helper = self::HELPER;
        $app    = Factory::getApplication();
        $input  = $app->getInput();
        $db     = Factory::getContainer()->get(DatabaseInterface::class);

        $page       = max(1, $input->getInt('page', 1));
        $max        = (int) ($item->maximum ?? 50);
        $limit      = max(1, (int) ($item->limit ?? 12));
        $pagination = preg_replace('/[^a-z-]/', '', (string) ($item->pagination ?? ''));
        $default    = (string) ($item->order ?? 'created');
        $default    = in_array($default, self::ORDERS, true) ? $default : 'created';
        $order      = (string) $input->getString('sort-by', '');
        // only orders Gridbox offers: the order goes into SQL
        $sortList = $helper::getBlogPostsSortingList($default === 'order_list');
        $order    = isset($sortList[$order]) ? $order : $default;

        if (!$ids) {
            return ['posts' => $helper::getEmptyList(), 'pagination' => '', 'count' => 0];
        }
        $idList = implode(',', array_map('intval', $ids));

        // as GridboxHelper::getBlogPosts
        $start = ($page - 1) * $limit;
        $rows  = $limit;
        if ($pagination !== '') {
            $rows  = $start + $limit;
            $start = 0;
        }
        $orderBy = $order;
        if (isset($sortList[$orderBy]) || in_array($orderBy, ['event-date', 'title ASC', 'title DESC'], true)) {
            $dir = '';
        } elseif ($orderBy === 'order_list') {
            $dir = ' ASC';
            if ($categoryId === 0) {
                $orderBy = 'root_order_list';
            }
        } else {
            $dir = ' DESC';
        }
        if ($orderBy === 'random') {
            $orderBy = 'RAND()';
        } elseif (!isset($sortList[$orderBy]) && $orderBy !== 'event-date') {
            $orderBy = 'p.' . $orderBy;
        }

        $query = $db->createQuery()
            ->select('DISTINCT p.id, p.title, p.intro_text, p.created, p.hits, p.intro_image, p.page_category,
                p.app_id, p.meta_title, c.title as category, a.title as blog, a.type');
        $query = $helper::getBlogPostsQuery($query, $appId, $categoryId, $orderBy . $dir);
        $query->where('p.id IN (' . $idList . ')');
        $pages = $db->setQuery($query, $start, $rows)->loadObjectList() ?: [];

        $count = $db->createQuery()->select('COUNT(DISTINCT(p.id))');
        $count = $helper::getBlogPostsQuery($count, $appId, $categoryId);
        $count->where('p.id IN (' . $idList . ')');
        $total = (int) $db->setQuery($count)->loadResult();

        $out = '';
        include JPATH_ROOT . '/components/com_gridbox/tmpl/layout/blog-posts.php';
        /** @var string $out */
        $saved             = $helper::$editItem;
        $helper::$editItem = $item;
        $posts             = '';
        try {
            foreach ($pages as $row) {
                $posts .= $helper::getRecentPostsHTML($row, $out, $max);
            }
        } finally {
            $helper::$editItem = $saved;
        }
        if ($posts === '') {
            $posts = $helper::getEmptyList();
        }

        $url = $helper::getGridboxCategoryLinks($categoryId, $appId) . ($filter !== '' ? '&' . $filter : '');
        if (isset($sortList[(string) $input->getString('sort-by', '')])) {
            $url .= '&sort-by=' . $order;
        }

        return ['posts' => $posts, 'pagination' => self::pagination($url, $page - 1, $limit, $total, $pagination), 'count' => $total];
    }

    /** Gridbox's pagination (GridboxHelper::getBlogPagination) for a given number of products. */
    private static function pagination(string $url, int $active, int $limit, int $count, string $type): string
    {
        if ($count === 0) {
            return '';
        }
        $pages = (int) ceil($count / max(1, $limit));
        if ($pages <= 1) {
            return '';
        }
        $start = 0;
        $max   = $pages;
        if ($active > 2 && $pages > 4) {
            $start = $active - 2;
        }
        if ($pages > 4 && ($pages - $active) < 3) {
            $start = $pages - 5;
        }
        if ($pages > $active + 2) {
            $max = $active + 3;
            if ($pages > 3 && $active < 2) {
                $max = 4;
            }
            if ($pages > 4 && $active < 2) {
                $max = 5;
            }
        }
        $app = Factory::getApplication();
        $out = '';
        include JPATH_ROOT . '/components/com_gridbox/tmpl/layout/blog-posts-pagination.php';

        return (string) $out;
    }
}

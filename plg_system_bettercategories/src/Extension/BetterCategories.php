<?php

/**
 * @package     Merserwis.Plugin
 * @subpackage  System.bettercategories
 *
 * Gridbox store category pages list products from the whole category subtree, so a parent
 * category shows products instead of letting the visitor pick a subcategory first. This plugin
 * adds the subcategories of the current category (with product counts) to the page, as text links
 * or tiles. The product list, filters and search are left to Gridbox.
 */

namespace Merserwis\Plugin\System\BetterCategories\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Registry\Registry;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\SubscriberInterface;

final class BetterCategories extends CMSPlugin implements SubscriberInterface
{
    private const POSITIONS  = ['before_products', 'after_products', 'after_intro', 'page_top', 'before_item', 'after_item'];
    private const STYLES     = ['below', 'overlay', 'cover', 'card'];
    private const SHAPES     = ['rect', 'rounded', 'circle'];
    private const RATIOS     = ['1-1' => '1 / 1', '4-3' => '4 / 3', '3-2' => '3 / 2', '16-9' => '16 / 9', '3-4' => '3 / 4'];
    private const HOVERS     = ['none', 'zoom', 'zoom_out', 'lift', 'shine', 'grayscale', 'tint_reveal', 'tint_show', 'tilt', 'ring'];
    private const DIRECTIONS = ['rows', 'columns', 'scroll', 'inline'];
    private const VERSION    = '1.2.0';
    private const SUB_MODES  = ['none', 'below', 'drawer', 'side', 'flip', 'tooltip'];
    private const CACHE_GROUP = 'plg_system_bettercategories';

    /** @var int[]|null */
    private ?array $hidden = null;

    /** @var array<int, object>|null site menu items of com_gridbox */
    private ?array $gridboxMenu = null;

    /** Administrator live preview: links become "#", image URLs absolute. */
    private bool $preview = false;

    /** @var int[]|null view levels of the current user (memoised per request) */
    private ?array $levels = null;

    /** Device class of the current visitor (memoised per request). */
    private ?string $deviceClass = null;

    /** Stored in the cache in place of an empty result, so leaf categories skip rendering too. */
    private const CACHE_EMPTY = "\0empty";

    /** Thumbnails folder (under the site root) and the time one request may spend creating them. */
    private const THUMB_DIR    = 'media/plg_system_bettercategories/thumbs';
    private const THUMB_BUDGET = 1.5;

    /** Seconds spent creating thumbnails in this request. */
    private float $thumbTime = 0.0;

    /** A thumbnail was left for later (time budget): the block is not cached, the next visit finishes it. */
    private bool $incomplete = false;

    /** The page already carries breadcrumb structured data (the "automatic" breadcrumb setting adds none). */
    private bool $pageCrumbs = false;

    /** @var array<int, string> store app titles */
    private array $appTitles = [];

    public static function getSubscribedEvents(): array
    {
        return ['onAfterRender' => 'onAfterRender', 'onAjaxBettercategories' => 'onAjax', 'onExtensionAfterSave' => 'onExtensionAfterSave'];
    }

    public function onAfterRender(): void
    {
        $t0  = hrtime(true);
        $app = $this->getApplication();
        if (!$app->isClient('site') || $app->getDocument()->getType() !== 'html') {
            return;
        }

        $input = $app->getInput();
        if ($input->getCmd('option') !== 'com_gridbox' || $input->getCmd('view') !== 'blog'
            || $input->getCmd('tmpl') === 'component') {
            return;
        }

        // Use the settings saved in the database, not the ones handed to the plugin at start-up: some
        // sites (device-specific extensions/caches) pass phones an older copy of plugin parameters.
        $given        = substr(md5(json_encode($this->params->toArray())), 0, 8);
        $this->params = $this->savedParams() ?? $this->params;

        // Search, filters, tag and author listings stay as Gridbox renders them.
        foreach (['search', 'query', 'tag', 'author'] as $key) {
            if (trim((string) $input->get($key, '', 'raw')) !== '') {
                return;
            }
        }
        if ($this->params->get('first_page_only', 1) && $input->getInt('page', 1) > 1) {
            return;
        }

        $appId      = $input->getInt('app', 0);
        $categoryId = $input->getInt('id', 0);
        if ($appId <= 0 || !ComponentHelper::isEnabled('com_gridbox') || !$this->isEnabledApp($appId)) {
            return;
        }

        $body = $app->getBody();
        $pos  = $this->findInsertPosition($body);
        if ($pos === null) {
            return;
        }

        $this->pageCrumbs = stripos($body, 'BreadcrumbList') !== false;

        $start  = hrtime(true);
        $device = $this->device();
        $config = substr(md5(json_encode($this->params->toArray())), 0, 8);
        $status = 'off';

        try {
            $minutes = max(0, min(1440, (int) $this->params->get('cache_time', 15)));
            if ($minutes > 0) {
                $user  = $app->getIdentity();
                $key   = md5(implode('|', [self::VERSION, $config, $appId, $categoryId, $device,
                    $app->getLanguage()->getTag(), implode(',', $this->viewLevels()), Uri::root(), (int) $this->pageCrumbs]));
                $cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)
                    ->createCacheController('output', ['defaultgroup' => self::CACHE_GROUP, 'lifetime' => $minutes, 'caching' => true]);
                $html = $cache->get($key);
                if (is_string($html)) {
                    $status = 'hit';
                    $html   = $html === self::CACHE_EMPTY ? '' : $html;
                } else {
                    $html   = $this->render($appId, $categoryId);
                    $status = 'miss';
                    if ($this->incomplete) {
                        // some thumbnails are still to be made: the next visit renders (and caches) again
                        $status = 'miss, thumbnails pending';
                    } else {
                        $cache->store($html === '' ? self::CACHE_EMPTY : $html, $key);
                    }
                }
            } else {
                $html = $this->render($appId, $categoryId);
            }
        } catch (\Throwable $e) {
            return;
        }

        // One comment line (also on pages without subcategories) to compare what desktop and phone
        // visitors get: settings hash, device, cache state and time spent.
        $html .= sprintf('<!-- Better Categories %s | cfg %s%s | %s | cache %s | %.1f ms (total %.1f ms) -->',
            self::VERSION, $config, $given !== $config ? ' (site passed ' . $given . ', ignored)' : '',
            $device, $status, (hrtime(true) - $start) / 1e6, (hrtime(true) - $t0) / 1e6);
        $app->setBody(substr($body, 0, $pos) . $html . substr($body, $pos));
    }

    /**
     * After the plugin settings are saved: clear this plugin's cache and the page caches that may
     * still hold pages (and plugin settings) from before the change, on the site and administrator.
     */
    public function onExtensionAfterSave($event): void
    {
        $args    = method_exists($event, 'getArguments') ? $event->getArguments() : [];
        $context = $args['context'] ?? $args[0] ?? '';
        $table   = $args['subject'] ?? $args['item'] ?? $args[1] ?? null;
        if ($context !== 'com_plugins.plugin' || !is_object($table) || ($table->element ?? '') !== 'bettercategories') {
            return;
        }

        $factory = Factory::getContainer()->get(CacheControllerFactoryInterface::class);
        foreach (array_unique([JPATH_SITE . '/cache', JPATH_ADMINISTRATOR . '/cache', JPATH_CACHE]) as $base) {
            foreach ([self::CACHE_GROUP, 'com_plugins', 'page', 'gridbox'] as $group) {
                try {
                    $factory->createCacheController('callback', ['defaultgroup' => $group, 'cachebase' => $base])->clean($group);
                } catch (\Throwable $e) {
                }
            }
        }
    }

    /** The plugin settings as saved in #__extensions (null when they cannot be read). */
    private function savedParams(): ?Registry
    {
        try {
            $db    = $this->db();
            $query = $db->createQuery()
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('bettercategories'));
            $json = $db->setQuery($query)->loadResult();

            return is_string($json) && $json !== '' ? new Registry($json) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function isEnabledApp(int $appId): bool
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $this->params->get('app_ids', ''))));
        if ($ids) {
            return in_array($appId, $ids, true);
        }

        $db    = $this->db();
        $query = $db->createQuery()
            ->select($db->quoteName('type'))
            ->from($db->quoteName('#__gridbox_app'))
            ->where($db->quoteName('id') . ' = ' . $appId);

        return $db->setQuery($query)->loadResult() === 'products';
    }

    // ---------------------------------------------------------------- position in the page

    /**
     * Offset in the page where the block goes, or null when the anchor element is missing.
     * Anchors are Gridbox elements: the product list (.ba-item-blog-posts), the category header
     * (.ba-item-category-intro), the page content wrapper, or any element by its id (item-…).
     */
    private function findInsertPosition(string $body): ?int
    {
        $bodyStart = stripos($body, '<body');
        if ($bodyStart === false) {
            return null;
        }

        $position = (string) $this->params->get('position', 'before_products');
        $position = in_array($position, self::POSITIONS, true) ? $position : 'before_products';

        switch ($position) {
            case 'page_top':
                if (preg_match('#<div\b[^>]*\bclass="[^"]*\bba-gridbox-page\b[^"]*"[^>]*>#i', $body, $m, PREG_OFFSET_CAPTURE, $bodyStart)) {
                    return $m[0][1] + strlen($m[0][0]);
                }
                break;

            case 'after_intro':
                $start = $this->findElementStart($body, '#<div\b[^>]*\bclass="[^"]*\bba-item-category-intro\b#i', $bodyStart);
                $end   = $start === null ? null : $this->findElementEnd($body, $start);
                if ($end !== null) {
                    return $end;
                }
                break;

            case 'before_item':
            case 'after_item':
                $itemId = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $this->params->get('item_id', ''));
                if ($itemId !== '') {
                    $start = $this->findElementStart($body, '#<div\b[^>]*\bid="' . preg_quote($itemId, '#') . '"#i', $bodyStart);
                    $end   = $start === null ? null : ($position === 'before_item' ? $start : $this->findElementEnd($body, $start));
                    if ($end !== null) {
                        return $end;
                    }
                }
                break;

            case 'after_products':
                $start = $this->findElementStart($body, '#<div\b[^>]*\bclass="[^"]*\bba-item-blog-posts\b#i', $bodyStart);
                $end   = $start === null ? null : $this->findElementEnd($body, $start);
                if ($end !== null) {
                    return $end;
                }
                break;
        }

        // before_products, and the fallback when the chosen anchor is not on the page
        return $this->findElementStart($body, '#<div\b[^>]*\bclass="[^"]*\bba-item-blog-posts\b#i', $bodyStart);
    }

    private function findElementStart(string $body, string $pattern, int $offset): ?int
    {
        return preg_match($pattern, $body, $m, PREG_OFFSET_CAPTURE, $offset) ? $m[0][1] : null;
    }

    /**
     * Offset right after the </div> closing the <div> that starts at $start (div nesting counted).
     */
    private function findElementEnd(string $body, int $start): ?int
    {
        $depth  = 0;
        $offset = $start;
        while (preg_match('#<(/?)div\b[^>]*>#i', $body, $tag, PREG_OFFSET_CAPTURE, $offset)) {
            $depth += $tag[1][0] === '/' ? -1 : 1;
            $offset = $tag[0][1] + strlen($tag[0][0]);
            if ($depth === 0) {
                return $offset;
            }
        }

        return null;
    }

    // ---------------------------------------------------------------- data

    private function db(): DatabaseInterface
    {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }

    /** @return int[] */
    private function viewLevels(): array
    {
        if ($this->levels === null) {
            $user         = $this->getApplication()->getIdentity();
            $this->levels = array_map('intval', $user ? $user->getAuthorisedViewLevels() : [1]) ?: [1];
        }

        return $this->levels;
    }

    /**
     * Published categories of the app the visitor may see (same rules as Gridbox's categories element).
     *
     * @return array<int, object>
     */
    private function loadCategories(int $appId): array
    {
        $db    = $this->db();
        $lang  = $this->getApplication()->getLanguage()->getTag();
        $query = $db->createQuery()
            ->select($db->quoteName(['id', 'title', 'parent', 'image']))
            ->from($db->quoteName('#__gridbox_categories'))
            ->where($db->quoteName('app_id') . ' = ' . $appId)
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('access') . ' IN (' . implode(',', $this->viewLevels()) . ')')
            ->where($db->quoteName('language') . ' IN (' . $db->quote($lang) . ', ' . $db->quote('*') . ')')
            ->order($db->quoteName('order_list') . ' ASC, ' . $db->quoteName('id') . ' ASC');

        $categories = [];
        foreach ($db->setQuery($query)->loadObjectList() ?: [] as $row) {
            $row->id     = (int) $row->id;
            $row->parent = (int) $row->parent;
            $row->count  = 0;
            $row->topHits  = -1;
            $row->topImage = '';
            $categories[$row->id] = $row;
        }

        // A category under an unpublished or hidden parent is not reachable: drop the whole branch.
        foreach ($categories as $id => $cat) {
            $parent = $cat->parent;
            $guard  = 0;
            while ($parent !== 0 && isset($categories[$parent]) && $guard++ < 50) {
                $parent = $categories[$parent]->parent;
            }
            if ($parent !== 0) {
                unset($categories[$id]);
            }
        }

        return $categories;
    }

    /**
     * Product count of every category including its subcategories — the same numbers Gridbox shows
     * in its own categories element: visible products by primary category plus the additional
     * category map, without the subscription add-ons Gridbox hides from listings.
     */
    private function addCounts(array $categories): void
    {
        if (!$categories) {
            return;
        }

        $db    = $this->db();
        $ids   = implode(',', array_map('intval', array_keys($categories)));
        $rows  = [];

        foreach (['primary', 'mapped'] as $kind) {
            $query = $this->visibleProductsQuery();
            if ($kind === 'primary') {
                $query->select(['p.page_category AS category_id', 'COUNT(p.id) AS total'])
                    ->where('p.page_category IN (' . $ids . ')')
                    ->group('p.page_category');
            } else {
                $query->select(['pm.category_id', 'COUNT(p.id) AS total'])
                    ->innerJoin($db->quoteName('#__gridbox_category_page_map', 'pm') . ' ON pm.page_id = p.id')
                    ->where('pm.category_id IN (' . $ids . ')')
                    ->group('pm.category_id');
            }

            try {
                $rows = array_merge($rows, $db->setQuery($query)->loadObjectList() ?: []);
            } catch (\Throwable $e) {
                continue;
            }
        }

        foreach ($rows as $row) {
            $total = (int) $row->total;
            $this->walkUp($categories, (int) $row->category_id, function ($cat) use ($total) {
                $cat->count += $total;
            });
        }
    }

    /**
     * Published, currently live, visible products of the store (#__gridbox_pages AS p), without
     * the subscription add-ons Gridbox hides from listings. Selects nothing yet.
     */
    private function visibleProductsQuery(): \Joomla\Database\QueryInterface
    {
        $db     = $this->db();
        $now    = $db->quote(gmdate('Y-m-d H:i:s'));
        $null   = $db->quote($db->getNullDate());
        $lang   = $this->getApplication()->getLanguage()->getTag();
        $hidden = $this->hiddenProducts();

        $query = $db->createQuery()
            ->from($db->quoteName('#__gridbox_pages', 'p'))
            ->where('p.published = 1')
            ->where('p.created <= ' . $now)
            ->where('(p.end_publishing = ' . $null . ' OR p.end_publishing >= ' . $now . ')')
            ->where('p.language IN (' . $db->quote($lang) . ', ' . $db->quote('*') . ')')
            ->where('p.page_access IN (' . implode(',', $this->viewLevels()) . ')')
            ->where($db->quoteName('p.page_category') . ' <> ' . $db->quote('trashed'));

        if ($hidden) {
            $query->where('p.id NOT IN (' . implode(',', $hidden) . ')');
        }

        return $query;
    }

    /**
     * Image of the most viewed visible product in each category subtree (primary category and the
     * additional category map), used for tiles without an own image.
     */
    private function addTopProductImages(int $appId, array $categories): void
    {
        $db = $this->db();

        $primary = $this->visibleProductsQuery()
            ->select(['p.page_category AS category_id', 'p.hits', 'p.intro_image'])
            ->where('p.app_id = ' . $appId)
            ->where($db->quoteName('p.intro_image') . ' <> ' . $db->quote(''));
        $mapped = $this->visibleProductsQuery()
            ->select(['pm.category_id', 'p.hits', 'p.intro_image'])
            ->innerJoin($db->quoteName('#__gridbox_category_page_map', 'pm') . ' ON pm.page_id = p.id')
            ->where('p.app_id = ' . $appId)
            ->where($db->quoteName('p.intro_image') . ' <> ' . $db->quote(''));

        foreach ([$primary, $mapped] as $query) {
            try {
                $rows = $db->setQuery($query)->loadObjectList() ?: [];
            } catch (\Throwable $e) {
                continue;
            }

            foreach ($rows as $row) {
                $hits  = (int) $row->hits;
                $image = (string) $row->intro_image;
                $this->walkUp($categories, (int) $row->category_id, function ($cat) use ($hits, $image) {
                    if ($hits > $cat->topHits) {
                        $cat->topHits  = $hits;
                        $cat->topImage = $image;
                    }
                });
            }
        }
    }

    /**
     * Internal link of a Gridbox category with the Itemid Gridbox itself would pick
     * (GridboxHelper::getGridboxCategoryLinks): the menu item of the category, else of the nearest
     * parent category, else of the whole app, else the home item when it is a Gridbox page.
     */
    private function categoryLink(int $appId, int $categoryId, array $categories): string
    {
        $link  = 'index.php?option=com_gridbox&view=blog&app=' . $appId . '&id=' . $categoryId;
        $items = $this->gridboxMenuItems();

        $find = function (int $id) use ($items, $appId): int {
            foreach ($items as $item) {
                $q = $item->query ?? [];
                if (($q['view'] ?? '') === 'blog' && (int) ($q['app'] ?? 0) === $appId && isset($q['id']) && (int) $q['id'] === $id) {
                    return (int) $item->id;
                }
            }

            return 0;
        };

        $itemId = $find($categoryId);
        $parent = $categories[$categoryId]->parent ?? 0;
        $guard  = 0;
        while ($itemId === 0 && $parent > 0 && $guard++ < 50) {
            $itemId = $find($parent);
            $parent = $categories[$parent]->parent ?? 0;
        }
        if ($itemId === 0) {
            $itemId = $find(0);
        }
        if ($itemId === 0) {
            $home   = $this->getApplication()->getMenu('site')->getDefault();
            $itemId = $home && $home->component === 'com_gridbox' ? (int) $home->id : 0;
        }

        return $link . '&Itemid=' . $itemId;
    }

    /** @return object[] */
    private function gridboxMenuItems(): array
    {
        if ($this->gridboxMenu === null) {
            // AbstractMenu::getItems() reads a deprecated user getter in Joomla 6: filter the items here.
            $componentId       = (int) ComponentHelper::getComponent('com_gridbox')->id;
            $levels            = $this->viewLevels();
            $this->gridboxMenu = array_values(array_filter(
                $this->getApplication()->getMenu('site')->getMenu(),
                fn ($item) => (int) $item->component_id === $componentId && in_array((int) $item->access, $levels, true)
            ));
        }

        return $this->gridboxMenu;
    }

    /**
     * Device class from the User-Agent: "mobile" (phones), "tablet" or "desktop" (iPadOS reports a desktop Mac).
     */
    private function device(): string
    {
        if ($this->deviceClass === null) {
            $ua = (string) ($this->getApplication()->getInput()->server->getString('HTTP_USER_AGENT', ''));
            if (preg_match('/iPad|Tablet|PlayBook|Silk|Kindle|Android(?!.*Mobile)/i', $ua)) {
                $this->deviceClass = 'tablet';
            } else {
                $this->deviceClass = preg_match('/Mobi|iPhone|iPod|Android.*Mobile|Windows Phone|BlackBerry|Opera Mini/i', $ua) ? 'mobile' : 'desktop';
            }
        }

        return $this->deviceClass;
    }

    /**
     * Administrator live preview (com_ajax): renders the list from the unsaved form values.
     * POST jform[params][...], category (0 = store home), token. Returns html + categories with children.
     */
    public function onAjax($event): void
    {
        $app = $this->getApplication();
        if (!$app->isClient('administrator') || !Session::checkToken() || !$app->getIdentity()?->authorise('core.manage', 'com_plugins')) {
            $app->setHeader('status', '403', true);
            $this->ajaxResult($event, ['error' => 'Not allowed']);

            return;
        }

        $form   = (array) $app->getInput()->post->get('jform', [], 'array');
        $saved  = $this->params;
        $this->params  = new Registry((array) ($form['params'] ?? []));
        $this->preview = true;

        try {
            $appId = $this->previewApp();
            if ($appId === 0) {
                $this->ajaxResult($event, ['error' => 'No Gridbox store app found.']);

                return;
            }

            $categories = $this->loadCategories($appId);
            // every category as an indented tree (last-level pages show the sibling bar)
            $tree = [['id' => 0, 'title' => 'Store home']];
            $walk = function (int $parent, int $depth) use (&$walk, &$tree, $categories): void {
                foreach ($categories as $cat) {
                    if ($cat->parent === $parent && $depth < 50) {
                        $tree[] = ['id' => $cat->id, 'title' => str_repeat('— ', $depth) . $cat->title];
                        $walk($cat->id, $depth + 1);
                    }
                }
            };
            $walk(0, 1);

            $categoryId = $app->getInput()->post->getInt('category', 0);
            if (!isset($categories[$categoryId])) {
                $categoryId = 0;
            }
            $hasChildren = (bool) array_filter($categories, fn ($cat) => $cat->parent === $categoryId);

            $this->ajaxResult($event, [
                'html'         => $this->render($appId, $categoryId),
                'categories'   => $tree,
                'category'     => $categoryId,
                'hideProducts' => $hasChildren && (bool) $this->params->get('hide_products', 0),
            ]);
        } catch (\Throwable $e) {
            $this->ajaxResult($event, ['error' => $e->getMessage()]);
        } finally {
            $this->params  = $saved;
            $this->preview = false;
        }
    }

    private function ajaxResult($event, array $data): void
    {
        if (method_exists($event, 'addResult')) {
            // Joomla 5/6 AjaxEvent (ResultAwareInterface)
            $event->addResult($data);
        } else {
            $result   = $event->getArgument('result') ?? [];
            $result[] = $data;
            $event->setArgument('result', $result);
        }
    }

    /** First store app handled by the plugin (the "Gridbox app IDs" setting, or any Products app). */
    private function previewApp(): int
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $this->params->get('app_ids', ''))));
        if ($ids) {
            return (int) reset($ids);
        }

        $db    = $this->db();
        $query = $db->createQuery()
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__gridbox_app'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('products'))
            ->order($db->quoteName('id') . ' ASC');

        return (int) $db->setQuery($query, 0, 1)->loadResult();
    }

    private function walkUp(array $categories, int $id, callable $fn): void
    {
        $guard = 0;
        while (isset($categories[$id]) && $guard++ < 50) {
            $fn($categories[$id]);
            $id = $categories[$id]->parent;
        }
    }

    /**
     * Products Gridbox hides from listings: those a subscription product removes after purchase
     * (store product data: product_type "subscription", action "products"/"full", remove = true).
     *
     * @return int[]
     */
    private function hiddenProducts(): array
    {
        if ($this->hidden !== null) {
            return $this->hidden;
        }

        $this->hidden = [];
        try {
            $db    = $this->db();
            $query = $db->createQuery()
                ->select($db->quoteName('subscription'))
                ->from($db->quoteName('#__gridbox_store_product_data'))
                ->where($db->quoteName('product_type') . ' = ' . $db->quote('subscription'));

            foreach ($db->setQuery($query)->loadColumn() ?: [] as $json) {
                $sub = json_decode((string) $json);
                if (is_object($sub) && in_array($sub->action ?? '', ['products', 'full'], true) && !empty($sub->remove)) {
                    foreach ((array) ($sub->products ?? []) as $id) {
                        $this->hidden[] = (int) (is_object($id) ? ($id->id ?? 0) : $id);
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        $this->hidden = array_values(array_filter(array_unique($this->hidden)));

        return $this->hidden;
    }

    /** @return array<int, string> category id => image chosen in the plugin settings */
    private function manualImages(): array
    {
        $images = [];
        foreach ((array) $this->params->get('category_images', []) as $row) {
            $row   = (array) $row;
            $id    = (int) ($row['category'] ?? 0);
            $image = trim((string) ($row['image'] ?? ''));
            if ($id > 0 && $image !== '') {
                $images[$id] = $image;
            }
        }

        return $images;
    }

    private function imageUrl(string $image): string
    {
        $image = trim($image);
        if ($image === '') {
            return '';
        }

        $image = HTMLHelper::cleanImageURL($image)->url;
        if (preg_match('#^(https?:)?//#i', $image)) {
            return $image;
        }

        $path = implode('/', array_map('rawurlencode', array_map('rawurldecode', explode('/', ltrim($image, '/')))));

        return ($this->preview ? rtrim(Uri::root(), '/') : Uri::root(true)) . '/' . $path;
    }

    // ---------------------------------------------------------------- output

    private function render(int $appId, int $categoryId): string
    {
        $categories = $this->loadCategories($appId);
        if ($categoryId > 0 && !isset($categories[$categoryId])) {
            return '';
        }

        // Settings of this level of the category tree (per-level overrides); restored afterwards.
        $saved        = $this->params;
        $this->params = $this->levelParams($categories, $categoryId);

        try {
            return $this->renderBlock($appId, $categoryId, $categories);
        } finally {
            $this->params = $saved;
        }
    }

    private function renderBlock(int $appId, int $categoryId, array $categories): string
    {
        $p        = $this->settings();
        $children = array_filter($categories, fn ($cat) => $cat->parent === $categoryId);
        $rawLink  = fn (int $id): string => Route::_($this->categoryLink($appId, $id, $categories), false);
        $link     = fn ($cat): string => $this->preview ? '#' : htmlspecialchars($rawLink($cat->id), ENT_QUOTES, 'UTF-8');
        $crumbs   = $this->breadcrumbSchema($appId, $categoryId, $categories, $rawLink, $p);

        if (!$children && !($p['leafSiblings'] && $categoryId > 0)) {
            return $crumbs;
        }

        if ($p['counter'] || $p['hideEmpty']) {
            $this->addCounts($categories);
        }
        $children = array_filter($children, fn ($cat) => !$p['hideEmpty'] || $cat->count > 0);
        if (!$children) {
            // Last level: the sibling bar (when enabled) instead of the list.
            return ($p['leafSiblings'] && $categoryId > 0 ? $this->renderSiblings($appId, $categoryId, $categories, $link, $p) : '') . $crumbs;
        }

        if ($p['display'] === 'tiles' && $p['popularImage']) {
            $this->addTopProductImages($appId, $categories);
        }
        $manual = $p['display'] === 'tiles' ? $this->manualImages() : [];

        // Visible subcategories of every category: "last level" counters and the "what is inside" panels.
        $kids = [];
        foreach ($categories as $cat) {
            if ($cat->parent > 0 && (!$p['hideEmpty'] || $cat->count > 0)) {
                $kids[$cat->parent][] = $cat;
            }
        }

        $id      = 'bettercategories-' . substr(md5($appId . '-' . $categoryId . '-' . microtime()), 0, 8);
        $sizes   = $this->imageSizes($p);
        $items   = '';
        $count   = 0;
        $withSub = false;
        $schema  = [];
        foreach ($children as $cat) {
            $count++;
            $schema[] = ['name' => $cat->title, 'url' => $rawLink($cat->id)];

            $url   = $link($cat);
            $title = htmlspecialchars($cat->title, ENT_QUOTES, 'UTF-8');
            // "Last level only": the count is shown only on categories without visible subcategories.
            $showCount = $p['counter'] && (!$p['counterLeafOnly'] || empty($kids[$cat->id]));
            $countHtml = $showCount ? ' <span class="bettercategories-count">(' . $cat->count . ')</span>' : '';

            // Panel listing the subcategories of this category ("what is inside").
            $sub = $toggle = '';
            if ($p['subMode'] !== 'none' && !empty($kids[$cat->id])) {
                $withSub = true;
                [$toggle, $sub] = $this->subPanel($id . '-' . $cat->id, $cat, $kids[$cat->id], $url, $link, $p);
            }
            $liClass = $sub !== '' ? ' bettercategories-has-sub' : '';

            if ($p['display'] !== 'tiles') {
                $items .= '<li class="bettercategories-item' . $liClass . '"><a class="bettercategories-link" href="' . $url . '">' . $title . '</a>' . $countHtml . $toggle . $sub . '</li>';
                continue;
            }

            $image = $manual[$cat->id] ?? '';
            if ($image === '' && $p['gridboxImage']) {
                $image = (string) $cat->image;
            }
            if ($image === '' && $p['popularImage']) {
                $image = $cat->topImage;
            }
            // Small WebP copies (srcset) when enabled and possible; the original image otherwise.
            [$thumb, $srcset] = $this->thumbnail($image, $p);
            $src = htmlspecialchars($thumb !== '' ? $thumb : $this->imageUrl($image), ENT_QUOTES, 'UTF-8');
            $set = $srcset !== '' ? ' srcset="' . htmlspecialchars($srcset, ENT_QUOTES, 'UTF-8') . '" sizes="' . $sizes . '"' : '';

            $front = '<a class="bettercategories-link" href="' . $url . '">'
                . '<span class="bettercategories-media' . ($src === '' ? ' bettercategories-media--empty' : '') . '">'
                . ($src !== '' ? '<img src="' . $src . '"' . $set . ' alt="' . $title . '" loading="lazy" decoding="async">' : '')
                . ($p['hover'] === 'shine' ? '<span class="bettercategories-shine" aria-hidden="true"></span>' : '')
                . '</span>'
                . '<span class="bettercategories-caption"><span class="bettercategories-name">' . $title . '</span>' . $countHtml . '</span>'
                . '</a>';
            // Card flip: the front (tile) and the back (subcategories) turn together; the button stays outside.
            $body = $sub !== '' && $p['subMode'] === 'flip' ? $toggle . '<div class="bettercategories-card">' . $front . $sub . '</div>' : $front . $toggle . $sub;

            $items .= '<li class="bettercategories-item bettercategories-tile' . $liClass . '">' . $body . '</li>';
        }

        $classes = [
            'bettercategories',
            'bettercategories--' . $p['display'],
            'bettercategories--' . $p['orientation'],
            'bettercategories--align-' . $p['align'],
        ];
        if ($p['display'] === 'tiles') {
            $classes[] = 'bettercategories--style-' . $p['style'];
            $classes[] = 'bettercategories--shape-' . $p['shape'];
        }
        if ($withSub) {
            $classes[] = 'bettercategories--sub-' . $p['subMode'];
        }
        $p['items']   = $count;
        $p['withSub'] = $withSub;

        $heading  = $p['heading'];
        $headAttr = htmlspecialchars($heading !== '' ? $heading : 'Categories', ENT_QUOTES, 'UTF-8');

        return $this->css($id, $p)
            . '<nav id="' . $id . '" class="' . implode(' ', $classes) . '" aria-label="' . $headAttr . '">'
            . ($heading !== '' ? '<' . $p['headingTag'] . ' class="bettercategories-title">' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</' . $p['headingTag'] . '>' : '')
            . '<ul class="bettercategories-list">' . $items . '</ul></nav>'
            . ($withSub && $p['subMode'] !== 'below' ? $this->subScript($id) : '')
            . ($p['schemaList'] ? $this->itemListSchema($heading !== '' ? $heading : 'Categories', $schema) : '')
            . $crumbs;
    }

    /**
     * Settings for the level of the category tree being shown: 0 = store home, 1 = a top category,
     * 2 = its subcategory, 3 = anything deeper. A matching "per level" row overrides the chosen keys.
     */
    private function levelParams(array $categories, int $categoryId): Registry
    {
        $rows = (array) $this->params->get('level_overrides', []);
        if (!$rows) {
            return $this->params;
        }

        $depth = 0;
        $id    = $categoryId;
        while ($id > 0 && isset($categories[$id]) && $depth < 50) {
            $depth++;
            $id = $categories[$id]->parent;
        }
        $level = (string) min($depth, 3);

        foreach ($rows as $row) {
            $row = (array) $row;
            if ((string) ($row['level'] ?? '') !== $level) {
                continue;
            }

            $params = clone $this->params;
            foreach (['heading', 'display', 'tile_style', 'orientation', 'columns', 'columns_tablet', 'columns_mobile', 'sub_mode'] as $key) {
                $value = trim((string) ($row[$key] ?? ''));
                if ($value !== '') {
                    $params->set($key, $value);
                }
            }

            return $params;
        }

        return $this->params;
    }

    /**
     * Last-level category (no subcategories): a bar with the categories next to it, the current one
     * marked, and a link back to the parent. Products below stay as Gridbox shows them.
     */
    private function renderSiblings(int $appId, int $categoryId, array $categories, callable $link, array $p): string
    {
        $parentId = $categories[$categoryId]->parent;
        $siblings = array_filter($categories, fn ($cat) => $cat->parent === $parentId
            && ($cat->id === $categoryId || !$p['hideEmpty'] || $cat->count > 0));

        $items = '';
        if ($p['leafBack']) {
            $name   = $parentId > 0 ? $categories[$parentId]->title : $this->appTitle($appId);
            $url    = $this->preview ? '#' : htmlspecialchars(Route::_($this->categoryLink($appId, $parentId, $categories), false), ENT_QUOTES, 'UTF-8');
            $items .= '<li class="bettercategories-item bettercategories-back"><a class="bettercategories-link" href="' . $url . '">'
                . '<svg viewBox="0 0 12 12" width="12" height="12" aria-hidden="true" focusable="false"><path d="M8 1.5 3.5 6 8 10.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>'
                . htmlspecialchars(sprintf($p['leafBackLabel'], $name), ENT_QUOTES, 'UTF-8') . '</a></li>';
        }
        if (count($siblings) > 1) {
            foreach ($siblings as $cat) {
                $current = $cat->id === $categoryId;
                $items  .= '<li class="bettercategories-item' . ($current ? ' is-current' : '') . '"><a class="bettercategories-link" href="' . $link($cat) . '"' . ($current ? ' aria-current="page"' : '') . '>'
                    . htmlspecialchars($cat->title, ENT_QUOTES, 'UTF-8')
                    . ($p['counter'] ? ' <span class="bettercategories-count">(' . $cat->count . ')</span>' : '') . '</a></li>';
            }
        }
        if ($items === '') {
            return '';
        }

        $id      = 'bettercategories-' . substr(md5($appId . '-' . $categoryId . '-' . microtime()), 0, 8);
        $heading = count($siblings) > 1 ? $p['leafHeading'] : '';
        $label   = htmlspecialchars($p['leafHeading'] !== '' ? $p['leafHeading'] : 'Related categories', ENT_QUOTES, 'UTF-8');

        return $this->siblingsCss('#' . $id, $p)
            . '<nav id="' . $id . '" class="bettercategories bettercategories--siblings" aria-label="' . $label . '">'
            . ($heading !== '' ? '<' . $p['headingTag'] . ' class="bettercategories-title">' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</' . $p['headingTag'] . '>' : '')
            . '<ul class="bettercategories-list">' . $items . '</ul></nav>'
            // phones scroll the bar sideways: bring the current category into view
            . '<script>(function(n){var c=n&&n.querySelector(".is-current"),l=n&&n.querySelector(".bettercategories-list");if(!c||!l)return;'
            . 'function f(){if(l.scrollWidth<=l.clientWidth)return;var a=l.getBoundingClientRect(),b=c.getBoundingClientRect();l.scrollLeft+=b.left-a.left-(a.width-b.width)/2;}'
            . 'f();window.addEventListener("load",f);})(document.getElementById("' . $id . '"));</script>';
    }

    /** Styles of the sibling bar: chips in a wrapping row, one scrolling row on phones. */
    private function siblingsCss(string $s, array $p): string
    {
        $accent = $p['linkColor'] ?: '#1f2937';
        $pad    = $p['device'] === 'mobile' ? $p['padSideMob'] : $p['padSide'];
        $font   = $p['device'] === 'mobile' ? ($p['fontSizeMob'] ?: $p['fontSize']) : $p['fontSize'];
        $phone  = "$s .bettercategories-list{flex-wrap:nowrap;overflow-x:auto;scroll-snap-type:x proximity;scrollbar-width:thin;padding-bottom:6px;}"
            . "$s .bettercategories-item{flex:0 0 auto;scroll-snap-align:start;}";

        $r   = [];
        $r[] = "$s,$s *,$s *::before,$s *::after{box-sizing:border-box;}";
        // width limits: Gridbox columns are flex boxes, a one-line bar would otherwise widen the page
        $r[] = "$s{width:100%;max-width:100%;min-width:0;margin:{$p['marginTop']}px 0 {$p['marginBottom']}px;padding:0 {$pad}px;text-align:left;" . ($font ? "font-size:$font;" : '') . ($p['textColor'] ? "color:{$p['textColor']};" : '') . '}';
        $r[] = "$s .bettercategories-title{margin:{$p['headTop']}px 0 " . min($p['headBottom'], 12) . 'px;font-size:' . ($p['headingSize'] ?: '1.1em') . ';text-align:inherit;' . ($p['textColor'] ? 'color:inherit;' : '') . '}';
        $r[] = "$s .bettercategories-list{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:8px;}";
        $r[] = "$s .bettercategories-item{margin:0;padding:0;}";
        $r[] = "$s .bettercategories-link{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:999px;background:rgba(0,0,0,.05);line-height:1.3;text-decoration:none;white-space:nowrap;transition:background .2s,color .2s;"
            . ($p['linkColor'] ? "color:{$p['linkColor']};" : '') . '}';
        $r[] = "$s .bettercategories-link:hover{background:rgba(0,0,0,.1);" . ($p['hoverColor'] ? "color:{$p['hoverColor']};" : '') . '}';
        $r[] = "$s .bettercategories-count{opacity:.7;}";
        $r[] = "$s .is-current .bettercategories-link{background:$accent;color:#fff;font-weight:600;}";
        $r[] = "$s .is-current .bettercategories-count{opacity:.85;}";
        $r[] = "$s .bettercategories-back .bettercategories-link{background:transparent;padding-left:2px;font-weight:600;}";
        $r[] = "$s .bettercategories-back svg{display:block;flex:0 0 auto;}";
        // some sites strip @media from the page served to phones: the phone layout then applies directly
        $r[] = $p['device'] === 'mobile' ? $phone : "@media (max-width:768px){ $phone $s{padding:0 {$p['padSideMob']}px;} }";

        return '<style>' . implode('', $r) . '</style>';
    }

    // ---------------------------------------------------------------- thumbnails

    /** "sizes" of the tile images, from the column counts per device. */
    private function imageSizes(array $p): string
    {
        $tablet = $p['colsTablet'] ?: $p['colsDesktop'];
        $mobile = $p['colsMobile'] ?: $tablet;

        return sprintf('(max-width:768px) %dvw, (max-width:1024px) %dvw, %dpx',
            (int) ceil(100 / max(1, $mobile)), (int) ceil(100 / max(1, $tablet)), (int) ceil(1280 / max(1, $p['colsDesktop'])));
    }

    /**
     * WebP copies of a local image at the tile width and twice that (for sharp screens), made once and
     * kept in media/plg_system_bettercategories/thumbs (the file name changes when the image changes).
     * Returns [src, srcset], or ['', ''] when the original should be used: thumbnails switched off,
     * an external or unreadable image, an image not larger than the tile, no WebP support, or no time
     * left in this request (then the next visit makes it).
     *
     * @return array{0: string, 1: string}
     */
    private function thumbnail(string $image, array $p): array
    {
        if (!$p['thumbs'] || trim($image) === '' || !function_exists('imagewebp')) {
            return ['', ''];
        }

        $file = $this->localImage($image);
        $info = $file !== null ? @getimagesize($file) : false;
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true) || $info[0] < 2 || $info[1] < 2) {
            return ['', ''];
        }

        $base  = ($this->preview ? rtrim(Uri::root(), '/') : Uri::root(true)) . '/' . self::THUMB_DIR . '/';
        $hash  = substr(md5($file . '|' . filemtime($file) . '|' . filesize($file) . '|' . $p['thumbQuality']), 0, 16);
        $set      = [];
        $original = false;
        foreach ([$p['thumbWidth'], $p['thumbWidth'] * 2] as $width) {
            if ($width >= $info[0]) {
                // the original is not larger than this copy would be: it serves these screens itself
                $original = true;
                break;
            }
            $name = $hash . '-' . $width . '.webp';
            if (!is_file(JPATH_ROOT . '/' . self::THUMB_DIR . '/' . $name) && !$this->makeThumbnail($file, $info, $name, $width, $p['thumbQuality'])) {
                // not made (yet): never offer a large original in its place
                break;
            }
            $set[] = [$base . $name, $width];
        }
        if (!$set) {
            return ['', ''];
        }

        $srcset = array_map(fn ($t) => $t[0] . ' ' . $t[1] . 'w', $set);
        if ($original) {
            $srcset[] = $this->imageUrl($image) . ' ' . $info[0] . 'w';
        }

        return [$set[0][0], implode(', ', $srcset)];
    }

    /** Absolute path of an image inside the site, or null (external URL, missing file, outside the site). */
    private function localImage(string $image): ?string
    {
        $url = HTMLHelper::cleanImageURL(trim($image))->url;
        if (preg_match('#^([a-z][a-z0-9+.-]*:)?//#i', $url)) {
            return null;
        }

        $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));
        $root = Uri::root(true);
        if ($root !== '' && str_starts_with($path, $root . '/')) {
            $path = substr($path, strlen($root));
        }

        $site = realpath(JPATH_ROOT);
        $file = realpath(JPATH_ROOT . '/' . ltrim($path, '/'));

        return $site && $file && str_starts_with($file, $site . DIRECTORY_SEPARATOR) && is_file($file)
            && preg_match('/\.(jpe?g|png|webp|gif)$/i', $file) ? $file : null;
    }

    /** Creates one WebP thumbnail (GD); false when it cannot (memory, format, write) or time is up. */
    private function makeThumbnail(string $file, array $info, string $name, int $width, int $quality): bool
    {
        if ($this->thumbTime >= self::THUMB_BUDGET) {
            $this->incomplete = true;

            return false;
        }
        // decoded image + thumbnail must fit in the memory left to PHP
        $limit = $this->memoryLimit();
        if ($limit > 0 && memory_get_usage() + $info[0] * $info[1] * 5 + 16 * 1048576 > $limit) {
            return false;
        }

        $t0 = hrtime(true);
        try {
            $src = match ($info[2]) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
                IMAGETYPE_PNG  => @imagecreatefrompng($file),
                IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false,
                IMAGETYPE_GIF  => @imagecreatefromgif($file),
                default        => false,
            };
            if (!$src) {
                return false;
            }

            // photos taken sideways: browsers honour the EXIF orientation, GD does not
            if ($info[2] === IMAGETYPE_JPEG) {
                $angle = [3 => 180, 6 => -90, 8 => 90][$this->jpegOrientation($file)] ?? 0;
                if ($angle !== 0 && ($rotated = imagerotate($src, $angle, 0))) {
                    $src = $rotated;
                }
            }

            $w      = imagesx($src);
            $h      = imagesy($src);
            $height = max(1, (int) round($h * $width / $w));
            $dst    = imagecreatetruecolor($width, $height);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $height, $w, $h);

            $dir = JPATH_ROOT . '/' . self::THUMB_DIR;
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                return false;
            }
            // written under a temporary name first: a visitor never gets a half-written file
            $tmp = $dir . '/' . $name . '.' . bin2hex(random_bytes(4)) . '.tmp';
            $ok  = @imagewebp($dst, $tmp, $quality) && @rename($tmp, $dir . '/' . $name);
            if (!$ok) {
                @unlink($tmp);
            }

            return $ok;
        } catch (\Throwable $e) {
            return false;
        } finally {
            $this->thumbTime += (hrtime(true) - $t0) / 1e9;
        }
    }

    /**
     * EXIF orientation of a JPEG (1 = as stored), read from the file header without the exif
     * extension, which many hosts do not have.
     */
    private function jpegOrientation(string $file): int
    {
        $data = @file_get_contents($file, false, null, 0, 131072);
        if (!is_string($data) || !str_starts_with($data, "\xFF\xD8")) {
            return 1;
        }

        $pos = 2;
        $len = strlen($data);
        while ($pos + 4 <= $len && $data[$pos] === "\xFF") {
            $marker = ord($data[$pos + 1]);
            $size   = unpack('n', substr($data, $pos + 2, 2))[1];
            if ($marker === 0xE1 && substr($data, $pos + 4, 6) === "Exif\0\0") {
                $tiff  = $pos + 10;
                $le    = substr($data, $tiff, 2) === 'II';
                $short = fn (int $o) => $o + 2 <= $len ? unpack($le ? 'v' : 'n', substr($data, $o, 2))[1] : 0;
                $long  = fn (int $o) => $o + 4 <= $len ? unpack($le ? 'V' : 'N', substr($data, $o, 4))[1] : 0;
                $ifd   = $tiff + $long($tiff + 4);
                $count = $short($ifd);
                for ($i = 0; $i < $count && $i < 200; $i++) {
                    $entry = $ifd + 2 + $i * 12;
                    if ($short($entry) === 0x0112) {
                        $value = $short($entry + 8);

                        return $value >= 1 && $value <= 8 ? $value : 1;
                    }
                }

                return 1;
            }
            if ($marker === 0xDA || $size < 2) {
                // image data starts: no EXIF block before it
                break;
            }
            $pos += 2 + $size;
        }

        return 1;
    }

    private function memoryLimit(): int
    {
        $value = trim((string) ini_get('memory_limit'));
        if ($value === '' || $value === '-1') {
            return 0;
        }
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g'     => $number * 1073741824,
            'm'     => $number * 1048576,
            'k'     => $number * 1024,
            default => $number,
        };
    }

    // ---------------------------------------------------------------- structured data

    /** schema.org ItemList of the listed categories (JSON-LD). */
    private function itemListSchema(string $name, array $items): string
    {
        if ($this->preview || !$items) {
            return '';
        }

        $list = [];
        foreach (array_values($items) as $i => $item) {
            $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $item['name'], 'url' => $this->absoluteUrl($item['url'])];
        }

        return $this->jsonLd(['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => $name, 'numberOfItems' => count($list), 'itemListElement' => $list]);
    }

    /**
     * schema.org BreadcrumbList: store → parent categories → current category. "Automatic" adds it
     * only when the page has no breadcrumb data of its own (e.g. from a breadcrumbs module).
     */
    private function breadcrumbSchema(int $appId, int $categoryId, array $categories, callable $rawLink, array $p): string
    {
        if ($this->preview || $categoryId <= 0 || $p['schemaCrumbs'] === 'no' || ($p['schemaCrumbs'] === 'auto' && $this->pageCrumbs)) {
            return '';
        }

        $chain = [];
        $id    = $categoryId;
        while ($id > 0 && isset($categories[$id]) && count($chain) < 50) {
            array_unshift($chain, $categories[$id]);
            $id = $categories[$id]->parent;
        }

        $list = [['@type' => 'ListItem', 'position' => 1, 'name' => $this->appTitle($appId), 'item' => $this->absoluteUrl($rawLink(0))]];
        foreach ($chain as $cat) {
            $list[] = ['@type' => 'ListItem', 'position' => count($list) + 1, 'name' => $cat->title, 'item' => $this->absoluteUrl($rawLink($cat->id))];
        }

        return $this->jsonLd(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list]);
    }

    private function jsonLd(array $data): string
    {
        // JSON_HEX_TAG: a title can never close the script element
        return '<script type="application/ld+json">'
            . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE)
            . '</script>';
    }

    private function absoluteUrl(string $url): string
    {
        return preg_match('#^https?://#i', $url) ? $url : rtrim(Uri::getInstance()->toString(['scheme', 'host', 'port']), '/') . '/' . ltrim($url, '/');
    }

    /** Title of a store app (the first breadcrumb and the parent of top-level categories). */
    private function appTitle(int $appId): string
    {
        if (!isset($this->appTitles[$appId])) {
            $db    = $this->db();
            $query = $db->createQuery()
                ->select($db->quoteName('title'))
                ->from($db->quoteName('#__gridbox_app'))
                ->where($db->quoteName('id') . ' = ' . $appId);
            $this->appTitles[$appId] = trim((string) $db->setQuery($query)->loadResult()) ?: 'Store';
        }

        return $this->appTitles[$appId];
    }

    /**
     * "What is inside" panel of one category: optional title, links to its subcategories (up to the
     * limit, then "+N more"), and for the card back a link to the whole category. Interactive modes
     * get a toggle button (touch screens, keyboard); hover opens them too unless "click only" is set.
     */
    /** @return array{0: string, 1: string} toggle button, panel */
    private function subPanel(string $panelId, object $cat, array $kids, string $url, callable $link, array $p): array
    {
        $max   = $p['subMax'] > 0 ? $p['subMax'] : count($kids);
        $shown = array_slice($kids, 0, $max);
        $more  = count($kids) - count($shown);

        $list = '';
        foreach ($shown as $kid) {
            $list .= '<li><a href="' . $link($kid) . '">' . htmlspecialchars($kid->title, ENT_QUOTES, 'UTF-8') . '</a>'
                . ($p['subCounts'] ? ' <span class="bettercategories-count">(' . $kid->count . ')</span>' : '') . '</li>';
        }
        if ($more > 0) {
            $list .= '<li class="bettercategories-sub-more"><a href="' . $url . '">' . htmlspecialchars(sprintf($p['subMoreLabel'], $more), ENT_QUOTES, 'UTF-8') . '</a></li>';
        }

        $inner = ($p['subTitle'] !== '' ? '<div class="bettercategories-sub-title">' . htmlspecialchars($p['subTitle'], ENT_QUOTES, 'UTF-8') . '</div>' : '')
            . '<ul class="bettercategories-sublist bettercategories-sublist--' . $p['subStyle'] . '">' . $list . '</ul>'
            . ($p['subMode'] === 'flip' ? '<a class="bettercategories-sub-all" href="' . $url . '">' . htmlspecialchars($p['subAllLabel'], ENT_QUOTES, 'UTF-8') . '</a>' : '');

        if ($p['subMode'] === 'below') {
            return ['', '<div class="bettercategories-sub">' . $inner . '</div>'];
        }

        $label = htmlspecialchars(sprintf($p['subToggleLabel'], $cat->title), ENT_QUOTES, 'UTF-8');

        return [
            '<button type="button" class="bettercategories-toggle" aria-expanded="false" aria-controls="' . $panelId . '" aria-label="' . $label . '" title="' . $label . '">'
                . '<svg viewBox="0 0 12 12" width="12" height="12" aria-hidden="true" focusable="false"><path d="M6 1v10M1 6h10" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/></svg></button>',
            '<div class="bettercategories-sub" id="' . $panelId . '"><div class="bettercategories-sub-inner">' . $inner . '</div></div>',
        ];
    }

    /**
     * Toggle buttons of one block: open/close a panel, close the others, close on Escape or a click
     * outside. Scoped to the block; no dependencies.
     */
    private function subScript(string $id): string
    {
        return '<script>(function(n){if(!n)return;'
            // fit(): a tooltip stays inside the window; a drawer under a narrow tile (< 200 px) opens
            // across the whole row of the list, so its text is not squeezed into the tile width.
            . 'var tip=n.classList.contains("bettercategories--sub-tooltip"),dr=n.classList.contains("bettercategories--sub-drawer"),ul=n.querySelector(".bettercategories-list");'
            . 'function fit(li){var s=li.querySelector(".bettercategories-sub");if(!s)return;'
            . 'if(tip){s.classList.remove("bettercategories-sub--down");s.style.setProperty("--bc-dx","0px");var r=s.getBoundingClientRect();if(r.top<8){s.classList.add("bettercategories-sub--down");r=s.getBoundingClientRect();}var w=document.documentElement.clientWidth,d=0;if(r.left<8)d=8-r.left;else if(r.right>w-8)d=w-8-r.right;s.style.setProperty("--bc-dx",Math.round(d)+"px");}'
            . 'else if(dr&&ul){var a=ul.getBoundingClientRect(),b=li.getBoundingClientRect();if(b.width<200&&a.width>b.width+1){s.style.width=a.width+"px";s.style.marginLeft=Math.round(a.left-b.left)+"px";}else{s.style.width="";s.style.marginLeft="";}}}'
            . 'if(tip||dr){var last=null;n.addEventListener("pointerover",function(e){var li=e.target.closest(".bettercategories-has-sub");if(li&&li!==last){last=li;fit(li);}});'
            . 'n.addEventListener("focusin",function(e){var li=e.target.closest(".bettercategories-has-sub");if(li)fit(li);});'
            . 'window.addEventListener("resize",function(){last=null;n.querySelectorAll(".bettercategories-has-sub.is-open").forEach(fit);});}'
            . 'function set(li,o){if(o)fit(li);li.classList.toggle("is-open",o);var b=li.querySelector(".bettercategories-toggle");if(b)b.setAttribute("aria-expanded",o?"true":"false");}'
            . 'function closeAll(k){n.querySelectorAll(".bettercategories-item.is-open").forEach(function(li){if(li!==k)set(li,false);});}'
            . 'n.addEventListener("click",function(e){var b=e.target.closest(".bettercategories-toggle");if(!b||!n.contains(b))return;e.preventDefault();var li=b.closest(".bettercategories-item");var o=!li.classList.contains("is-open");closeAll(li);set(li,o);});'
            . 'document.addEventListener("click",function(e){if(!n.contains(e.target))closeAll(null);});'
            . 'n.addEventListener("keydown",function(e){if(e.key==="Escape"){var li=e.target.closest(".bettercategories-item.is-open");closeAll(null);if(li){var b=li.querySelector(".bettercategories-toggle");if(b)b.focus();}}});'
            . '})(document.getElementById("' . $id . '"));</script>';
    }

    /** Validated settings (every value is safe to print into CSS/HTML). */
    private function settings(): array
    {
        $pick = function (string $key, array $allowed, string $default): string {
            $value = (string) $this->params->get($key, $default);

            return in_array($value, $allowed, true) ? $value : $default;
        };
        $int = fn (string $key, int $default, int $min, int $max) => max($min, min($max, (int) $this->params->get($key, $default)));

        $display = $pick('display', ['text', 'tiles'], 'text');
        $columns = $int('columns', 0, 0, 8);

        return [
            'heading'      => trim((string) $this->params->get('heading', 'Categories')),
            'headingTag'   => $pick('heading_tag', ['h2', 'h3', 'h4', 'div'], 'h2'),
            'counter'      => (bool) $this->params->get('show_counter', 1),
            'hideEmpty'    => (bool) $this->params->get('hide_empty', 1),
            'counterLeafOnly' => (bool) $this->params->get('counter_leaf_only', 0),
            'hideProducts' => (bool) $this->params->get('hide_products', 0),
            'hideFilters'  => (bool) $this->params->get('hide_filters', 0),
            'display'      => $display,
            'orientation'  => $this->direction($display),
            'align'        => $pick('align', ['left', 'center', 'right'], 'left'),
            // 0 = automatic: one column of text links, four tiles per row
            'colsDesktop'  => $columns ?: ($display === 'tiles' ? 4 : 1),
            'colsTablet'   => $int('columns_tablet', 0, 0, 8),
            'colsMobile'   => $int('columns_mobile', 0, 0, 8),
            'device'       => $this->preview ? 'desktop' : $this->device(),
            'gap'          => $int('gap', 16, 0, 80),
            'fontSize'     => $this->cssSize((string) $this->params->get('font_size', '')),
            'headingSize'  => $this->cssSize((string) $this->params->get('heading_size', '')),
            'fontSizeMob'  => $this->cssSize((string) $this->params->get('font_size_mobile', '')),
            'headingSizeMob' => $this->cssSize((string) $this->params->get('heading_size_mobile', '')),
            'marginTop'    => $int('margin_top', 0, -200, 400),
            'marginBottom' => $int('margin_bottom', 24, -200, 400),
            'padSide'      => $int('padding_side', 0, 0, 200),
            'padSideMob'   => $int('padding_side_mobile', 16, 0, 200),
            'textColor'    => $this->cssColor((string) $this->params->get('text_color', '')),
            'linkColor'    => $this->cssColor((string) $this->params->get('link_color', '')),
            'hoverColor'   => $this->cssColor((string) $this->params->get('link_hover_color', '')),
            'style'        => $pick('tile_style', self::STYLES, 'below'),
            'shape'        => $pick('image_shape', self::SHAPES, 'rounded'),
            'ratio'        => self::RATIOS[$pick('image_ratio', array_keys(self::RATIOS), '1-1')],
            'fit'          => $pick('image_fit', ['contain', 'cover'], 'contain'),
            'radius'       => $int('image_radius', 12, 0, 60),
            'gridboxImage' => (bool) $this->params->get('use_gridbox_image', 1),
            'popularImage' => (bool) $this->params->get('use_popular_image', 1),
            'overlayColor' => $this->cssColor((string) $this->params->get('overlay_color', '')) ?: '#000000',
            'imageBg'      => $this->cssColor((string) $this->params->get('image_bg', '#ffffff')) ?: 'transparent',
            'tintColor'    => $this->cssColor((string) $this->params->get('tint_color', '')),
            'tintOpacity'  => $int('tint_opacity', 0, 0, 100) / 100,
            'hover'        => $pick('hover_effect', self::HOVERS, 'zoom'),
            'shadow'       => (bool) $this->params->get('shadow', 0),
            'shadowColor'  => $this->cssColor((string) $this->params->get('shadow_color', '#000000')) ?: '#000000',
            'shadowAngle'  => $int('shadow_angle', 90, 0, 360),
            'shadowDist'   => $int('shadow_distance', 8, 0, 100),
            'shadowBlur'   => $int('shadow_blur', 20, 0, 150),
            'shadowSpread' => $int('shadow_spread', 0, -50, 50),
            'shadowAlpha'  => $int('shadow_opacity', 20, 0, 100) / 100,
            'headTop'      => $int('heading_margin_top', 0, -100, 200),
            'headBottom'   => $int('heading_margin_bottom', 16, -100, 200),
            'capTop'       => $int('caption_margin_top', 10, -100, 200),
            'capBottom'    => $int('caption_margin_bottom', 0, -100, 200),
            'fill'         => $pick('fill_mode', ['left', 'center', 'stretch'], 'left'),
            'subMode'      => $this->subMode($display, $this->preview ? 'desktop' : $this->device()),
            'subTrigger'   => $pick('sub_trigger', ['hover', 'click'], 'hover'),
            'subStyle'     => $pick('sub_style', ['list', 'inline', 'chips'], 'list'),
            'subTitle'     => trim((string) $this->params->get('sub_title', 'In this category:')),
            'subMax'       => $int('sub_max', 6, 0, 50),
            'subCounts'    => (bool) $this->params->get('sub_counts', 0),
            'subBg'        => $this->cssColor((string) $this->params->get('sub_bg', '#ffffff')) ?: '#ffffff',
            'subColor'     => $this->cssColor((string) $this->params->get('sub_color', '')),
            'subAllLabel'  => trim((string) $this->params->get('sub_all_label', 'View all')) ?: 'View all',
            'subMoreLabel' => $this->printfLabel((string) $this->params->get('sub_more_label', '+%d more'), '+%d more'),
            'subToggleLabel' => $this->printfLabel((string) $this->params->get('sub_toggle_label', 'Subcategories of %s'), 'Subcategories of %s', 's'),
            'thumbs'       => (bool) $this->params->get('thumbnails', 1),
            'thumbWidth'   => $int('thumb_width', 480, 160, 1600),
            'thumbQuality' => $int('thumb_quality', 80, 40, 95),
            'leafSiblings' => (bool) $this->params->get('leaf_siblings', 0),
            'leafHeading'  => trim((string) $this->params->get('leaf_heading', 'Related categories')),
            'leafBack'     => (bool) $this->params->get('leaf_back', 1),
            'leafBackLabel' => $this->printfLabel((string) $this->params->get('leaf_back_label', 'Back to %s'), 'Back to %s', 's'),
            'schemaList'   => (bool) $this->params->get('schema_itemlist', 1),
            'schemaCrumbs' => $pick('schema_breadcrumb', ['auto', 'yes', 'no'], 'auto'),
        ];
    }

    /**
     * List direction: rows (row by row), columns (column by column), scroll (one scrolling row) or
     * inline (text links in one wrapping line). Settings saved before 1.0.0 used vertical/horizontal.
     */
    private function direction(string $display): string
    {
        $value = (string) $this->params->get('orientation', 'rows');
        if ($value === 'vertical') {
            return 'rows';
        }
        if ($value === 'horizontal') {
            return $display === 'tiles' ? 'scroll' : 'inline';
        }
        if ($value === 'inline' && $display === 'tiles') {
            return 'rows';
        }

        return in_array($value, self::DIRECTIONS, true) ? $value : 'rows';
    }

    /**
     * Subcategory panel mode; card flip and side drawer need tiles (text links fall back to the drawer).
     * Phones can use their own mode: "auto" turns the flip and side panel (too small on a narrow tile)
     * into the drawer, "same" keeps the main mode.
     */
    private function subMode(string $display, string $device): string
    {
        $mode = (string) $this->params->get('sub_mode', 'none');
        $mode = in_array($mode, self::SUB_MODES, true) ? $mode : 'none';
        if ($device === 'mobile' && $mode !== 'none') {
            $phone = (string) $this->params->get('sub_mode_mobile', 'auto');
            if ($phone === 'auto') {
                $mode = in_array($mode, ['flip', 'side'], true) ? 'drawer' : $mode;
            } elseif ($phone !== 'same' && in_array($phone, self::SUB_MODES, true)) {
                $mode = $phone;
            }
        }

        return $display !== 'tiles' && in_array($mode, ['flip', 'side'], true) ? 'drawer' : $mode;
    }

    /** A label with exactly one printf placeholder of the given type (%d or %s), else the default. */
    private function printfLabel(string $value, string $default, string $type = 'd'): string
    {
        $value = trim(str_replace('%%', '', $value)) === '' ? '' : trim($value);

        return $value !== '' && substr_count($value, '%') === 1 && str_contains($value, '%' . $type) ? $value : $default;
    }

    private function cssSize(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d+(\.\d+)?$/', $value)) {
            return $value . 'px';
        }

        return preg_match('/^\d+(\.\d+)?(px|rem|em|%|vw)$/', $value) ? $value : '';
    }

    private function cssColor(string $value): string
    {
        $value = trim($value);

        return preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s.,%]+\)|[a-zA-Z]{3,20})$/', $value) && strtolower($value) !== 'none' ? $value : '';
    }

    /**
     * Scoped styles of one block. Empty typography/colour settings leave the Gridbox theme values.
     */
    private function css(string $id, array $p): string
    {
        $s    = '#' . $id;
        $rows = [];

        $tablet = $p['colsTablet'] ?: $p['colsDesktop'];
        $mobile = $p['colsMobile'] ?: $tablet;
        // Per device: columns, font sizes and side margin (desktop and tablet share fonts and margin).
        $devices = [
            'desktop' => ['cols' => $p['colsDesktop'], 'font' => $p['fontSize'], 'head' => $p['headingSize'], 'pad' => $p['padSide']],
            'tablet'  => ['cols' => $tablet, 'font' => $p['fontSize'], 'head' => $p['headingSize'], 'pad' => $p['padSide']],
            'mobile'  => ['cols' => $mobile, 'font' => $p['fontSizeMob'] ?: $p['fontSize'], 'head' => $p['headingSizeMob'] ?: $p['headingSize'], 'pad' => $p['padSideMob']],
        ];
        $block = function (array $d) use ($s, $p): string {
            return "$s{--bcat-cols:{$d['cols']};padding-left:{$d['pad']}px;padding-right:{$d['pad']}px;" . ($d['font'] ? "font-size:{$d['font']};" : '') . '}'
                . ($d['head'] ? "$s .bettercategories-title{font-size:{$d['head']};}" : '')
                . $this->fillCss($s, $p, (int) $d['cols']);
        };

        $rows[] = "$s{--bcat-gap:{$p['gap']}px;margin:{$p['marginTop']}px 0 {$p['marginBottom']}px;text-align:{$p['align']};"
            . ($p['textColor'] ? "color:{$p['textColor']};" : '') . '}';
        $rows[] = $block($devices[$p['device']]);
        $rows[] = "$s .bettercategories-title{margin:{$p['headTop']}px 0 {$p['headBottom']}px;text-align:inherit;" . ($p['textColor'] ? 'color:inherit;' : '') . '}';
        $rows[] = "$s .bettercategories-list{list-style:none;margin:0;padding:0;gap:var(--bcat-gap);}";
        $rows[] = "$s,$s *,$s *::before,$s *::after{box-sizing:border-box;}";
        $rows[] = "$s .bettercategories-item{margin:0;padding:0;min-width:0;}";
        $rows[] = "$s .bettercategories-link{" . ($p['linkColor'] ? "color:{$p['linkColor']};" : '') . (($p['fontSize'] || $p['fontSizeMob']) ? 'font-size:inherit;' : '') . '}';
        if ($p['hoverColor']) {
            $rows[] = "$s .bettercategories-link:hover,$s .bettercategories-link:focus{color:{$p['hoverColor']};}";
        }
        $rows[] = "$s .bettercategories-count{opacity:.75;}";

        $justify = ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end'][$p['align']];

        $rowGap = $p['display'] === 'text' ? 'calc(var(--bcat-gap)/2)' : 'var(--bcat-gap)';
        switch ($p['orientation']) {
            case 'inline':
                // text links in one line, wrapping
                $rows[] = "$s .bettercategories-list{display:flex;flex-wrap:wrap;justify-content:$justify;column-gap:calc(var(--bcat-gap)*1.5);row-gap:$rowGap;}";
                break;
            case 'columns':
                // column by column: fill the first column top to bottom, then the next
                $rows[] = "$s .bettercategories-list{display:block;column-count:var(--bcat-cols);column-gap:var(--bcat-gap);}";
                $rows[] = "$s .bettercategories-item{break-inside:avoid;margin-bottom:$rowGap;}";
                break;
            case 'scroll':
                // one row, scrolled sideways; --bcat-cols items fit the width
                $rows[] = "$s .bettercategories-list{display:grid;grid-auto-flow:column;grid-auto-columns:calc((100% - (var(--bcat-cols) - 1)*var(--bcat-gap))/var(--bcat-cols));overflow-x:auto;scroll-snap-type:x mandatory;padding-bottom:6px;row-gap:$rowGap;}";
                $rows[] = "$s .bettercategories-item{scroll-snap-align:start;}";
                break;
            default:
                // row by row: left to right, then the next row
                $rows[] = "$s .bettercategories-list{display:grid;grid-template-columns:repeat(var(--bcat-cols),minmax(0,1fr));justify-content:$justify;row-gap:$rowGap;}";
        }

        if ($p['display'] === 'tiles') {

            $radius = ['rect' => '0', 'rounded' => $p['radius'] . 'px', 'circle' => '50%'][$p['shape']];
            $ratio  = $p['shape'] === 'circle' ? '1 / 1' : $p['ratio'];

            $shadow = $p['shadow'] ? $this->shadow($p, 1.0) : '';
            $ease   = 'cubic-bezier(.2,.7,.2,1)';

            $rows[] = "$s .bettercategories-tile .bettercategories-link{display:flex;flex-direction:column;align-items:$justify;gap:0;height:100%;text-decoration:none;position:relative;transition:transform .35s $ease;}";
            $rows[] = "$s .bettercategories-media{display:block;width:100%;aspect-ratio:$ratio;border-radius:$radius;overflow:hidden;background:{$p['imageBg']};position:relative;isolation:isolate;transition:box-shadow .35s $ease,outline-color .35s;"
                . ($shadow ? "box-shadow:$shadow;" : '') . '}';
            $rows[] = "$s .bettercategories-media img{display:block;width:100%;height:100%;object-fit:{$p['fit']};position:relative;z-index:0;transition:transform .5s $ease,filter .5s;}";
            // colour tint over the image (::before), under the texts
            $rows[] = "$s .bettercategories-media::before{content:'';position:absolute;inset:0;z-index:1;pointer-events:none;background:" . ($p['tintColor'] ?: 'transparent')
                . ';opacity:' . ($p['hover'] === 'tint_show' ? 0 : $p['tintOpacity']) . ";transition:opacity .4s $ease;}";
            $rows[] = "$s .bettercategories-caption{display:block;width:100%;line-height:1.3;overflow-wrap:anywhere;hyphens:auto;position:relative;z-index:3;margin:{$p['capTop']}px 0 {$p['capBottom']}px;}";
            $rows[] = "$s .bettercategories-name{font-weight:600;}";

            $hover = "$s .bettercategories-tile .bettercategories-link:hover";
            switch ($p['hover']) {
                case 'zoom':
                    $rows[] = "$hover img{transform:scale(1.07);}";
                    break;
                case 'zoom_out':
                    $rows[] = "$s .bettercategories-media img{transform:scale(1.12);}";
                    $rows[] = "$hover img{transform:scale(1);}";
                    break;
                case 'lift':
                    $rows[] = "$hover{transform:translateY(-6px);}";
                    $rows[] = "$hover .bettercategories-media{box-shadow:" . ($p['shadow'] ? $this->shadow($p, 1.8) : '0 14px 28px rgba(0,0,0,.18)') . ';}';
                    break;
                case 'shine':
                    $rows[] = "$s .bettercategories-shine{position:absolute;top:0;left:-75%;z-index:2;width:50%;height:100%;pointer-events:none;"
                        . "background:linear-gradient(100deg,rgba(255,255,255,0) 0%,rgba(255,255,255,.55) 50%,rgba(255,255,255,0) 100%);transform:skewX(-20deg);}";
                    $rows[] = "$hover .bettercategories-shine{animation:bcat-shine-$id .8s ease-out;}";
                    $rows[] = "@keyframes bcat-shine-$id{to{left:125%;}}";
                    break;
                case 'grayscale':
                    $rows[] = "$s .bettercategories-media img{filter:grayscale(1);}";
                    $rows[] = "$hover img{filter:none;transform:scale(1.03);}";
                    break;
                case 'tint_reveal':
                    $rows[] = "$hover .bettercategories-media::before{opacity:0;}";
                    break;
                case 'tint_show':
                    $rows[] = "$hover .bettercategories-media::before{opacity:" . ($p['tintOpacity'] ?: .35) . ';' . ($p['tintColor'] ? '' : 'background:#000;') . '}';
                    break;
                case 'tilt':
                    $rows[] = "$hover img{transform:scale(1.08) rotate(-2deg);}";
                    break;
                case 'ring':
                    $rows[] = "$s .bettercategories-media{outline:3px solid transparent;outline-offset:3px;}";
                    $rows[] = "$hover .bettercategories-media{outline-color:" . ($p['hoverColor'] ?: ($p['linkColor'] ?: 'currentColor')) . ';}';
                    break;
            }
            if ($p['orientation'] === 'scroll' && $p['shadow']) {
                // room for the shadow inside the scrolling row
                $pad = $p['shadowDist'] + $p['shadowBlur'] + max(0, $p['shadowSpread']);
                $rows[] = "$s .bettercategories-list{padding:{$pad}px;margin:-{$pad}px;}";
            }

            switch ($p['style']) {
                case 'overlay':
                    // text over the bottom of the image on a gradient
                    $rows[] = "$s .bettercategories-caption{position:absolute;left:0;right:0;bottom:0;margin:0;max-height:100%;overflow:hidden;border-radius:0 0 $radius $radius;padding:" . ($p['shape'] === 'circle' ? '30% 18% 16%' : '32px 12px 10px') . ";color:#fff;font-size:.9em;"
                        . ($p['shape'] === 'circle' ? 'text-align:center;' : '')
                        . "background:linear-gradient(to top," . $this->alpha($p['overlayColor'], .75) . ',' . $this->alpha($p['overlayColor'], 0) . ');}';
                    $rows[] = "$s .bettercategories-caption .bettercategories-count{opacity:.9;}";
                    break;

                case 'cover':
                    // the image fills the tile, text centred over a tint
                    $rows[] = "$s .bettercategories-media img{object-fit:cover;}";
                    $rows[] = "$s .bettercategories-media::after{content:'';position:absolute;inset:0;z-index:1;background:" . $this->alpha($p['overlayColor'], .45) . ';transition:background .3s;}';
                    $rows[] = "$s .bettercategories-tile .bettercategories-link:hover .bettercategories-media::after{background:" . $this->alpha($p['overlayColor'], .3) . ';}';
                    $rows[] = "$s .bettercategories-caption{position:absolute;inset:0;margin:0;border-radius:$radius;display:flex;flex-direction:column;align-items:center;justify-content:center;overflow:hidden;padding:" . ($p['shape'] === 'circle' ? '16%' : '12px') . ";text-align:center;color:#fff;font-size:.95em;}";
                    break;

                case 'card':
                    $rows[] = "$s .bettercategories-tile .bettercategories-link{padding:12px;border:1px solid rgba(0,0,0,.08);border-radius:" . ($p['shape'] === 'rect' ? '0' : $p['radius'] . 'px') . ';background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.06);transition:box-shadow .3s,transform .35s;}';
                    $rows[] = "$s .bettercategories-tile .bettercategories-link:hover{box-shadow:0 6px 18px rgba(0,0,0,.12);}";
                    break;
            }
            if ($p['style'] !== 'overlay' && $p['style'] !== 'cover') {
                $rows[] = "$s .bettercategories-caption{text-align:{$p['align']};}";
            }
        }

        // Every width range explicitly: some sites strip @media from the HTML served to phones, so the
        // base value above already matches the visitor's device; these rules fix a cached page shown
        // on another device.
        $rows[] = '@media (min-width:1025px){ ' . $block($devices['desktop']) . ' }';
        $rows[] = '@media (min-width:769px) and (max-width:1024px){ ' . $block($devices['tablet']) . ' }';
        $rows[] = '@media (max-width:768px){ ' . $block($devices['mobile']) . ' }';

        if ($p['withSub']) {
            $rows[] = $this->subCss($s, $p);
        }

        if ($p['hideProducts'] && !$this->preview) {
            // Parent categories list subcategories only; products appear on the last level.
            $rows[] = '.ba-item-blog-posts{display:none!important}';
            if ($p['hideFilters']) {
                $rows[] = '.ba-item-fields-filter{display:none!important}';
            }
        }

        return '<style>' . implode('', $rows) . '</style>';
    }

    /**
     * Fewer items than columns on this device: keep them left (default), centre them at the normal
     * column width, or stretch them over the full width.
     */
    private function fillCss(string $s, array $p, int $cols): string
    {
        $n = (int) ($p['items'] ?? 0);
        if ($p['fill'] === 'left' || $n <= 0 || $n >= $cols) {
            return '';
        }

        $width = "calc((100% - ($cols - 1)*var(--bcat-gap))/$cols)";
        switch ($p['orientation']) {
            case 'rows':
                return $p['fill'] === 'stretch'
                    ? "$s .bettercategories-list{grid-template-columns:repeat($n,minmax(0,1fr));}"
                    : "$s .bettercategories-list{grid-template-columns:repeat($n,$width);justify-content:center;}";
            case 'scroll':
                return $p['fill'] === 'stretch'
                    ? "$s .bettercategories-list{grid-auto-columns:calc((100% - ($n - 1)*var(--bcat-gap))/$n);}"
                    : "$s .bettercategories-list{justify-content:center;}";
            case 'columns':
                return $p['fill'] === 'stretch'
                    ? "$s .bettercategories-list{column-count:$n;}"
                    : "$s .bettercategories-list{column-count:$n;max-width:calc($n*$width + ($n - 1)*var(--bcat-gap));margin-left:auto;margin-right:auto;}";
            default: // inline text links
                return $p['fill'] === 'stretch'
                    ? "$s .bettercategories-item{flex:1 1 0;text-align:center;}"
                    : "$s .bettercategories-list{justify-content:center;}";
        }
    }

    /** Styles of the subcategory panels ("what is inside") for the chosen mode. */
    private function subCss(string $s, array $p): string
    {
        $mode   = $p['subMode'];
        $bg     = $p['subBg'];
        $color  = $p['subColor'] ? "color:{$p['subColor']};" : '';
        $ease   = 'cubic-bezier(.2,.7,.2,1)';
        $radius = $p['display'] === 'tiles' ? ['rect' => '0', 'rounded' => $p['radius'] . 'px', 'circle' => '14px'][$p['shape']] : '8px';
        $open   = "$s .bettercategories-has-sub.is-open";
        // Hover opens panels only where a real pointer can hover (touch screens use the button).
        $hover  = $p['subTrigger'] === 'hover';
        // Keyboard focus on the category link opens the drawer and tooltip (flip and side would cover
        // the focused link); focus inside a panel keeps it open. Focus on the toggle button does not,
        // so Escape (which returns focus to the button) really closes the panel.
        $focus  = in_array($mode, ['drawer', 'tooltip'], true)
            ? "$s .bettercategories-has-sub:has(.bettercategories-link:focus-visible,.bettercategories-sub:focus-within)"
            : "$s .bettercategories-has-sub:has(.bettercategories-sub:focus-within)";
        $both   = fn (string $sel, string $decl) => "$open $sel,$focus $sel{{$decl}}"
            . ($hover ? "@media (hover:hover){ $s .bettercategories-has-sub:hover $sel{{$decl}} }" : '');

        $r = [];
        // Shared content
        $r[] = "$s .bettercategories-sub-title{font-weight:600;font-size:.85em;opacity:.8;margin:0 0 .35em;}";
        $r[] = "$s .bettercategories-sublist{list-style:none;margin:0;padding:0;font-size:.9em;line-height:1.45;}";
        $r[] = "$s .bettercategories-sublist li{margin:0;padding:0;}";
        $r[] = "$s .bettercategories-sublist a{text-decoration:none;" . ($p['linkColor'] ? "color:{$p['linkColor']};" : '') . '}';
        $r[] = "$s .bettercategories-sublist a:hover{text-decoration:underline;}";
        $r[] = "$s .bettercategories-sublist--inline li{display:inline;}";
        $r[] = "$s .bettercategories-sublist--inline li:not(:last-child)::after{content:', ';}";
        $r[] = "$s .bettercategories-sublist--chips{display:flex;flex-wrap:wrap;gap:6px;}";
        $r[] = "$s .bettercategories-sublist--chips a{display:inline-block;padding:3px 10px;border-radius:999px;background:rgba(0,0,0,.06);font-size:.9em;}";
        $r[] = "$s .bettercategories-sublist--chips a:hover{text-decoration:none;background:rgba(0,0,0,.12);}";
        $r[] = "$s .bettercategories-sub-more a{font-weight:600;}";
        $r[] = "$s .bettercategories-sub-all{display:inline-block;margin-top:.6em;font-weight:600;text-decoration:none;" . ($p['linkColor'] ? "color:{$p['linkColor']};" : '') . '}';
        $r[] = "$s .bettercategories-has-sub{position:relative;}";

        if ($mode === 'below' || $mode === 'drawer') {
            // the list sits under the tile: the tile must not stretch over the list's space
            $r[] = "$s .bettercategories-has-sub > .bettercategories-link{height:auto;}";
        }
        if ($mode === 'below') {
            $r[] = "$s .bettercategories-sub{margin-top:.4em;text-align:inherit;$color}";
            if ($p['display'] === 'tiles') {
                $r[] = "$s .bettercategories-tile .bettercategories-sub{text-align:{$p['align']};}";
            }

            return implode('', $r);
        }

        // Toggle button: "+" that turns into "×"
        $r[] = "$s .bettercategories-toggle{position:absolute;top:8px;right:8px;z-index:6;width:30px;height:30px;padding:0;border:0;border-radius:50%;background:rgba(255,255,255,.92);box-shadow:0 1px 4px rgba(0,0,0,.2);cursor:pointer;display:flex;align-items:center;justify-content:center;color:#333;}";
        $r[] = "$s .bettercategories-toggle svg{display:block;width:12px;height:12px;transition:transform .3s $ease;}";
        $r[] = "$open .bettercategories-toggle svg{transform:rotate(45deg);}";
        $r[] = "$s .bettercategories-toggle:focus-visible{outline:2px solid currentColor;outline-offset:2px;}";
        if ($p['display'] !== 'tiles') {
            $r[] = "$s .bettercategories-toggle{position:relative;top:auto;right:auto;display:inline-flex;vertical-align:middle;width:22px;height:22px;margin-left:6px;box-shadow:none;background:rgba(0,0,0,.06);}";
        }

        switch ($mode) {
            case 'drawer':
                // Slides open below the category and pushes the rest of the layout down.
                $r[] = "$s .bettercategories-list{align-items:start;}";
                $r[] = "$s .bettercategories-sub{display:grid;grid-template-rows:0fr;transition:grid-template-rows .35s $ease;}";
                $r[] = "$s .bettercategories-sub-inner{overflow:hidden;min-height:0;}";
                $r[] = "$s .bettercategories-sub-inner > *:first-child{margin-top:.6em;}";
                $r[] = "$s .bettercategories-sub-inner{padding:0 .75em;border-radius:$radius;background:$bg;$color}";
                $r[] = $both('.bettercategories-sub', 'grid-template-rows:1fr;');
                // a drawer widened across the row (narrow tiles) lies over the neighbouring tiles' free space
                $r[] = "$open,$focus" . "{z-index:7;}" . ($hover ? "@media (hover:hover){ $s .bettercategories-has-sub:hover{z-index:7;} }" : '');
                $r[] = $both('.bettercategories-sub-inner', 'padding-bottom:.6em;box-shadow:0 4px 14px rgba(0,0,0,.08);');
                break;

            case 'side':
                // Slides in from the side over the tile.
                $r[] = "$s .bettercategories-tile{overflow:hidden;border-radius:$radius;}";
                $r[] = "$s .bettercategories-sub{position:absolute;inset:0;z-index:5;overflow:hidden auto;overflow-wrap:break-word;scrollbar-width:thin;padding:14px 44px 14px 14px;background:$bg;$color"
                    . "transform:translateX(102%);transition:transform .4s $ease;text-align:left;}";
                $r[] = $both('.bettercategories-sub', 'transform:translateX(0);box-shadow:-6px 0 18px rgba(0,0,0,.12);');
                break;

            case 'flip':
                // The tile turns over; its back lists the subcategories.
                $r[] = "$s .bettercategories-tile{perspective:1200px;}";
                $r[] = "$s .bettercategories-card{position:relative;height:100%;transform-style:preserve-3d;transition:transform .6s $ease;}";
                $r[] = "$s .bettercategories-card > .bettercategories-link{backface-visibility:hidden;-webkit-backface-visibility:hidden;}";
                $r[] = "$s .bettercategories-sub{position:absolute;inset:0;z-index:5;overflow:hidden auto;overflow-wrap:break-word;scrollbar-width:thin;padding:16px 44px 16px 16px;border-radius:$radius;background:$bg;$color"
                    . "transform:rotateY(180deg);backface-visibility:hidden;-webkit-backface-visibility:hidden;text-align:left;box-shadow:0 6px 18px rgba(0,0,0,.12);}";
                $r[] = $both('.bettercategories-card', 'transform:rotateY(180deg);');
                break;

            case 'tooltip':
                // A small bubble above the category.
                $r[] = "$s .bettercategories-sub{position:absolute;left:50%;bottom:calc(100% + 10px);z-index:30;width:max-content;max-width:min(300px,90vw);padding:12px 14px;border-radius:10px;background:$bg;$color"
                    . "box-shadow:0 10px 30px rgba(0,0,0,.18);text-align:left;opacity:0;visibility:hidden;pointer-events:none;transform:translate(calc(-50% + var(--bc-dx,0px)),6px);transition:opacity .2s,transform .2s $ease,visibility 0s linear .2s;}";
                $r[] = "$s .bettercategories-sub::after{content:'';position:absolute;left:50%;top:100%;margin-left:calc(-7px - var(--bc-dx,0px));border:7px solid transparent;border-top-color:$bg;}";
                // an invisible bridge so the pointer can move from the category to the bubble
                $r[] = "$s .bettercategories-sub::before{content:'';position:absolute;left:0;right:0;top:100%;height:12px;}";
                // no room above (top of the window): the bubble opens below the category
                $r[] = "$s .bettercategories-sub.bettercategories-sub--down{bottom:auto;top:calc(100% + 10px);}";
                $r[] = "$s .bettercategories-sub--down::after{top:auto;bottom:100%;border-top-color:transparent;border-bottom-color:$bg;}";
                $r[] = "$s .bettercategories-sub--down::before{top:auto;bottom:100%;}";
                $r[] = $both('.bettercategories-sub', 'opacity:1;visibility:visible;pointer-events:auto;transform:translate(calc(-50% + var(--bc-dx,0px)),0);transition-delay:0s;');
                break;
        }

        return implode('', $r);
    }

    /**
     * box-shadow from the angle (direction the shadow falls: 0° = right, 90° = down), distance, blur,
     * spread, colour and opacity settings; $scale enlarges it (hover "lift").
     */
    private function shadow(array $p, float $scale): string
    {
        $rad = deg2rad($p['shadowAngle']);
        $x   = round(cos($rad) * $p['shadowDist'] * $scale, 1);
        $y   = round(sin($rad) * $p['shadowDist'] * $scale, 1);
        $blur = round($p['shadowBlur'] * $scale, 1);

        return "{$x}px {$y}px {$blur}px {$p['shadowSpread']}px " . $this->alpha($p['shadowColor'], min(1, $p['shadowAlpha'] * ($scale > 1 ? 1.25 : 1)));
    }

    /** Colour with opacity, from a #rgb/#rrggbb value (other formats are returned unchanged). */
    private function alpha(string $color, float $opacity): string
    {
        if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $color, $m)) {
            return $color;
        }

        $hex = strlen($m[1]) <= 4 ? preg_replace('/(.)/', '$1$1', substr($m[1], 0, 3)) : substr($m[1], 0, 6);
        [$r, $g, $b] = array_map('hexdec', str_split($hex, 2));

        return "rgba($r,$g,$b,$opacity)";
    }
}

<?php

/**
 * @package     Merserwis.Plugin
 * @subpackage  System.bettercategories
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

class PlgSystemBettercategoriesInstallerScript extends InstallerScript
{
    protected $minimumPhp    = '8.2.0';
    protected $minimumJoomla = '6.0.0';

    /**
     * Since 1.6.1 the extension ships English, German, Polish, French, Czech and Dutch: the files of
     * the other languages installed by earlier versions are removed (Joomla keeps them on an update).
     */
    private function removeDroppedLanguages(): void
    {
        foreach (['ar-AA', 'es-ES', 'hi-IN', 'lt-LT', 'sk-SK', 'uk-UA', 'zh-CN'] as $tag) {
            $files = [
                JPATH_ADMINISTRATOR . '/language/' . $tag . '/plg_system_bettercategories',
                JPATH_PLUGINS . '/system/bettercategories/language/' . $tag . '/plg_system_bettercategories',
                JPATH_SITE . '/language/' . $tag . '/mod_bettercategories',
                JPATH_SITE . '/modules/mod_bettercategories/language/' . $tag . '/mod_bettercategories',
            ];
            foreach ($files as $base) {
                foreach (['.ini', '.sys.ini'] as $ext) {
                    if (is_file($base . $ext)) {
                        @unlink($base . $ext);
                    }
                }
            }
            foreach ([JPATH_PLUGINS . '/system/bettercategories/language/' . $tag, JPATH_SITE . '/modules/mod_bettercategories/language/' . $tag] as $dir) {
                if (is_dir($dir) && !(new \FilesystemIterator($dir))->valid()) {
                    @rmdir($dir);
                }
            }
        }
    }

    /**
     * 1.7.0: the category descriptions and the rows of Gridbox's Categories element came over from
     * MerTools (its setting "Category pages", gone since MerTools 1.4.0). Once, while this plugin has
     * no description settings yet: when that setting was on, both are switched on here, with the
     * description where the category layout has it, as MerTools showed it. MerTools keeps its saved
     * settings over its update, so the order of the two updates does not matter.
     */
    private function takeOverFromMerTools(DatabaseInterface $db): void
    {
        $read = function (string $element) use ($db): ?array {
            $json = $db->setQuery(
                $db->createQuery()
                    ->select($db->quoteName('params'))
                    ->from($db->quoteName('#__extensions'))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                    ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote($element))
            )->loadResult();

            return is_string($json) ? (json_decode($json !== '' ? $json : '{}', true) ?: []) : null;
        };
        $own = $read('bettercategories');
        if ($own === null || array_key_exists('desc_enabled', $own)) {
            return;
        }
        $mertools = $read('mertools');
        $on       = $mertools !== null && (int) ($mertools['layout_categories'] ?? 0) === 1;
        // the spacing of the descriptions left as MerTools showed it (tidying is on for new sites)
        $own += ['desc_enabled' => $on ? '1' : '0', 'desc_position' => 'layout', 'desc_tidy' => $on ? '0' : '1', 'gb_categories_rows' => $on ? '1' : '0'];
        $db->setQuery(
            $db->createQuery()
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode($own, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('bettercategories'))
        )->execute();
        if ($on) {
            Factory::getApplication()->enqueueMessage(Text::_('PLG_SYSTEM_BETTERCATEGORIES_INSTALL_MERTOOLS_TAKEN'), 'message');
        }
    }

    public function postflight(string $type, InstallerAdapter $parent): void
    {
        if ($type === 'uninstall') {
            return;
        }

        $this->removeDroppedLanguages();

        // Gridbox's joined style sheets still hold the previous filters.css (named by the file list, not
        // the content): removed, Gridbox makes them again with the new files; pages kept by MerTools are
        // marked stale. Done here, not through the plugin class: the old class may be loaded in this request.
        foreach (glob(JPATH_ROOT . '/templates/gridbox/css/min/*.min.css') ?: [] as $file) {
            if (strpos(basename($file), 'index.') !== 0) {
                @unlink($file);
            }
        }
        $bases = [JPATH_SITE . '/cache', JPATH_ADMINISTRATOR . '/cache', (string) Factory::getApplication()->get('cache_path', '')];
        foreach (array_unique(array_filter($bases)) as $base) {
            if (is_dir($base . '/mertools_page')) {
                @file_put_contents($base . '/mertools_page/purged', sprintf('%.6F', microtime(true)), LOCK_EX);
            }
            try {
                Factory::getContainer()->get(\Joomla\CMS\Cache\CacheControllerFactoryInterface::class)
                    ->createCacheController('callback', ['defaultgroup' => 'page', 'cachebase' => $base])->clean('page');
            } catch (\Throwable $e) {
            }
        }

        try {
            $db   = Factory::getContainer()->get(DatabaseInterface::class);
            $lang = Factory::getApplication()->getLanguage();
            // the texts of this very version, from the package being installed (on an update the copy already installed has none of the new ones)
            $lang->load('plg_system_bettercategories', JPATH_ADMINISTRATOR) || $lang->load('plg_system_bettercategories', JPATH_PLUGINS . '/system/bettercategories');
            $lang->load('plg_system_bettercategories', $parent->getParent()->getPath('source'), null, true);

            // a fresh install enables the plugin; an update keeps whatever the administrator chose
            if ($type === 'install' || $type === 'discover_install') {
                $db->setQuery(
                    $db->createQuery()
                        ->update($db->quoteName('#__extensions'))
                        ->set($db->quoteName('enabled') . ' = 1')
                        ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                        ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                        ->where($db->quoteName('element') . ' = ' . $db->quote('bettercategories'))
                )->execute();
                $this->markFresh($db);
            }

            $this->takeOverFromMerTools($db);

            // The plugin only does something on Gridbox store pages: say so when Gridbox is missing.
            // the predecessor (Gridbox Subcategories, plg_system_gbsubcats) would add a second list
            $old = $db->createQuery()
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 0')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('gbsubcats'))
                ->where($db->quoteName('enabled') . ' = 1');
            if ($db->setQuery($old)->execute() && $db->getAffectedRows() > 0) {
                Factory::getApplication()->enqueueMessage(Text::_('PLG_SYSTEM_BETTERCATEGORIES_INSTALL_OLD_DISABLED'), 'notice');
            }

            $gridbox = $db->setQuery(
                $db->createQuery()
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__extensions'))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote('com_gridbox'))
            )->loadResult();
            if (!$gridbox) {
                Factory::getApplication()->enqueueMessage(Text::_('PLG_SYSTEM_BETTERCATEGORIES_INSTALL_NO_GRIDBOX'), 'warning');
            }

            // 1.8.0: the plugin changes the pages Gridbox sends and every store is built differently: after an install or an
            // update it is to be checked on the site itself; a fresh install is shown the way to the quick setup
            if ($type === 'install' || $type === 'update' || $type === 'discover_install') {
                // a text that did not load stays its key: then the English one is shown
                $check = Text::_('PLG_SYSTEM_BETTERCATEGORIES_INSTALL_CHECK');
                Factory::getApplication()->enqueueMessage($check !== 'PLG_SYSTEM_BETTERCATEGORIES_INSTALL_CHECK' ? $check
                    : 'Better Categories for Gridbox: check on your site, as a guest in a private window, on a computer and a phone, that the store pages look and work as they should: the store home page, a category with subcategories, a last-level category and the filters.', 'warning');
                if ($type !== 'update') {
                    $start = Text::sprintf('PLG_SYSTEM_BETTERCATEGORIES_INSTALL_START', 'index.php?option=com_bettercategories&amp;view=setup');
                    Factory::getApplication()->enqueueMessage(strpos($start, 'PLG_SYSTEM_BETTERCATEGORIES_INSTALL_START') === false ? $start
                        : 'Better Categories for Gridbox: <a href="index.php?option=com_bettercategories&amp;view=setup">start with the quick setup</a>.', 'info');
                }
            }
        } catch (\Throwable $e) {
        }
    }

    /** A fresh install: the control panel of the administrator offers the quick setup first (Joomla saves the defaults, so the settings alone do not tell). */
    private function markFresh(DatabaseInterface $db): void
    {
        try {
            $where  = ' WHERE ' . $db->quoteName('type') . ' = ' . $db->quote('plugin') . ' AND ' . $db->quoteName('folder') . ' = ' . $db->quote('system')
                . ' AND ' . $db->quoteName('element') . ' = ' . $db->quote('bettercategories');
            $params = json_decode((string) $db->setQuery('SELECT ' . $db->quoteName('params') . ' FROM ' . $db->quoteName('#__extensions') . $where)->loadResult(), true);
            $params = \is_array($params) ? $params : [];
            if (!isset($params['setup_done'])) {
                $params['setup_pending'] = 1;
                $db->setQuery('UPDATE ' . $db->quoteName('#__extensions') . ' SET ' . $db->quoteName('params') . ' = ' . $db->quote(json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . $where)->execute();
            }
        } catch (\Throwable $e) {
        }
    }
}

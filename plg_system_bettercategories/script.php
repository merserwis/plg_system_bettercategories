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
     * A fresh install enables the plugin; an update keeps whatever the administrator chose.
     */
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
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            if ($type === 'install' || $type === 'discover_install') {
                $db->setQuery(
                    $db->createQuery()
                        ->update($db->quoteName('#__extensions'))
                        ->set($db->quoteName('enabled') . ' = 1')
                        ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                        ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                        ->where($db->quoteName('element') . ' = ' . $db->quote('bettercategories'))
                )->execute();
            }

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
        } catch (\Throwable $e) {
        }
    }
}

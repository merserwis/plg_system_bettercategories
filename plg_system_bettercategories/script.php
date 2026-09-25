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
    protected $minimumJoomla = '5.0.0';

    /**
     * A fresh install enables the plugin; an update keeps whatever the administrator chose.
     */
    public function postflight(string $type, InstallerAdapter $parent): void
    {
        if ($type === 'uninstall') {
            return;
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

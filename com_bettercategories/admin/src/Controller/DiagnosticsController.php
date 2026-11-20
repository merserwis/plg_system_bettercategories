<?php

/**
 * @package     Merserwis.Component
 * @subpackage  com_bettercategories
 */

namespace Merserwis\Component\BetterCategories\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Merserwis\Component\BetterCategories\Administrator\Helper\Diagnostics;
use Merserwis\Component\BetterCategories\Administrator\Helper\Settings;

/**
 * Safe mode turned on or off from the Diagnostics page: the right to change the state of plugins is needed,
 * as for turning the plugin on or off in Joomla, and the form's token. Extends the display controller, so
 * diagnostics.display is checked as the plain display is.
 */
class DiagnosticsController extends DisplayController
{
    public function safeOn(): void
    {
        $this->switchSafeMode(true);
    }

    public function safeOff(): void
    {
        $this->switchSafeMode(false);
    }

    private function switchSafeMode(bool $on): void
    {
        $this->checkToken();
        $back = Route::_('index.php?option=com_bettercategories&view=diagnostics', false);
        $user = $this->app->getIdentity();
        if (!$user?->authorise('core.manage', 'com_plugins') || !$user->authorise('core.edit.state', 'com_plugins')) {
            $this->setRedirect($back, Text::_('JLIB_APPLICATION_ERROR_EDITSTATE_NOT_PERMITTED'), 'error');

            return;
        }
        Settings::loadLanguage();
        $error = Diagnostics::setSafeMode($on);
        if ($error === '') {
            // the lists and pages kept in the caches were made with the plugin running (or stopped): made again.
            // Safe mode is turned on when the plugin's code may be damaged: loading it must not end the request.
            try {
                if (class_exists(\Merserwis\Plugin\System\BetterCategories\Extension\BetterCategories::class)) {
                    \Merserwis\Plugin\System\BetterCategories\Extension\BetterCategories::refreshGridboxStyles();
                }
            } catch (\Throwable $e) {
            }
            $factory = Factory::getContainer()->get(CacheControllerFactoryInterface::class);
            foreach ([JPATH_SITE . '/cache', JPATH_ADMINISTRATOR . '/cache', (string) $this->app->get('cache_path', '')] as $base) {
                foreach ($base === '' ? [] : ['plg_system_bettercategories', 'page'] as $group) {
                    try {
                        $factory->createCacheController('callback', ['defaultgroup' => $group, 'cachebase' => $base])->clean($group);
                    } catch (\Throwable $e) {
                    }
                }
            }
            $this->app->enqueueMessage(Text::_('COM_BETTERCATEGORIES_CHECK_SITE'), 'warning');
        }
        $this->setRedirect($back, $error ?: Text::_($on ? 'COM_BETTERCATEGORIES_DIAG_SAFE_ON_DONE' : 'COM_BETTERCATEGORIES_DIAG_SAFE_OFF_DONE'), $error ? 'error' : 'message');
    }
}

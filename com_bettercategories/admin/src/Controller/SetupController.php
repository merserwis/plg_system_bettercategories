<?php

/**
 * @package     Merserwis.Component
 * @subpackage  com_bettercategories
 */

namespace Merserwis\Component\BetterCategories\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Merserwis\Component\BetterCategories\Administrator\Helper\Catalog;
use Merserwis\Component\BetterCategories\Administrator\Helper\Settings;

/**
 * Saving the quick setup: only the switches that were changed are written (Catalog::setupChanges),
 * and the setup is marked as done, so the control panel no longer offers it first. Extends the
 * display controller, so setup.display is checked as the plain display is.
 */
class SetupController extends DisplayController
{
    public function save(): void
    {
        $this->checkToken();
        $user = $this->app->getIdentity();
        if (!$user?->authorise('core.manage', 'com_plugins') || !$user->authorise('core.edit', 'com_plugins')) {
            $this->setRedirect(Route::_('index.php?option=com_bettercategories&view=setup', false), Text::_('JLIB_APPLICATION_ERROR_SAVE_NOT_PERMITTED'), 'error');

            return;
        }
        Settings::loadLanguage();
        $posted  = (array) $this->input->post->get('jform', [], 'array');
        $initial = (array) $this->input->post->get('bcc_initial', [], 'array');
        $changes = Catalog::setupChanges($posted, $initial);
        $errors  = Settings::store($changes + ['setup_done' => 1, 'setup_pending' => 0]);
        if ($errors) {
            foreach ($errors as $error) {
                $this->app->enqueueMessage($error, 'warning');
            }
            $this->setRedirect(Route::_('index.php?option=com_bettercategories&view=setup', false));

            return;
        }
        $this->app->enqueueMessage(Text::plural('COM_BETTERCATEGORIES_SETUP_SAVED', \count($changes)));
        $this->app->enqueueMessage(Text::_('COM_BETTERCATEGORIES_CHECK_SITE'), 'warning');
        $this->setRedirect(Route::_('index.php?option=com_bettercategories', false));
    }

    public function cancel(): void
    {
        $this->checkToken();
        $this->setRedirect(Route::_('index.php?option=com_bettercategories', false));
    }
}

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
 * Saving a page of settings (page.apply stays on the page, page.save goes back to the control panel),
 * closing it, and turning the plugin on. The right to edit plugins is needed, as in Joomla's plugin
 * manager, and the form's token. Extends the display controller, so page.display is checked as the
 * plain display is.
 */
class PageController extends DisplayController
{
    public function __construct($config = [], $factory = null, $app = null, $input = null)
    {
        parent::__construct($config, $factory, $app, $input);
        $this->registerTask('apply', 'save');
    }

    public function save(): void
    {
        $this->checkToken();
        $page = $this->input->getCmd('page', '');
        if (!Catalog::page($page)) {
            $this->setRedirect(Route::_('index.php?option=com_bettercategories', false), Text::_('COM_BETTERCATEGORIES_ERROR_PAGE'), 'error');

            return;
        }
        $back = 'index.php?option=com_bettercategories&view=page&page=' . $page;
        $user = $this->app->getIdentity();
        if (!$user?->authorise('core.manage', 'com_plugins') || !$user->authorise('core.edit', 'com_plugins')) {
            $this->setRedirect(Route::_($back, false), Text::_('JLIB_APPLICATION_ERROR_SAVE_NOT_PERMITTED'), 'error');

            return;
        }
        Settings::loadLanguage();
        // the pages are made of the plugin's fields: with the plugin off a save would write defaults over the settings
        if (!Settings::pluginReady()) {
            $this->setRedirect(Route::_($back, false), Text::_('COM_BETTERCATEGORIES_PLUGIN_OFF'), 'error');

            return;
        }
        $posted = $this->input->post->get('jform', [], 'array');
        $params = \is_array($posted['params'] ?? null) ? $posted['params'] : [];
        $errors = Settings::savePage($page, $params);
        if ($errors) {
            // the values typed stay in the form; a setting of the page not sent (a list of rows emptied) stays empty, not the saved one
            $form = Settings::form($page);
            foreach ($form ? Settings::fieldNames($form) : [] as $name) {
                $params += [$name => []];
            }
            $this->app->setUserState('com_bettercategories.page.' . $page, $params);
            foreach ($errors as $error) {
                $this->app->enqueueMessage($error, 'warning');
            }
            $this->setRedirect(Route::_($back, false));

            return;
        }
        $this->app->setUserState('com_bettercategories.page.' . $page, null);
        $this->app->enqueueMessage(Text::_('COM_BETTERCATEGORIES_SAVED'));
        $this->app->enqueueMessage(Text::_('COM_BETTERCATEGORIES_CHECK_SITE'), 'warning');
        $this->setRedirect(Route::_($this->getTask() === 'apply' ? $back : 'index.php?option=com_bettercategories', false));
    }

    public function cancel(): void
    {
        $this->checkToken();
        $this->app->setUserState('com_bettercategories.page.' . $this->input->getCmd('page', ''), null);
        $this->setRedirect(Route::_('index.php?option=com_bettercategories', false));
    }

    /** the plugin was off: on (the control panel offers it) */
    public function enable(): void
    {
        $this->checkToken();
        $user = $this->app->getIdentity();
        if (!$user?->authorise('core.manage', 'com_plugins') || !$user->authorise('core.edit.state', 'com_plugins')) {
            $this->setRedirect(Route::_('index.php?option=com_bettercategories', false), Text::_('JLIB_APPLICATION_ERROR_EDITSTATE_NOT_PERMITTED'), 'error');

            return;
        }
        $errors = Settings::enable();
        $this->setRedirect(Route::_('index.php?option=com_bettercategories', false), $errors ? implode(' ', $errors) : Text::_('COM_BETTERCATEGORIES_ENABLED'), $errors ? 'error' : 'message');
    }
}

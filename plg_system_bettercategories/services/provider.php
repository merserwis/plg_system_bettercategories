<?php

/**
 * @package     Merserwis.Plugin
 * @subpackage  System.bettercategories
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Merserwis\Plugin\System\BetterCategories\Extension\BetterCategories;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            $container->lazy(BetterCategories::class, function (Container $container) {
                $plugin = new BetterCategories((array) PluginHelper::getPlugin('system', 'bettercategories'));
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            })
        );
    }
};

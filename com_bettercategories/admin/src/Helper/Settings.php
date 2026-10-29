<?php

/**
 * @package     Merserwis.Component
 * @subpackage  com_bettercategories
 *
 * The settings of Better Categories are, as they always were, the parameters of its system plugin
 * (#__extensions): the plugin reads them and nothing about that changes. This class reads them, builds
 * the form of a page from the fieldsets of the plugin's manifest, and saves through Joomla's own plugin
 * model, so everything that follows a save of the plugin in Joomla follows here too (the plugin's
 * onExtensionAfterSave empties its cache and the kept pages, Joomla cleans its cache of the plugins, the
 * action log notes who saved).
 */

namespace Merserwis\Component\BetterCategories\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

final class Settings
{
    /** the row of the plugin (false: not read yet) */
    private static object|false|null $plugin = false;

    private static ?\SimpleXMLElement $manifest = null;

    private static ?array $defaults = null;

    /** the plugin's row: extension_id, enabled, params; null when it is not installed */
    public static function plugin(): ?object
    {
        if (self::$plugin === false) {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->createQuery()
                ->select($db->quoteName(['extension_id', 'enabled', 'params']))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('bettercategories'));
            self::$plugin = $db->setQuery($query)->loadObject() ?: null;
        }

        return self::$plugin;
    }

    public static function params(): Registry
    {
        return new Registry((string) (self::plugin()->params ?? ''));
    }

    /** the texts of the settings are the plugin's: loaded where a page of Better Categories shows them */
    public static function loadLanguage(): void
    {
        $lang = Factory::getApplication()->getLanguage();
        $lang->load('plg_system_bettercategories', JPATH_ADMINISTRATOR)
            || $lang->load('plg_system_bettercategories', JPATH_PLUGINS . '/system/bettercategories');
    }

    /** the plugin's manifest, whose <config> holds every setting */
    public static function manifest(): ?\SimpleXMLElement
    {
        if (self::$manifest === null) {
            $file = JPATH_PLUGINS . '/system/bettercategories/bettercategories.xml';
            $xml  = is_file($file) ? simplexml_load_file($file) : false;
            if ($xml === false || !isset($xml->config->fields)) {
                return null;
            }
            self::$manifest = $xml;
        }

        return self::$manifest;
    }

    /**
     * The tabs of a page: fieldset name => its label (a language key), in the order of the manifest.
     *
     * @return array<string, string>
     */
    public static function fieldsets(string $page): array
    {
        return array_map(fn (array $set) => $set['label'], self::fieldsetInfo($page));
    }

    /**
     * The tabs of a page with their description (language keys), as the plugin's own form shows them.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public static function fieldsetInfo(string $page): array
    {
        $out = [];
        foreach (self::manifest()?->config->fields->fieldset ?? [] as $fieldset) {
            if ((string) $fieldset['page'] === $page) {
                $out[(string) $fieldset['name']] = ['label' => (string) $fieldset['label'], 'description' => (string) $fieldset['description']];
            }
        }

        return $out;
    }

    /**
     * The default value of every setting, from the manifest (the plugin uses the same ones when a
     * setting was never saved).
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        if (self::$defaults === null) {
            self::$defaults = [];
            foreach (self::manifest()?->config->fields->fieldset ?? [] as $fieldset) {
                foreach ($fieldset->field as $field) {
                    if (isset($field['default'])) {
                        self::$defaults[(string) $field['name']] = (string) $field['default'];
                    }
                }
            }
        }

        return self::$defaults;
    }

    /**
     * The form of a page (its fieldsets of the manifest under jform[params]), or null for a page without
     * settings. Without the "return to Gridbox" field of the plugin's own form. The settings of the other
     * pages are not in it: the live preview and the tools of the plugin take them from the saved ones
     * (the page's form says so with data-bc-partial).
     */
    public static function form(string $page): ?Form
    {
        $fields = self::manifest()?->config->fields;
        if (!$fields) {
            return null;
        }
        $copies = [];
        $local  = [];
        foreach ($fields->fieldset as $fieldset) {
            if ((string) $fieldset['page'] !== $page) {
                continue;
            }
            $copy = new \SimpleXMLElement($fieldset->asXML());
            foreach ($copy->xpath('field[@type="gbreturn"]') ?: [] as $field) {
                unset($field[0]);
            }
            foreach ($copy->field as $field) {
                $local[(string) $field['name']] = true;
            }
            $copies[] = $copy;
        }
        if (!$copies) {
            return null;
        }
        $xml = '';
        foreach ($copies as $copy) {
            self::resolveShowon($copy, $local);
            // asXML() of a whole document starts with the XML declaration, which may not stand inside another document
            $xml .= preg_replace('/^<\?xml[^>]*\?>\s*/', '', (string) $copy->asXML());
        }
        $form = Factory::getContainer()->get(FormFactoryInterface::class)->createForm('com_bettercategories.page.' . $page, ['control' => 'jform']);
        $form->load('<form><fields name="params" addfieldprefix="' . htmlspecialchars((string) $fields['addfieldprefix'], ENT_QUOTES | ENT_XML1, 'UTF-8') . '">'
            . $xml . '</fields></form>');

        return $form;
    }

    /**
     * A field shown on another page of the settings than the fields it depends on (showon="desc_enabled:1" where
     * "desc_enabled" is on another page): the form has no such field to look at, so the condition is answered with
     * the saved value now. True: the condition is dropped. False: the field is not in the form (it cannot be shown,
     * and its saved value stays). Only conditions joined with [AND] are answered; the others are left as they are.
     *
     * @param array<string, true> $local names of the fields of the page
     */
    private static function resolveShowon(\SimpleXMLElement $fieldset, array $local): void
    {
        $params   = self::params();
        $defaults = self::defaults();
        $remove   = [];
        foreach ($fieldset->field as $field) {
            $showon = (string) $field['showon'];
            if ($showon === '' || str_contains($showon, '[OR]')) {
                continue;
            }
            $keep = [];
            $show = true;
            foreach (explode('[AND]', $showon) as $condition) {
                if (!preg_match('/^([A-Za-z0-9_]+)(!?):(.*)$/', $condition, $m) || isset($local[$m[1]])) {
                    $keep[] = $condition;
                    continue;
                }
                $actual = $params->get($m[1], $defaults[$m[1]] ?? '');
                $actual = \is_array($actual) ? array_map('strval', $actual) : [(string) $actual];
                $found  = (bool) array_intersect($actual, explode(',', $m[3]));
                $show   = $show && ($m[2] === '!' ? !$found : $found);
            }
            if (!$show) {
                $remove[] = $field;
            } elseif ($keep) {
                $field['showon'] = implode('[AND]', $keep);
            } else {
                unset($field['showon']);
            }
        }
        foreach ($remove as $field) {
            unset($field[0]);
        }
    }

    /**
     * The names of the settings of a form: the fields of its tabs, not those inside a list of rows. Form::getGroup() also
     * returns the fields of a subform, and the rows of "Per level" have fields named as settings of other pages
     * (tile_style, sub_mode): taken for settings of this page, a save would set those back to their default.
     */
    public static function fieldNames(Form $form): array
    {
        $out = [];
        foreach ($form->getXml()->xpath('//fields[@name="params"]/fieldset/field[@name]') ?: [] as $field) {
            $out[] = (string) $field['name'];
        }

        return array_values(array_unique($out));
    }

    /**
     * Saves the settings of a page: the values sent are filtered and checked by the page's form; a
     * setting of the page that was not sent (a list with nothing chosen, a list of rows emptied) goes
     * back to its default, as it did when the plugin's own form replaced all its settings; the settings
     * of other pages are kept as they are.
     *
     * @param array<string, mixed> $posted jform[params]
     *
     * @return list<string> the errors (none: saved)
     */
    public static function savePage(string $page, array $posted): array
    {
        $form = self::form($page);
        if (!$form) {
            return [Text::_('COM_BETTERCATEGORIES_ERROR_PAGE')];
        }
        $data  = $form->filter(['params' => $posted]);
        $valid = $form->validate($data);
        if ($valid !== true) {
            $errors = [];
            foreach ($form->getErrors() as $error) {
                $errors[] = $error instanceof \Throwable ? $error->getMessage() : (string) $error;
            }

            return $errors ?: [Text::_('COM_BETTERCATEGORIES_ERROR_SAVE')];
        }
        $names  = self::fieldNames($form);
        // only the settings of this page: a name sent that the page does not have is not written
        $values = array_intersect_key((array) ($data['params'] ?? []), array_flip($names));

        return self::store($values, array_diff($names, array_keys($values)));
    }

    /**
     * Writes settings into the plugin's parameters through Joomla's plugin model.
     *
     * @param array<string, mixed> $set   setting => value
     * @param list<string>         $unset settings that go back to their default
     *
     * @return list<string> the errors (none: saved)
     */
    public static function store(array $set, array $unset = []): array
    {
        // read again just before writing: another administrator may have saved another page meanwhile
        self::$plugin = false;
        $plugin       = self::plugin();
        if (!$plugin) {
            return [Text::_('COM_BETTERCATEGORIES_ERROR_PLUGIN')];
        }
        $params = json_decode((string) $plugin->params, true);
        $params = \is_array($params) ? $params : [];
        foreach ($unset as $key) {
            unset($params[$key]);
        }
        $params = array_replace($params, $set);

        try {
            $model = Factory::getApplication()->bootComponent('com_plugins')->getMVCFactory()
                ->createModel('Plugin', 'Administrator', ['ignore_request' => true]);
            if (!$model || !$model->save(['extension_id' => (int) $plugin->extension_id, 'params' => $params])) {
                return [($model ? (string) $model->getError() : '') ?: Text::_('COM_BETTERCATEGORIES_ERROR_SAVE')];
            }
        } catch (\Throwable $e) {
            return [$e->getMessage()];
        }
        self::$plugin = false;

        return [];
    }

    /** turns the plugin on (Joomla's plugin model, so the change of state is seen by other extensions) */
    public static function enable(): array
    {
        $plugin = self::plugin();
        if (!$plugin) {
            return [Text::_('COM_BETTERCATEGORIES_ERROR_PLUGIN')];
        }
        try {
            $model = Factory::getApplication()->bootComponent('com_plugins')->getMVCFactory()
                ->createModel('Plugin', 'Administrator', ['ignore_request' => true]);
            $pks   = [(int) $plugin->extension_id];
            if (!$model || !$model->publish($pks, 1)) {
                return [($model ? (string) $model->getError() : '') ?: Text::_('COM_BETTERCATEGORIES_ERROR_SAVE')];
            }
        } catch (\Throwable $e) {
            return [$e->getMessage()];
        }
        self::$plugin = false;

        return [];
    }

    /** the plugin is installed and on: its field classes (the pages are made of them) are then loaded */
    public static function pluginReady(): bool
    {
        return (int) (self::plugin()->enabled ?? 0) === 1 && class_exists(\Merserwis\Plugin\System\BetterCategories\Field\BcpreviewField::class);
    }

    /**
     * The figures of the store for the control panel: the apps of products and their published categories.
     * Null without Gridbox (no such tables).
     *
     * @return array{apps: int, categories: int}|null
     */
    public static function storeFigures(): ?array
    {
        try {
            $db   = Factory::getContainer()->get(DatabaseInterface::class);
            $apps = $db->setQuery(
                $db->createQuery()
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__gridbox_app'))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('products'))
            )->loadColumn();
            $apps = array_map('intval', $apps ?: []);
            $cats = 0;
            if ($apps) {
                $cats = (int) $db->setQuery(
                    $db->createQuery()
                        ->select('COUNT(*)')
                        ->from($db->quoteName('#__gridbox_categories'))
                        ->whereIn($db->quoteName('app_id'), $apps)
                        ->where($db->quoteName('published') . ' = 1')
                )->loadResult();
            }

            return ['apps' => \count($apps), 'categories' => $cats];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Gridbox is installed and on */
    public static function hasGridbox(): bool
    {
        return ComponentHelper::isEnabled('com_gridbox');
    }

    /**
     * A fresh installation: the installer's script marks it (setup_pending) and the quick setup has not
     * been saved yet, so the control panel offers the quick setup first. A site updated from an older
     * version has its settings and is not asked (Joomla saves the defaults of a new install, so the
     * number of settings says nothing).
     */
    public static function isNew(Registry $params): bool
    {
        return (int) $params->get('setup_pending', 0) === 1 && !(int) $params->get('setup_done', 0);
    }
}

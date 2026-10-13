<?php

/**
 * @package     Merserwis.Plugin
 * @subpackage  System.bettercategories
 *
 * Icons of categories and filter groups: a Font Awesome Free icon ("fa-solid fa-fire") put into the
 * page as inline SVG — no font or style sheet to load, no clash with the icon fonts of the template
 * (Gridbox ships Font Awesome 5, Joomla 6) and only the icons used reach the visitor —, a file of the
 * media manager (SVG, PNG…) as an image, or any other classes of an icon font the site loads
 * ("zmdi zmdi-fire", "flaticon-fire") as <i>.
 */

namespace Merserwis\Plugin\System\BetterCategories\Icon;

\defined('_JEXEC') or die;

final class Icons
{
    /** the Font Awesome sprites shipped with the plugin (media/plg_system_bettercategories/icons/fa) */
    public const STYLES = ['solid' => 'solid', 'fa-solid' => 'solid', 'fas' => 'solid', 'regular' => 'regular', 'fa-regular' => 'regular',
        'far' => 'regular', 'brands' => 'brands', 'fa-brands' => 'brands', 'fab' => 'brands'];

    /** Font Awesome classes that are not an icon name (sizes, animations…) */
    private const MODIFIERS = '/^fa-(?:\d*x|xs|sm|lg|xl|2xs|2xl|fw|spin|pulse|beat|fade|bounce|flip|shake|border|inverse|li|ul|stack(?:-\dx)?|rotate-\d+|flip-(?:horizontal|vertical|both)|pull-(?:left|right|start|end))$/';

    /** @var array<string, string|null> style/name => <svg …> (null: no such icon) */
    private static array $svg = [];

    /** @var array<string, string> style => sprite file contents */
    private static array $sprites = [];

    /** @param string $dir folder of the sprites (…/media/plg_system_bettercategories/icons/fa) */
    public function __construct(private string $dir)
    {
    }

    /**
     * The HTML of an icon: the file when there is one, else the icon of the classes; '' when neither.
     *
     * @param callable $url  image path of the media manager => address
     */
    public function html(string $icon, string $file, string $class, callable $url): string
    {
        $file = trim($file);
        if ($file !== '') {
            $src = (string) $url($file);
            if ($src !== '') {
                return '<img class="' . $class . ' bc-icon--img" src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="" aria-hidden="true" loading="lazy" decoding="async">';
            }
        }
        $icon = trim($icon);
        if ($icon === '') {
            return '';
        }
        [$style, $name] = self::parse($icon);
        if ($style !== null && $name !== null) {
            $svg = $this->svg($style, $name);
            if ($svg !== null) {
                return str_replace('<svg ', '<svg class="' . $class . ' bc-icon--svg" ', $svg);
            }
        }
        $classes = trim(preg_replace('/[^A-Za-z0-9_\- ]+/', '', $icon) ?? '');

        return $classes === '' ? '' : '<i class="' . $class . ' bc-icon--font ' . $classes . '" aria-hidden="true"></i>';
    }

    /**
     * Style and name of a Font Awesome class list ("fa-solid fa-fire", "fas fa-fire", "fa-fire" =
     * solid), or [null, null] for other classes.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function parse(string $icon): array
    {
        $style = null;
        $name  = null;
        foreach (preg_split('/\s+/', strtolower(trim($icon))) ?: [] as $token) {
            if (isset(self::STYLES[$token]) && str_starts_with($token, 'fa')) {
                $style = self::STYLES[$token];
            } elseif ($token === 'fa' || preg_match(self::MODIFIERS, $token)) {
                // sizes and the like: the size comes from the settings
                continue;
            } elseif (str_starts_with($token, 'fa-')) {
                $name ??= substr($token, 3);
            } else {
                // classes of another icon font
                return [null, null];
            }
        }
        if ($name === null || !preg_match('/^[a-z0-9-]+$/', $name)) {
            return [null, null];
        }

        return [$style ?? 'solid', $name];
    }

    /** The inline SVG of a Font Awesome icon, or null when the style has no such icon. */
    public function svg(string $style, string $name): ?string
    {
        $key = $style . '/' . $name;
        if (array_key_exists($key, self::$svg)) {
            return self::$svg[$key];
        }
        if (!isset(self::$sprites[$style])) {
            self::$sprites[$style] = (string) @file_get_contents($this->dir . '/' . $style . '.svg');
        }
        $out = null;
        if (preg_match('#<symbol id="' . preg_quote($name, '#') . '" viewBox="([0-9 .\-]+)">(.*?)</symbol>#s', self::$sprites[$style], $m)) {
            $out = '<svg viewBox="' . $m[1] . '" fill="currentColor" aria-hidden="true" focusable="false">' . trim($m[2]) . '</svg>';
        }

        return self::$svg[$key] = $out;
    }
}

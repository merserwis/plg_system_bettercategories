<?php

/**
 * @package     Merserwis.Plugin
 * @subpackage  System.bettercategories
 *
 * The store menu (1.11.0, mod_bettercategories as "Store menu"): the category tree of a store in a header,
 * three levels deep — the top categories, their subcategories as headings with an image, the count and the
 * price "from", and the categories under those as links.
 *
 *  - button: one button ("Categories") opens a panel, the top categories on the left and the subcategories
 *    of the one pointed at on the right;
 *  - bar: the top categories in a row, each opens its subcategories below the whole bar;
 *  - panel: the panel always open, for the drop-down of a menu item of Gridbox's own menu (its "Mega Menu").
 *
 * Below 1024 px every kind is a drawer from the side: the top categories, and the subcategories of one of them
 * after its arrow. The same HTML for every device (a page cache serves it to all): the widths are CSS, the
 * behaviour media/plg_system_bettercategories/js/menu.js; without the script the panel opens on hover and
 * keyboard focus, and every link works.
 *
 * Built from the data the plugin prepares (BetterCategories::menuNodes()): this class only writes HTML.
 */

namespace Merserwis\Plugin\System\BetterCategories\Menu;

\defined('_JEXEC') or die;

final class MegaMenu
{
    public const STYLES = ['button', 'bar', 'panel'];

    /**
     * @param  array  $o      style, label, depth (2|3), images (none|groups|all), counts, columns (2–6), leaves (0 = all),
     *                        trigger (hover|click), accent (CSS color or '')
     * @param  array  $texts  label, all (sprintf %s = category), more, back, close, open (sprintf %s = category)
     */
    public function __construct(private array $o, private array $texts)
    {
    }

    /**
     * @param  list<array>  $nodes  title, url, count, price, img (src, srcset), icon (HTML), children (the same)
     */
    public function render(string $id, array $nodes): string
    {
        if (!$nodes) {
            return '';
        }
        $e      = fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $style  = in_array($this->o['style'] ?? '', self::STYLES, true) ? $this->o['style'] : 'button';
        $label  = (string) $this->texts['label'];
        $panel  = $id . '-panel';
        $images = (string) ($this->o['images'] ?? 'groups');
        $vars   = '--bcm-cols:' . max(2, min(6, (int) ($this->o['columns'] ?? 4)));
        if (($this->o['accent'] ?? '') !== '') {
            $vars .= ';--bcm-accent:' . $this->o['accent'];
        }

        $top = '';
        foreach ($nodes as $i => $node) {
            $subId = $id . '-s' . $i;
            $kids  = $node['children'] ?? [];
            $top  .= '<li class="bcm-top-item' . ($kids ? ' bcm-has-sub' : '') . '">'
                . '<a class="bcm-top-link" href="' . $e($node['url']) . '">' . ($images === 'all' ? $this->media($node, 'bcm-img bcm-img--top') : '')
                . '<span class="bcm-name">' . $e($node['title']) . '</span>' . $this->count($node) . '</a>';
            if ($kids) {
                $top .= '<button type="button" class="bcm-open" aria-expanded="false" aria-controls="' . $subId . '" aria-label="'
                    . $e(sprintf($this->texts['open'], $node['title'])) . '"><span aria-hidden="true"></span></button>'
                    . '<div class="bcm-sub" id="' . $subId . '">' . $this->sub($node, $images) . '</div>';
            }
            $top .= '</li>';
        }

        $head = '<div class="bcm-head"><button type="button" class="bcm-back" hidden>' . $e($this->texts['back']) . '</button>'
            . '<span class="bcm-head-title">' . $e($label) . '</span>'
            . ($style !== 'panel' ? '<button type="button" class="bcm-close" aria-label="' . $e($this->texts['close']) . '"><span aria-hidden="true">×</span></button>' : '')
            . '</div>';

        return '<style id="bettercategories-menu-css">' . self::css() . '</style>'
            . '<nav id="' . $e($id) . '" class="bcm bcm--' . $style . ($images !== 'none' ? ' bcm--img' : '') . ' bcm--' . (($this->o['trigger'] ?? 'hover') === 'click' ? 'click' : 'hover') . '" aria-label="' . $e($label)
            . '" style="' . $e($vars) . '">'
            . '<button type="button" class="bcm-toggle" aria-expanded="false" aria-controls="' . $panel . '"><span class="bcm-burger" aria-hidden="true"></span>'
            . '<span class="bcm-toggle-label">' . $e($label) . '</span></button>'
            . '<div class="bcm-panel" id="' . $panel . '">' . $head . '<ul class="bcm-top">' . $top . '</ul></div>'
            . '</nav>';
    }

    /** The subcategories of a top category: a link to all of it, then a column for each subcategory. */
    private function sub(array $node, string $images): string
    {
        $e      = fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $leaves = max(0, (int) ($this->o['leaves'] ?? 6));
        $html   = '<div class="bcm-sub-head"><a class="bcm-all" href="' . $e($node['url']) . '">' . $e(sprintf($this->texts['all'], $node['title'])) . '</a>'
            . ($node['price'] !== '' ? '<span class="bcm-price">' . $e($node['price']) . '</span>' : '') . '</div><ul class="bcm-cols">';
        foreach ($node['children'] as $group) {
            $html .= '<li class="bcm-group"><a class="bcm-group-link" href="' . $e($group['url']) . '">' . ($images !== 'none' ? $this->media($group, 'bcm-img') : '')
                . '<span class="bcm-group-text"><span class="bcm-name">' . $e($group['title']) . '</span>' . $this->count($group)
                . ($group['price'] !== '' ? '<span class="bcm-price">' . $e($group['price']) . '</span>' : '') . '</span></a>';
            $kids = (int) ($this->o['depth'] ?? 3) >= 3 ? ($group['children'] ?? []) : [];
            if ($kids) {
                $html .= '<ul class="bcm-leaves">';
                foreach ($leaves > 0 ? array_slice($kids, 0, $leaves) : $kids as $leaf) {
                    $html .= '<li><a href="' . $e($leaf['url']) . '">' . $e($leaf['title']) . $this->count($leaf) . '</a></li>';
                }
                if ($leaves > 0 && count($kids) > $leaves) {
                    $html .= '<li><a class="bcm-more" href="' . $e($group['url']) . '">' . $e($this->texts['more']) . '</a></li>';
                }
                $html .= '</ul>';
            }
            $html .= '</li>';
        }

        return $html . '</ul>';
    }

    private function count(array $node): string
    {
        return !empty($this->o['counts']) ? ' <span class="bcm-count">' . (int) $node['count'] . '</span>' : '';
    }

    /** The image of a category (a small WebP copy when there is one), its icon, or an empty square. */
    private function media(array $node, string $class): string
    {
        $e = fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        if (!empty($node['img']['src'])) {
            return '<img class="' . $class . '" src="' . $e($node['img']['src']) . '"'
                . (!empty($node['img']['srcset']) ? ' srcset="' . $e($node['img']['srcset']) . '" sizes="48px"' : '')
                . ' alt="" loading="lazy" decoding="async" width="48" height="48">';
        }
        if (($node['icon'] ?? '') !== '') {
            return '<span class="' . $class . ' bcm-img--icon" aria-hidden="true">' . $node['icon'] . '</span>';
        }

        return '<span class="' . $class . ' bcm-img--empty" aria-hidden="true"></span>';
    }

    /** The styles of every store menu (one block per menu; the same text, so a second one costs little). */
    public static function css(): string
    {
        return <<<'CSS'
.bcm{--bcm-accent:#0b6bcb;--bcm-bg:#fff;--bcm-color:#1f2328;--bcm-muted:#6b7280;--bcm-line:#e5e7eb;--bcm-cols:4;position:relative;font-size:15px;line-height:1.35;color:var(--bcm-color)}
.bcm *{box-sizing:border-box}.bcm ul{list-style:none;margin:0;padding:0}.bcm li{margin:0;padding:0}
.bcm a{color:inherit;text-decoration:none}.bcm a:hover,.bcm a:focus-visible{color:var(--bcm-accent)}
.bcm button{font:inherit;cursor:pointer}
.bcm ul,.bcm a,.bcm button,.bcm span,.bcm li{font-family:inherit;font-size:inherit;line-height:inherit;letter-spacing:normal;text-transform:none}
.bcm-toggle{display:inline-flex;align-items:center;gap:.6em;padding:.65em 1.1em;border:0;border-radius:6px;background:var(--bcm-accent);color:#fff;font-weight:600}
.bcm-burger{display:inline-block;width:1.05em;height:.8em;background:linear-gradient(currentColor,currentColor) top/100% 2px no-repeat,linear-gradient(currentColor,currentColor) center/100% 2px no-repeat,linear-gradient(currentColor,currentColor) bottom/100% 2px no-repeat}
.bcm-panel{background:var(--bcm-bg);color:var(--bcm-color)}
.bcm-head{display:none}
.bcm-count{color:var(--bcm-muted);font-size:.82em;font-weight:400;margin-left:.35em}
.bcm-count::before{content:"("}.bcm-count::after{content:")"}
.bcm-price{display:block;color:var(--bcm-accent);font-size:.82em;font-weight:600}
.bcm-img{display:inline-flex;align-items:center;justify-content:center;flex:none;width:48px;height:48px;object-fit:contain;border-radius:6px;background:#f6f7f9}
.bcm-img--top{width:28px;height:28px}
.bcm-img--icon svg,.bcm-img--icon img{width:60%;height:60%}
.bcm-open{display:inline-flex;align-items:center;justify-content:center;flex:none;width:40px;align-self:stretch;border:0;background:none;color:inherit}
.bcm-open span{width:.5em;height:.5em;border:solid currentColor;border-width:2px 2px 0 0;transform:rotate(45deg)}
.bcm-sub-head{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:baseline;gap:6px 16px;margin:0 0 14px;padding:0 0 10px;border-bottom:1px solid var(--bcm-line)}
.bcm-all{font-weight:700;color:var(--bcm-accent)!important}
.bcm-group-link{display:flex;align-items:center;gap:10px;font-weight:600}
.bcm-group-text{min-width:0}
.bcm-leaves{margin:6px 0 0!important}
.bcm--img .bcm-leaves{padding-left:58px!important}
.bcm-leaves a{display:block;padding:3px 0;color:var(--bcm-muted)}
.bcm-more{font-style:italic}
@media (min-width:1024px){
.bcm--button,.bcm--panel{display:inline-block}
.bcm--panel{display:block}
.bcm--panel .bcm-toggle{display:none}
.bcm--button .bcm-panel,.bcm--panel .bcm-panel{position:relative;display:flex;min-height:380px}
.bcm--button .bcm-panel{position:absolute;top:100%;left:0;z-index:1000;margin-top:6px;width:min(1120px,calc(100vw - 32px));border-radius:8px;box-shadow:0 14px 36px rgba(0,0,0,.18)}
.bcm--button:not(.is-open) .bcm-panel{display:none}
html:not(.bcm-js) .bcm--button.bcm--hover:hover .bcm-panel,html:not(.bcm-js) .bcm--button:focus-within .bcm-panel{display:flex}
.bcm--button .bcm-top,.bcm--panel .bcm-top{flex:none;width:270px;max-height:70vh;overflow:auto;padding:8px 0;border-right:1px solid var(--bcm-line)}
.bcm-top-item{display:flex}
.bcm--button .bcm-top-link,.bcm--panel .bcm-top-link{flex:1;display:flex;align-items:center;gap:10px;padding:9px 6px 9px 18px}
.bcm-top-item.is-active>.bcm-top-link,.bcm-top-item.is-active>.bcm-open{background:#f3f4f6;color:var(--bcm-accent)}
.bcm-sub{display:none;position:absolute;top:0;right:0;bottom:0;left:270px;padding:20px 26px;overflow:auto}
.bcm-top-item.is-active>.bcm-sub,html:not(.bcm-js) .bcm-top-item:hover>.bcm-sub{display:block}
.bcm-cols{display:grid;grid-template-columns:repeat(var(--bcm-cols),minmax(0,1fr));gap:20px 26px}
.bcm--bar .bcm-toggle{display:none}
.bcm--bar .bcm-panel{background:transparent}
.bcm--bar .bcm-top{display:flex;flex-wrap:wrap;gap:0 4px}
.bcm--bar .bcm-top-item{position:static}
.bcm--bar .bcm-top-link{display:flex;align-items:center;gap:8px;padding:10px 4px 10px 12px;font-weight:600}
.bcm--bar .bcm-open{width:28px}.bcm--bar .bcm-open span{transform:rotate(135deg);margin-top:-.25em}
.bcm--bar .bcm-top-item.is-active>.bcm-top-link,.bcm--bar .bcm-top-item.is-active>.bcm-open{background:transparent}
.bcm--bar .bcm-sub{top:100%;bottom:auto;left:0;z-index:1000;max-height:72vh;background:var(--bcm-bg);border-radius:0 0 8px 8px;box-shadow:0 14px 36px rgba(0,0,0,.18)}
}
@media (max-width:1023.98px){
.bcm--button .bcm-panel,.bcm--bar .bcm-panel{position:fixed;top:0;bottom:0;left:0;z-index:10001;width:min(400px,88vw);overflow:auto;visibility:hidden;transform:translateX(-102%);transition:transform .25s ease,visibility 0s .25s;box-shadow:0 0 36px rgba(0,0,0,.25)}
.bcm.is-open .bcm-panel{visibility:visible;transform:none;transition:transform .25s ease}
.bcm--panel .bcm-toggle{display:none}
.bcm-backdrop{position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.45)}
.bcm-head{display:flex;align-items:center;gap:8px;min-height:52px;padding:6px 8px 6px 16px;border-bottom:1px solid var(--bcm-line);position:sticky;top:0;z-index:1;background:var(--bcm-bg)}
.bcm-head-title{flex:1;font-weight:700}
.bcm-back{border:0;background:none;color:var(--bcm-accent);font-weight:600;padding:8px 6px}
.bcm-back::before{content:"‹ "}
.bcm-close{width:40px;height:40px;border:0;background:none;font-size:26px;line-height:1;color:inherit}
.bcm-top-item{display:flex;flex-wrap:wrap;border-bottom:1px solid var(--bcm-line)}
.bcm-top-link{flex:1;display:flex;align-items:center;gap:10px;padding:13px 8px 13px 16px}
.bcm-open{width:52px}
.bcm-sub{display:none;width:100%}
.bcm.is-drilled .bcm-top-item:not(.is-active),.bcm.is-drilled .bcm-top-item.is-active>.bcm-top-link,.bcm.is-drilled .bcm-top-item.is-active>.bcm-open{display:none}
.bcm.is-drilled .bcm-top-item.is-active{border:0}
.bcm.is-drilled .bcm-top-item.is-active>.bcm-sub{display:block;padding:12px 16px 20px}
.bcm-cols{display:block}
.bcm-group{padding:10px 0;border-bottom:1px solid var(--bcm-line)}
.bcm-group:last-child{border:0}
.bcm--img .bcm-leaves{padding-left:50px!important}
.bcm-leaves a{padding:6px 0}
.bcm-img{width:40px;height:40px}.bcm-img--top{width:28px;height:28px}
}
@media (prefers-reduced-motion:reduce){.bcm-panel{transition:none!important}}
CSS;
    }
}

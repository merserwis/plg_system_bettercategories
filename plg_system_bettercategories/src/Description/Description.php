<?php

/**
 * @package     Merserwis.Plugin
 * @subpackage  System.bettercategories
 *
 * The description of a store category (or of the store, on its home page) on the category page: where
 * the category layout holds Gridbox's description block — also when the block was moved out of the
 * category header, where Gridbox leaves it as the placeholder "Category Description" — or at a place
 * of its own. It can show only its beginning with a "Read more" button, and a short lead of its own
 * elsewhere (e.g. under the header, the rest under the products). The whole text is always in the HTML
 * of the page, once: shortening is done by the browser (a height limit), so search engines read the
 * description as before; it is also given to them as schema.org CollectionPage data.
 */

namespace Merserwis\Plugin\System\BetterCategories\Description;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use Merserwis\Plugin\System\BetterCategories\Extension\BetterCategories;

final class Description
{
    /** "layout": where the category layout has Gridbox's description block; the others as for the subcategories. */
    public const POSITIONS = ['layout', 'after_intro', 'before_products', 'after_products', 'page_top', 'before_item', 'after_item'];

    /** Places of the lead when the description is split. */
    public const LEAD_POSITIONS = ['after_intro', 'before_products', 'page_top'];

    /** The text Gridbox leaves in a description block outside the category header. */
    public const PLACEHOLDER = 'Category Description';

    /** The start tag of Gridbox's description block. */
    private const SLOT = '#<div\b[^>]*\sclass="(?:[^"]*\s)?intro-category-description(?:\s[^"]*)?"[^>]*>#i';

    private BetterCategories $plugin;

    private Registry $params;

    public function __construct(BetterCategories $plugin, Registry $params)
    {
        $this->plugin = $plugin;
        $this->params = $params;
    }

    public static function enabled(Registry $params): bool
    {
        return (bool) (int) $params->get('desc_enabled', 0);
    }

    public function position(): string
    {
        $position = (string) $this->params->get('desc_position', 'layout');

        return in_array($position, self::POSITIONS, true) ? $position : 'layout';
    }

    public function itemId(): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '', (string) $this->params->get('desc_item_id', ''));
    }

    public function leadPosition(): string
    {
        $position = (string) $this->params->get('desc_lead_position', 'after_intro');

        return in_array($position, self::LEAD_POSITIONS, true) ? $position : 'after_intro';
    }

    /**
     * The page with the description put into the first description block of the layout ("layout") or
     * every description block taken off (any other place: the block to insert is returned). Further
     * blocks are emptied, so a placeholder never shows and the text is on the page once. A split
     * description gives its lead as a block of its own.
     *
     * @return array{0: string, 1: string, 2: string, 3: string} the page, the description and the lead
     *         still to be inserted ('' for none), what goes into the head (styles, structured data)
     */
    public function prepare(string $body, int $appId, int $categoryId, bool $show): array
    {
        $bodyStart = stripos($body, '<body');
        if ($bodyStart === false) {
            return [$body, '', '', ''];
        }
        $slots = [];
        $offset = $bodyStart;
        while (preg_match(self::SLOT, $body, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $m[0][1];
            $end   = $this->elementEnd($body, $start);
            if ($end === null) {
                break;
            }
            $slots[] = [$start, $start + strlen($m[0][0]), (int) strrpos(substr($body, 0, $end), '<'), $end];
            $offset  = $end;
        }

        $layout = $this->position() === 'layout';
        if ($layout && !$slots) {
            return [$body, '', '', ''];
        }
        [$title, $text] = $show ? $this->source($appId, $categoryId) : ['', ''];
        [$lead, $rest]  = $this->split($text);
        $block = $rest !== '' ? $this->render($rest, $categoryId) : '';
        $intro = $lead !== '' ? $this->renderLead($lead, $categoryId) : '';
        // the data are of the text as shown: without the line or marker of the split
        $head  = $text !== '' ? '<style id="bettercategories-desc-css">' . self::css() . '</style>' . $this->schema($body, $title, $lead . "\n" . $rest) : '';

        // from the last block to the first, so the offsets of the others stay right
        foreach (array_reverse($slots, true) as $i => [$start, $innerStart, $innerEnd, $end]) {
            if ($layout) {
                $body = substr($body, 0, $innerStart) . ($i === 0 ? $block : '') . substr($body, $innerEnd);
                continue;
            }
            // the whole block goes, with Gridbox's wrapper around it when that holds nothing else
            if (preg_match('#<div\s+class="intro-post-info"\s*>\s*$#i', substr($body, max(0, $start - 200), min(200, $start)), $w)
                && preg_match('#^\s*</div>#i', substr($body, $end, 200), $c)) {
                $start -= strlen($w[0]);
                $end   += strlen($c[0]);
            }
            $body = substr($body, 0, $start) . substr($body, $end);
        }

        return [$body, $layout ? '' : $block, $intro, $head];
    }

    /**
     * The description split into a lead and the rest (setting): at the first horizontal line (<hr>) or
     * paragraph holding only the marker (default ---; Gridbox's editor has no button for a line), or
     * after the first paragraph (with the heading above it). Without such a place: no lead. The line
     * or marker itself is not shown.
     *
     * @return array{0: string, 1: string} lead, rest
     */
    public function split(string $html): array
    {
        $mode = (string) $this->params->get('desc_split', 'off');
        if ($html === '' || !in_array($mode, ['marker', 'paragraph'], true)) {
            return ['', $html];
        }
        if ($mode === 'paragraph') {
            if (!preg_match('#</p\s*>#i', $html, $m, PREG_OFFSET_CAPTURE)) {
                return ['', $html];
            }
            $cut = $m[0][1] + strlen($m[0][0]);
            // only between elements of the top level: a paragraph inside a box (<div><p>…</p><p>…</p></div>) is not cut, the two
            // halves would each be left with an open or a lone closing tag
            if (!self::topLevel($html, $cut)) {
                return ['', $html];
            }

            return [trim(substr($html, 0, $cut)), trim(substr($html, $cut))];
        }
        $marker = self::plain((string) $this->params->get('desc_split_marker', '---'));
        preg_match_all('#<hr\b[^>]*>|<p\b[^>]*>(.*?)</p\s*>#is', $html, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($all as $m) {
            if (!isset($m[1]) || ($marker !== '' && self::plain($m[1][0]) === $marker)) {
                if (!self::topLevel($html, $m[0][1])) {
                    return ['', $html];
                }

                return [trim(substr($html, 0, $m[0][1])), trim(substr($html, $m[0][1] + strlen($m[0][0])))];
            }
        }

        return ['', $html];
    }

    /**
     * The HTML before $at closes every element it opens (no element left open across the cut). Void elements (img, br…)
     * and comments do not count.
     */
    private static function topLevel(string $html, int $at): bool
    {
        $void  = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr'];
        $depth = 0;
        $head  = preg_replace('#<!--.*?-->#s', '', substr($html, 0, $at)) ?? '';
        preg_match_all('#<(/?)([a-z][a-z0-9-]*)\b[^>]*?(/?)>#i', $head, $tags, PREG_SET_ORDER);
        foreach ($tags as [$all, $close, $name, $self]) {
            if ($self === '/' || \in_array(strtolower($name), $void, true)) {
                continue;
            }
            $depth += $close === '/' ? -1 : 1;
        }

        return $depth === 0;
    }

    /** Text of HTML: tags off, entities decoded, spaces (also no-break ones) joined. */
    private static function plain(string $html): string
    {
        $html = preg_replace('#<(?:br|/p|/h[1-6]|/li|/div|/td)\b[^>]*>#i', ' $0', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? $text);
    }

    /**
     * schema.org CollectionPage of the category: its name, the description as plain text (up to 500
     * characters) and its address (the canonical one of the page, when it has one). "Automatic" adds it
     * only when the page has no CollectionPage of its own (e.g. from Gridbox's SEO settings).
     */
    private function schema(string $body, string $title, string $text): string
    {
        $mode = (string) $this->params->get('desc_schema', 'auto');
        if ($mode === 'no' || ($mode !== 'yes' && stripos($body, '"CollectionPage"') !== false)) {
            return '';
        }
        $plain = self::plain($text);
        if (mb_strlen($plain) > 500) {
            $cut   = mb_substr($plain, 0, 500);
            $space = mb_strrpos($cut, ' ');
            $plain = rtrim(mb_substr($cut, 0, $space > 300 ? $space : 500), " ,.;:–-") . '…';
        }
        $url = preg_match('#<link\b[^>]*\brel="canonical"[^>]*\bhref="([^"]+)"#i', $body, $m) || preg_match('#<link\b[^>]*\bhref="([^"]+)"[^>]*\brel="canonical"#i', $body, $m)
            ? html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') : $this->pageUrl();
        $data = array_filter(['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => $title, 'description' => $plain, 'url' => $url], fn ($v) => $v !== '');

        // JSON_HEX_TAG: a text can never close the script element
        return '<script type="application/ld+json">'
            . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE)
            . '</script>';
    }

    /**
     * The address of this page without a canonical link: its path and, without SEF, the parameters that name the page
     * (index.php?option=com_gridbox&view=blog&app=…&id=…), nothing else of the request.
     */
    private function pageUrl(): string
    {
        $uri   = Uri::getInstance();
        $route = array_filter(array_intersect_key($uri->getQuery(true), array_flip(['option', 'view', 'app', 'id', 'Itemid', 'lang'])),
            fn ($v) => \is_string($v) && preg_match('/^[A-Za-z0-9_-]{1,64}$/', $v));

        return $uri->toString(['scheme', 'host', 'port', 'path']) . ($route ? '?' . http_build_query($route, '', '&', PHP_QUERY_RFC3986) : '');
    }

    /**
     * Tag, author and search pages of a store use the category layout too: Gridbox fills the header with
     * the tag or author, while a description block moved elsewhere keeps the placeholder. Only such
     * placeholders are emptied here.
     */
    public static function clearPlaceholders(string $body): string
    {
        $bodyStart = stripos($body, '<body');
        if ($bodyStart === false || !str_contains($body, self::PLACEHOLDER)) {
            return $body;
        }

        return substr($body, 0, $bodyStart) . (preg_replace('#(<div\b[^>]*\sclass="(?:[^"]*\s)?intro-category-description(?:\s[^"]*)?"[^>]*>)\s*'
            . preg_quote(self::PLACEHOLDER, '#') . '\s*(</div>)#i', '$1$2', substr($body, $bodyStart)) ?? substr($body, $bodyStart));
    }

    /**
     * The title and description saved in Gridbox: of the category, or of the store on its home page.
     *
     * @return array{0: string, 1: string}
     */
    private function source(int $appId, int $categoryId): array
    {
        // only what Gridbox shows this visitor: a category (and its branch) or a store that is published, of the page's
        // language and of the visitor's access; else Gridbox answers 403 or 404, and the text must not reach the page's data
        if ($categoryId > 0 ? !isset($this->plugin->loadCategories($appId)[$categoryId]) : !$this->plugin->appVisible($appId)) {
            return ['', ''];
        }
        $db    = $this->plugin->db();
        $query = $db->createQuery()->select($db->quoteName(['title', 'description']));
        if ($categoryId > 0) {
            $query->from($db->quoteName('#__gridbox_categories'))
                ->where($db->quoteName('id') . ' = ' . $categoryId)
                ->where($db->quoteName('app_id') . ' = ' . $appId);
        } else {
            $query->from($db->quoteName('#__gridbox_app'))->where($db->quoteName('id') . ' = ' . $appId);
        }
        $row  = $db->setQuery($query)->loadObject();
        $text = trim((string) ($row->description ?? ''));
        if ($text !== '' && (int) $this->params->get('desc_tidy', 1)) {
            $text = self::tidy($text);
        }

        // a description of nothing but tags and spaces counts as none
        $plain = str_ireplace(['&nbsp;', '&#160;', "\xC2\xA0"], '', strip_tags($text, '<img><iframe><video><object><svg>'));

        return [trim((string) ($row->title ?? '')), trim($plain) === '' ? '' : $text];
    }

    /**
     * Spacing left by the editor taken off: empty paragraphs (<p>&nbsp;</p>) and line breaks at the
     * start or end of a heading or paragraph. The text and its tags stay as they are.
     */
    public static function tidy(string $html): string
    {
        $html = preg_replace('#(<(h[1-6]|p)\b[^>]*>)(?:\s|&nbsp;|&\#160;|\xC2\xA0|<br\s*/?>)+#i', '$1', $html) ?? $html;
        $html = preg_replace('#(?:\s|&nbsp;|&\#160;|\xC2\xA0|<br\s*/?>)+(</(h[1-6]|p)>)#i', '$1', $html) ?? $html;
        $html = preg_replace('#<p\b[^>]*>\s*</p>#i', '', $html) ?? $html;

        return trim($html);
    }

    /** The lead of a split description: shown whole, with spacing of its own. */
    public function renderLead(string $text, int $categoryId): string
    {
        $int  = fn (string $key, int $default): int => max(-200, min(400, (int) $this->params->get($key, $default)));
        $tidy = (int) $this->params->get('desc_tidy', 1) ? ' bettercategories-desc--tidy' : '';

        return '<div id="bettercategories-desc-lead-' . max(0, $categoryId) . '" class="bettercategories-desc bettercategories-desc--lead' . $tidy . '"'
            . ' style="--bcd-mt:' . $int('desc_lead_margin_top', 0) . 'px;--bcd-mb:' . $int('desc_lead_margin_bottom', 24) . 'px">'
            . '<div class="bettercategories-desc-body">' . $text . '</div></div>';
    }

    /** The description (its styles go into the head); folded to its beginning when that is set. */
    public function render(string $text, int $categoryId): string
    {
        $int = fn (string $key, int $default, int $min, int $max): int => max($min, min($max, (int) $this->params->get($key, $default)));
        $id  = 'bettercategories-desc-' . max(0, $categoryId);

        $vars = '--bcd-mt:' . $int('desc_margin_top', 25, -200, 400) . 'px;--bcd-mb:' . $int('desc_margin_bottom', 0, -200, 400) . 'px';
        $tidy = (int) $this->params->get('desc_tidy', 1) ? ' bettercategories-desc--tidy' : '';
        $fold = (bool) (int) $this->params->get('desc_fold', 0);
        if (!$fold) {
            return '<div id="' . $id . '" class="bettercategories-desc' . $tidy . '" style="' . $vars . '"><div class="bettercategories-desc-body">' . $text . '</div></div>';
        }

        $height = $int('desc_height', 180, 40, 2000);
        $phone  = $int('desc_height_mobile', 0, 0, 2000) ?: $height;
        $vars  .= ';--bcd-h:' . $height . 'px;--bcd-h-m:' . $phone . 'px';
        $align  = in_array($align = (string) $this->params->get('desc_button_align', 'center'), ['left', 'center', 'right'], true) ? $align : 'center';
        $style  = in_array($style = (string) $this->params->get('desc_button_style', 'filled'), ['filled', 'outline', 'link'], true) ? $style : 'filled';
        $vars  .= ';--bcd-align:' . ['left' => 'start', 'center' => 'center', 'right' => 'end'][$align];
        foreach (['desc_button_bg' => '--bcd-btn-bg', 'desc_button_color' => '--bcd-btn-c'] as $key => $var) {
            $color = trim((string) $this->params->get($key, ''));
            if (preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s.,%]+\)|[a-zA-Z]{3,20})$/', $color) && strtolower($color) !== 'none') {
                $vars .= ';' . $var . ':' . $color;
            }
        }

        $this->plugin->loadLanguage();
        $more = trim((string) $this->params->get('desc_more_text', '')) ?: Text::_('PLG_SYSTEM_BETTERCATEGORIES_DESC_MORE_DEFAULT');
        $less = (int) $this->params->get('desc_less', 1)
            ? (trim((string) $this->params->get('desc_less_text', '')) ?: Text::_('PLG_SYSTEM_BETTERCATEGORIES_DESC_LESS_DEFAULT')) : '';
        $classes = 'bettercategories-desc bettercategories-desc--fold is-folded' . ((int) $this->params->get('desc_fade', 1) ? ' bettercategories-desc--fade' : '') . $tidy;

        return '<div id="' . $id . '" class="' . $classes . '" style="' . $vars . '">'
            . '<div class="bettercategories-desc-body" id="' . $id . '-body">' . $text . '</div>'
            . '<div class="bettercategories-desc-more"><button type="button" class="bettercategories-desc-toggle bettercategories-desc-toggle--' . $style . '"'
            . ' aria-expanded="false" aria-controls="' . $id . '-body"' . ($less !== '' ? ' data-less="' . htmlspecialchars($less, ENT_QUOTES, 'UTF-8') . '"' : '') . '>'
            . htmlspecialchars($more, ENT_QUOTES, 'UTF-8') . '</button></div></div>'
            . self::script($id)
            // without JavaScript the whole text is shown
            . '<noscript><style>#' . $id . ' .bettercategories-desc-body{max-height:none!important;-webkit-mask-image:none!important;mask-image:none!important}#' . $id . ' .bettercategories-desc-more{display:none}</style></noscript>';
    }

    /**
     * Styles of the description. The beginning is shown up to the height set, faded out with a mask
     * (no colour to match: any background, also in dark mode); the button takes the accent colour
     * of the Gridbox theme. Printed, the whole text.
     */
    public static function css(): string
    {
        // the background overlay of Gridbox rows lets clicks through, so the links of the description work (as MerTools did)
        return '.com_gridbox.blog .ba-overlay{pointer-events:none}'
            . '.bettercategories-desc{margin:var(--bcd-mt,0) 0 var(--bcd-mb,0);--bcd-limit:var(--bcd-h,180px)}'
            // tidied: the empty paragraphs and line breaks that made the spacing are gone, so headings, paragraphs
            // and lists get even spacing of their own (Gridbox themes set their margins to 0)
            . '.bettercategories-desc--tidy .bettercategories-desc-body :is(p,ul,ol,table,blockquote,figure){margin-top:0;margin-bottom:.8em}'
            . '.bettercategories-desc--tidy .bettercategories-desc-body :is(h1,h2,h3,h4,h5,h6){margin-top:1.1em;margin-bottom:.45em}'
            . '.bettercategories-desc-body>:first-child{margin-top:0!important}.bettercategories-desc-body>:last-child{margin-bottom:0!important}'
            . '.bettercategories-desc--fold .bettercategories-desc-body{overflow:hidden;max-height:var(--bcd-limit);transition:max-height .4s ease}'
            . '.bettercategories-desc--fold:not(.is-folded) .bettercategories-desc-body,.bettercategories-desc--fold.is-short .bettercategories-desc-body{max-height:none}'
            . '.bettercategories-desc--fade.is-folded:not(.is-short) .bettercategories-desc-body{-webkit-mask-image:linear-gradient(to bottom,#000 calc(100% - min(80px,50%)),transparent);mask-image:linear-gradient(to bottom,#000 calc(100% - min(80px,50%)),transparent)}'
            . '.bettercategories-desc--fold.is-short .bettercategories-desc-more,.bettercategories-desc--fold.is-final .bettercategories-desc-more{display:none}'
            . '.bettercategories-desc-more{text-align:var(--bcd-align,center);margin-top:16px}'
            . '.bettercategories-desc-toggle{display:inline-flex;align-items:center;gap:.6em;cursor:pointer;font:inherit;font-size:12px;font-weight:700;letter-spacing:2px;line-height:1.4;text-transform:uppercase;border-radius:50px;padding:14px 30px;border:1px solid var(--bcd-btn-bg,var(--primary,#2f6fde));background:var(--bcd-btn-bg,var(--primary,#2f6fde));color:var(--bcd-btn-c,var(--title-inverse,#fff));transition:opacity .2s,background-color .2s,color .2s}'
            . '.bettercategories-desc-toggle:hover{opacity:.85}'
            . '.bettercategories-desc-toggle--outline{background:transparent;color:var(--bcd-btn-bg,var(--primary,#2f6fde))}'
            . '.bettercategories-desc-toggle--outline:hover{opacity:1;background:var(--bcd-btn-bg,var(--primary,#2f6fde));color:var(--bcd-btn-c,var(--title-inverse,#fff))}'
            . '.bettercategories-desc-toggle--link{border:0;background:none;padding:4px 0;border-radius:0;font-size:inherit;letter-spacing:0;text-transform:none;font-weight:600;color:var(--bcd-btn-bg,var(--primary,#2f6fde))}'
            . '.bettercategories-desc-toggle--link:hover{opacity:1;text-decoration:underline}'
            . '.bettercategories-desc-toggle::after{content:"";width:.45em;height:.45em;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:translateY(-25%) rotate(45deg);transition:transform .2s}'
            . '.bettercategories-desc-toggle[aria-expanded="true"]::after{transform:translateY(25%) rotate(-135deg)}'
            . '.bettercategories-desc-toggle:focus-visible{outline:2px solid var(--bcd-btn-bg,var(--primary,#2f6fde));outline-offset:3px}'
            . '@media (max-width:767px){.bettercategories-desc{--bcd-limit:var(--bcd-h-m,var(--bcd-h,180px))}}'
            . '@media (prefers-reduced-motion:reduce){.bettercategories-desc-body{transition:none!important}}'
            . '@media print{.bettercategories-desc-body{max-height:none!important;-webkit-mask-image:none!important;mask-image:none!important}.bettercategories-desc-more{display:none!important}}';
    }

    /**
     * The button. Runs right after the description (no waiting for the page): a text no more than a
     * little taller than the height set is shown whole, without the button (checked again when its
     * size changes, e.g. when a font or picture arrives). Folding back scrolls to the description
     * when its top is out of view; an address with #anchor inside the text opens it.
     */
    private static function script(string $id): string
    {
        return '<script>(function(n){if(!n)return;var b=n.querySelector(".bettercategories-desc-body"),t=n.querySelector(".bettercategories-desc-toggle");if(!b||!t)return;'
            . 'var more=t.textContent,less=t.getAttribute("data-less")||"",tm=0;'
            . 'function lim(){return parseFloat(getComputedStyle(n).getPropertyValue("--bcd-limit"))||0}'
            . 'function check(){var l=lim();n.classList.toggle("is-short",!!l&&b.scrollHeight<=l+Math.max(48,l*.2))}'
            . 'function done(){clearTimeout(tm);if(!n.classList.contains("is-folded"))b.style.maxHeight="none"}'
            // the full height is read while still folded: read after the class goes, the height would jump without the transition
            . 'function open(){b.style.maxHeight=b.scrollHeight+"px";n.classList.remove("is-folded");t.setAttribute("aria-expanded","true");'
            . 'if(less)t.textContent=less;else n.classList.add("is-final");tm=setTimeout(done,500)}'
            . 'function close(){clearTimeout(tm);b.style.maxHeight=b.scrollHeight+"px";b.offsetHeight;n.classList.add("is-folded");b.style.maxHeight="";'
            . 't.setAttribute("aria-expanded","false");t.textContent=more;var r=n.getBoundingClientRect();if(r.top<0)n.scrollIntoView({block:"start",behavior:"smooth"})}'
            . 'b.addEventListener("transitionend",function(e){if(e.target===b)done()});'
            . 't.addEventListener("click",function(){n.classList.contains("is-folded")?open():close()});'
            . 'check();if(window.ResizeObserver)new ResizeObserver(check).observe(b);window.addEventListener("resize",check);'
            . 'function hash(){try{var h=location.hash.slice(1),e=h&&document.getElementById(decodeURIComponent(h));if(e&&b.contains(e)&&n.classList.contains("is-folded")&&!n.classList.contains("is-short")){open();setTimeout(function(){e.scrollIntoView()},450)}}catch(x){}}'
            . 'hash();window.addEventListener("hashchange",hash);'
            . '})(document.getElementById("' . $id . '"));</script>';
    }

    /**
     * Offset right after the </div> closing the <div> that starts at $start (div nesting counted). A comment, a script or a
     * style is stepped over whole: a "</div>" written inside one is not a tag.
     */
    private function elementEnd(string $body, int $start): ?int
    {
        $depth  = 0;
        $offset = $start;
        while (preg_match('#<!--.*?-->|<(script|style)\b[^>]*>.*?</\1\s*>|<(/?)div\b[^>]*>#is', $body, $tag, PREG_OFFSET_CAPTURE, $offset)) {
            $offset = $tag[0][1] + strlen($tag[0][0]);
            if (!isset($tag[2]) || $tag[2][1] < 0) {
                continue;
            }
            $depth += $tag[2][0] === '/' ? -1 : 1;
            if ($depth === 0) {
                return $offset;
            }
        }

        return null;
    }
}

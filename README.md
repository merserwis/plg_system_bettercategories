# Better Categories for Balbooa Gridbox (Joomla 6 Package)

[![Joomla Version](https://img.shields.io/badge/Joomla-6.x-blue?style=for-the-badge&logo=joomla)](https://www.joomla.org)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%20--%208.5-777BB4?style=for-the-badge&logo=php)](https://www.php.net)
[![Gridbox](https://img.shields.io/badge/Balbooa%20Gridbox-2.20.3.1%20(Store)-orange?style=for-the-badge)](https://www.balbooa.com/joomla-gridbox)
[![Version](https://img.shields.io/badge/Release-v1.2.0-brightgreen?style=for-the-badge)](https://github.com/merserwis/)
[![License](https://img.shields.io/badge/License-GPL--3.0-green?style=for-the-badge)](https://www.gnu.org/licenses/gpl-3.0.html)

A native Joomla 6 extension that makes **Balbooa Gridbox store categories** navigable the way shoppers expect: a parent category **shows its subcategories first** (as text links or image tiles, with product counts), and the products below.

Out of the box, a Gridbox store category page lists the products of the **whole category subtree**. A visitor who opens *Meters* immediately gets a mixed grid of every meter from every subcategory, with no way to pick *Installation testers* or *Thermal cameras* first. Better Categories adds that missing level of navigation — without touching Gridbox, its templates or its product list.

---

## ⚡ Key Highlights & Capabilities

* **Subcategories on every store category page:** On the store home page and on each category with children, the plugin lists the **direct subcategories of the current category**. Leaf categories (no children) are left exactly as Gridbox renders them.
* **Product counts that match Gridbox:** Each subcategory shows the number of products in it **and all of its subcategories** — counted the same way as Gridbox's own *Categories* element: published and currently live products the visitor is allowed to see, primary category plus Gridbox's *additional categories* map, without the subscription add-ons Gridbox hides from listings.
* **Canonical links:** Subcategory links use the same menu item (`Itemid`) Gridbox itself would pick — the category's menu item, the nearest parent's, the store app's, or the Gridbox home page — so they resolve to the same canonical URLs as Gridbox's sitemap, e.g. `/oferta/meters/installation-testers`.
* **Text links or image tiles:** A simple list of links, one under another or in one wrapping row — or tiles with images in four styles: *text below the image*, *card*, *text on the image with a gradient* and *cover*.
* **What is inside each category (new in 1.1.0):** Every category can show its own subcategories — as text under the name, a **drawer sliding down** that pushes the layout, a **panel sliding in from the side**, a **card flip** with the list on the back, or a **modern tooltip**. Custom title (e.g. *In this category:*), list / inline / chip styles, a limit with a *+N more* link, product counts, colours, hover or button opening, and a separate mode for phones.
* **Fewer categories than columns (new in 1.1.0):** Six columns but only three subcategories? Keep them on the left, centre them, or stretch them over the full width of the module — per device.
* **Fast WebP thumbnails (new in 1.2.0):** Tiles load small WebP copies of the images (tile width and twice that for sharp screens) instead of full-size photos — made once, kept on the server, EXIF rotation and transparency preserved.
* **Per-level settings (new in 1.2.0):** Large image tiles on the store home page, compact text links deeper down — heading, display, tile style, direction, columns and the *what is inside* mode can differ per level of the tree.
* **Sibling bar on the last level (new in 1.2.0):** A category without subcategories shows the categories next to it (current one highlighted) and a link back to the parent, so shoppers switch without going back. It scrolls sideways on phones.
* **Structured data (new in 1.2.0):** The listed categories as a schema.org `ItemList`, and a `BreadcrumbList` (store → parents → current category) that is added only when the page has none of its own.
* **Smart category images:** Tile images come from, in order: an image chosen for the category in the plugin → the image set on the category in Gridbox → **the image of the most viewed product** in the category and its subcategories. Categories need no manual work to look good.
* **Modern hover effects:** Zoom in, zoom out, lift with shadow, light shine, grayscale-to-colour, tint fade-in / fade-out, tilt, outline ring — or none.
* **Full visual control:** Position on the page, alignment, direction, responsive columns (desktop / tablet / phone), font sizes and colours (empty = Gridbox theme), image shape (rectangle, rounded corners, circle), aspect ratio, fit, background, colour tint, drop shadow (angle, distance, blur, size, opacity) and spacing.
* **Zero impact on search, filters and pagination:** Search results, filter queries, tag and author listings are never modified; by default the list appears only on the first page of a category.
* **Administrator menu entry:** *Better Categories for Gridbox* appears in the Joomla 6 administrator menu and opens the plugin settings directly.
* **Joomla 6 & PHP 8.5 native:** Service-provider plugin with lazy loading, `createQuery()`, dependency-injected database, no deprecated Joomla APIs in the extension code, no warnings on PHP 8.5.

---

## 🛠️ How It Works

```mermaid
flowchart LR
    A[Visitor opens a Gridbox<br/>store category page] --> B[Gridbox renders the page<br/>header, filters, product list]
    B --> C{Better Categories<br/>onAfterRender}
    C -->|search / filter / tag / author<br/>or page 2+| G[Page unchanged]
    C -->|store category page| D[Load visible subcategories<br/>of the current category]
    D -->|no subcategories| H[Sibling bar when enabled,<br/>otherwise unchanged]
    D --> E[Counts, images and<br/>canonical links]
    E --> F[Insert the list at the chosen<br/>position, e.g. above the products]
```

1. The system plugin runs after Gridbox has rendered the page (`onAfterRender`), only on the site, only for `com_gridbox` category views (`view=blog`) of **store apps** (or the apps you list).
2. It reads the published categories of the app the visitor may see (access level, language), drops branches under unpublished or hidden parents and finds the children of the current category (the store home page is category `0`).
3. It counts products per subtree, picks tile images and builds links with Gridbox's own `Itemid` rules, then routes them through Joomla's router (and therefore Gridbox's router).
4. The block — a `<nav>` with its own scoped `<style>` — is inserted next to a Gridbox element of the category template: by default **right before the product list** (`.ba-item-blog-posts`). If the chosen anchor is not on the page, it falls back to *above the products*; if there is no product list at all, nothing is inserted.

Nothing is written to the database and Gridbox files are not modified; the only files the plugin creates are the image thumbnails in `media/plg_system_bettercategories/thumbs`. Disabling the plugin restores the original pages instantly.

---

## 🚀 Installation & Package Structure

1. Download `pkg_bettercategories-1.2.0.zip` from [Releases](https://github.com/merserwis/plg_system_bettercategories/releases).
2. In the Joomla administrator go to **System → Install → Extensions** and upload the package.
3. On a fresh install the plugin is **enabled automatically**; an update keeps whatever you chose before. If Balbooa Gridbox is not installed, the installer says so in a notice.
4. Open **Better Categories for Gridbox** in the administrator menu (or *System → Plugins → System - Better Categories for Gridbox*) and adjust the settings.

| Extension | Type | Purpose |
|---|---|---|
| `plg_system_bettercategories` | System plugin | Renders the subcategory list on Gridbox store category pages. |
| `com_bettercategories` | Administrator component | Menu entry *Better Categories for Gridbox* that opens the plugin settings. |
| `pkg_bettercategories` | Package | Installs and updates both in one step. |

Uninstalling the package removes both extensions; the plugin stores nothing outside its own settings.

**Updates:** the package registers the update server `https://raw.githubusercontent.com/merserwis/plg_system_bettercategories/main/update.xml`, so new releases appear in *System → Update → Extensions*. Joomla verifies each download against the SHA-256 checksum in `update.xml`.

---

## 👀 Live Preview

The *Layout*, *Text and colours* and *Tiles* tabs have a **live preview column**. It shows the list exactly as the plugin renders it — real categories, counts and images from your store — using the values currently in the form, before you save. Switch between **Desktop (1280 px), Tablet (820 px) and Phone (390 px)**: the preview renders at the real device width, scaled to fit, so column settings and all responsive rules apply as on the site. Pick any parent category to preview its subcategories. Sliders show their current value, and colour pickers are enlarged for easier use.

---

## ⚙️ Configuration Reference

Empty typography and colour fields always mean **"use the Gridbox theme value"**.

### Settings

| Option | Default | Description |
|---|---|---|
| Gridbox app IDs | *(empty)* | Comma-separated IDs of the Gridbox apps to handle. Empty = every store (*Products*) app. |
| List heading | `Categories` | Heading above the list. Empty = no heading. |
| Heading tag | `H2` | `H2`, `H3`, `H4` or `DIV`. |
| Show product count | Yes | Shows `(n)` after each subcategory — products in the subcategory and all of its subcategories. |
| Count on last-level categories only | No | With the count enabled: show `(n)` only on categories without subcategories — the last level, where the products are. |
| Hide products until the last category | No | On the store home page and on categories that have subcategories, hide the Gridbox product list so visitors pick a category first; products appear on the last level. Search results are not affected. |
| Also hide product filters | No | Hide the Gridbox product filters on the same pages. |
| Hide subcategories without products | Yes | Skips subcategories whose count is 0 (e.g. *Uncategorised*). |
| First page of the list only | Yes | No list on `?page=2` and further. |
| Cache time (minutes) | `15` | The finished list is stored and reused (per category, device, language and access level), so pages are not slowed down. `0` = no cache. Saving the settings clears it, together with Joomla's and Gridbox's page caches. |

### Layout

| Option | Default | Description |
|---|---|---|
| Position on the page | Above the product list | *Above / below the product list*, *below the category header*, *at the start of the page content*, *before / after a chosen Gridbox element*. Falls back to *above the product list* when the anchor is missing. |
| Gridbox element ID | *(empty)* | For the *before / after element* positions: the `id` of an element in the category template, e.g. `item-1500368728` (see the page source). |
| Show categories as | Text (links) | *Text (links)* or *Tiles with an image*. |
| List direction | Row by row | *Row by row* (left → right, then the next row), *Column by column* (top → bottom, then the next column), *One scrolling row* (swiped sideways) or *Inline, wrapping* (text links). |
| Text alignment | Left | Left, centre or right — heading, links and tile captions. |
| Columns | `0` | `0` = automatic: 1 column of text, 4 tiles per row. For horizontal tiles: how many tiles fit the width. |
| Columns — tablet (≤ 1024 px) | `0` | `0` = same as desktop. |
| Columns — phone (≤ 768 px) | `0` | `0` = same as tablet. |
| Space above / below the list (px) | `0` / `24` | Outer margins of the block. |
| Side margin — desktop and tablet / phone (px) | `0` / `16` | Left and right space, so tiles do not touch the screen edges on phones. |
| Gap between items (px) | `16` | Space between links / tiles. |
| When there are fewer items than columns | Align left | *Align left*, *Centre* (items keep the width they have in a full row) or *Stretch to full width* (items share the whole width of the module). Applies to every direction and device. |
| Heading space above / below (px) | `0` / `16` | Margins of the heading. |

Column counts work even on sites that strip `@media` rules from the HTML served to phones: the plugin also detects phones and tablets from the browser's User-Agent and uses the matching count directly, while explicit `@media` ranges keep a cached page correct on every device.

### Text and colours

| Option | Default | Description |
|---|---|---|
| Font size | *(theme)* | E.g. `16px`, `1rem`, `1.2em`; a bare number means pixels. |
| Heading font size | *(theme)* | Same format. |
| Font size — phone / Heading font size — phone | *(desktop value)* | Separate sizes for phones (≤ 768 px). |
| Text colour | *(theme)* | Heading, counts and texts. |
| Link colour | *(theme)* | Category names. |
| Link hover colour | *(theme)* | Also the colour of the *Outline* hover effect. |

### Tiles

| Option | Default | Description |
|---|---|---|
| Tile style | Text below the image | *Text below the image*, *Card* (border and shadow), *Text on the image with a gradient*, *Cover* (image fills the tile, centred text over a tint). |
| Gradient / tint colour | `#000000` | Colour of the gradient (*Text on the image*) or tint (*Cover*). |
| Image shape | Rounded corners | Rectangle, rounded corners or circle. |
| Corner radius (px) | `12` | For rounded corners. |
| Image aspect ratio | `1:1` | `1:1`, `4:3`, `3:2`, `16:9`, `3:4`. A circle is always `1:1`. |
| Text distance from the image — top / bottom (px) | `10` / `0` | Spacing of the caption in the *Text below the image* and *Card* styles. |
| Background under the image | `#ffffff` | Shown under transparent PNGs and around images with the *Contain* fit. |
| Image fit | Contain | *Contain* = the whole image is visible (best for product photos on white). *Cover* = cropped to the shape. |
| Image tint colour / Tint opacity (%) | *(none)* / `0` | A colour layer over the image, under the text. |
| Mouse hover effect | Zoom in | See the table below. |
| Shadow under the image | No | Enables the drop shadow. |
| Shadow colour | `#000000` | |
| Shadow angle (°) | `90` | Direction the shadow falls: 0° = right, 90° = down, 180° = left, 270° = up. |
| Shadow distance (px) | `8` | |
| Intensity — blur (px) | `20` | Higher = softer, wider shadow. |
| Shadow size (px) | `0` | Grows (+) or shrinks (−) the shadow relative to the image. |
| Shadow opacity (%) | `20` | |
| Use the Gridbox category image | Yes | Use the image set on the category in Gridbox when the plugin has none for it. |
| Most popular product image | Yes | Otherwise use the image of the most viewed visible product in the category and its subcategories. |
| Fast thumbnails (WebP) | Yes | Small WebP copies instead of full-size photos: one at the thumbnail width, one twice as wide for sharp screens (`srcset`). Made once, kept in `media/plg_system_bettercategories/thumbs`; a changed image gets new copies. External images, images not larger than the tile and servers without WebP support in PHP GD keep the original image. |
| Thumbnail width (px) / quality | `480` / `80` | Width of the smaller copy (160–1600) and WebP quality (40–95). |
| Own category images | *(empty)* | Pick a category and an image from Media — takes priority over every other source. |

### Subcategories

| Option | Default | Description |
|---|---|---|
| Show what is inside | Do not show | *Text under the category name* (always visible), *Drawer sliding down* (opens under the category and pushes the layout down), *Panel sliding in from the side* (over the tile), *Card flip* (the tile turns over; the back lists the subcategories and a *View all* link), *Tooltip* (a bubble above the category). Side panel and card flip need tiles; text links use the drawer instead. |
| Open on | Mouse hover and button | Hover opens panels only on devices with a real mouse; touch screens and keyboards always use the **+** button (it turns into **×**). *Button only* disables hover. |
| On phones | Automatic | *Automatic* turns the card flip and side panel (too small on a narrow tile) into the drawer; *Same as desktop*; or a fixed mode for phones. Phones are recognised from the browser, like the phone column setting. |
| Title | `In this category:` | Shown above the list; empty = no title. |
| List style | One under another | *One under another*, *In one line, comma separated* or *Chips*. |
| Maximum subcategories | `6` | Longer lists end with a *+N more* link to the category. `0` = all. |
| Show product counts | No | Counts next to the subcategories, counted like the main list. |
| Panel background / text colour | `#ffffff` / *(theme)* | Colours of the drawer, side panel, card back and tooltip. |
| “More” link text | `+%d more` | `%d` = the number of hidden subcategories. |
| “View all” link text | `View all` | Card back only. |
| Button label for screen readers | `Subcategories of %s` | `%s` = the category name. |

Only visible subcategories are listed (same access, publishing and language rules as the main list, empty ones hidden when *Hide empty categories* is on). Panels are keyboard accessible: the button has `aria-expanded` / `aria-controls`, **Escape** closes the panel and returns focus to the button, a click outside closes it, and opening one panel closes the others. The drawer under a narrow tile (under 200 px) opens across the whole row; the tooltip moves to stay inside the window and opens downwards when there is no room above.

### Per level

Rows of settings for one level of the category tree each: *Store home page*, *Level 1* (a top category), *Level 2* (its subcategory), *Level 3 and deeper*. Every field left at *— main setting —* keeps the value from the other tabs.

| Field | Overrides |
|---|---|
| List heading | The heading above the list. |
| Show categories as | Text links or tiles. |
| Tile style | Text below the image, card, text on the image, cover. |
| List direction | Rows, columns, one scrolling row, inline. |
| Columns / tablet / phone | Column counts per device. |
| Show what is inside | The subcategory panel mode. |

The live preview shows the level of the category chosen in it.

### Navigation and SEO

| Option | Default | Description |
|---|---|---|
| Sibling categories on the last level | No | A category without subcategories shows a bar of chips with the categories next to it — the current one highlighted (`aria-current="page"`) — and a link back to the parent. Products stay as Gridbox shows them. On phones the bar scrolls sideways with the current category in view. |
| Bar heading | `Related categories` | Empty = no heading. |
| Link back to the parent category / text | Yes / `Back to %s` | `%s` = the parent category (or the store on top-level categories). |
| Structured data: category list | Yes | The listed categories as a schema.org `ItemList` (JSON-LD). |
| Structured data: breadcrumbs | Automatic | A schema.org `BreadcrumbList`: store → parent categories → current category. *Automatic* adds it only when the page has no breadcrumb data of its own (e.g. from a breadcrumbs module), so there are never two; *Yes* always, *No* never. |

### Hover effects

| Effect | What happens |
|---|---|
| None | No animation. |
| Zoom in | The image scales up slightly. |
| Zoom out | The image starts slightly enlarged and returns to the full frame. |
| Lift with shadow | The tile moves up and its shadow grows (uses your shadow settings when enabled). |
| Light shine | A diagonal light reflection sweeps across the image. |
| Grayscale to colour | Images are grayscale until hovered. |
| Tint fades out | The image tint disappears on hover (set a tint colour and opacity). |
| Tint fades in | A tint appears on hover (tint colour, or 35 % black if none is set). |
| Tilt and zoom | The image rotates slightly while zooming in. |
| Outline | A ring in the link hover colour appears around the image. |

---

## 🧪 Verification & Testing

Versions 1.0.0 to 1.2.0 were tested on **Joomla 6.1.3 with PHP 8.5.10**, MySQL 8.0 and **Gridbox 2.20.3.1** (early development builds also on PHP 8.4), with a store structure of nested categories, products assigned directly to parent categories, unpublished products and categories, transparent PNG and JPG images.

1. **Counts and visibility** — parent categories count products of the whole subtree; unpublished products and categories under unpublished parents are excluded; empty categories are hidden by default.
2. **Image sources** — plugin image → Gridbox category image → most viewed visible product (an unpublished product with more views is ignored).
3. **Positions** — all six positions, including the fallback when the anchor element is missing.
4. **Unchanged pages** — search (`?search=`), filters (`?query=`), tag and author listings, pagination (`?page=2`), leaf categories and product pages.
5. **Code health** — no PHP warnings or notices from the extension on PHP 8.5; no deprecated Joomla API calls in the extension code. The only deprecations on these pages come from Gridbox's own router (used for every Gridbox link) and from Joomla core form fields.

6. **Subcategory panels (1.1.0)** — every mode in a real browser on desktop and phone widths: opening with the button, closing with the second click, Escape and a click outside, one panel open at a time, no horizontal scrolling, no JavaScript errors. With the panels switched off the output is identical to 1.0.0 in all 42 tested page × settings × device combinations.

7. **1.2.0** — thumbnails from JPEG (also a sideways EXIF photo), transparent PNG and 6000 × 4000 px photos: WebP copies at 480 / 960 px, rotation and transparency kept; with many large images the first visit makes what fits in 1.5 s and skips the cache, the next one finishes. Per-level overrides on every level, the sibling bar on desktop and phone (no horizontal page scroll), JSON-LD validated as JSON with absolute URLs. No PHP warnings from the extension with full error reporting. With thumbnails and structured data off, the output is identical to 1.1.0 in all 42 combinations; updating from 1.1.0 keeps all settings.

Quick check on your site: open a store category that has subcategories and look for `<nav id="bettercategories-` in the page source.

---

## 🩺 Diagnostics

Every Gridbox store category page (also one without subcategories) carries one HTML comment, e.g.
`<!-- Better Categories 1.2.0 | cfg 9c0c6dbd | mobile | cache hit | 0.1 ms (total 0.4 ms) -->` —
plugin version, a hash of the active settings, the detected device, cache status and time spent. `cache miss, thumbnails pending` means some thumbnails are still being made: that page is not cached yet and the next visit completes it.
The plugin always uses its settings as saved in the database, even when the site hands it a different copy (some device-specific extensions or caches pass phones old plugin parameters); the comment then adds `(site passed …, ignored)`, so such a site setup is easy to spot.

---

## 🔒 Security & Performance

* **Read-only data:** the plugin only reads Gridbox tables. The only files it writes are the thumbnails, inside its own media folder, from images inside the site (paths are resolved and checked to stay in the site root). Delete the `thumbs` folder at any time and save the plugin settings (this clears its cache); the thumbnails are made again.
* **Access-aware:** categories and products respect Joomla view levels, publishing state, publish-up / publish-down dates and language, like Gridbox's own listings.
* **Escaped output:** titles, URLs and image paths are HTML-escaped; colours and sizes are validated before they reach the CSS.
* **Fast:** with the cache the plugin adds about 0.1–0.5 ms per page (measured); without it a handful of database queries. A small scoped `<style>` block; no front-end JavaScript unless an interactive subcategory panel is on (then one small inline script per block, no dependencies). Images are lazy-loaded; tiles use small WebP thumbnails with `srcset`, making the first visit to a page with new images slower once (at most 1.5 s of thumbnail work per request).
* **Scoped:** runs only on Gridbox store category pages; every other page is returned untouched.

---

## 📋 Requirements

| Component | Version |
|---|---|
| Joomla | 6.x (tested on 6.1.3) |
| PHP | 8.2 – 8.5 |
| Balbooa Gridbox | Store (*Products*) app; tested with 2.20.3.1 |

Gridbox's store elements (product list, category header) are Gridbox **Pro** features and need an activated Gridbox licence; the subcategory list is placed relative to them.

---

## 📝 Changelog

* **1.2.0** — Fast WebP thumbnails with `srcset`. Per-level settings. Sibling bar with a back link on the last level. schema.org `ItemList` and `BreadcrumbList` (automatic, never duplicated).
* **1.1.0** — *What is inside*: subcategories of each category as text, drawer, side panel, card flip or tooltip, with title, list style, limit, counts, colours, hover/button opening and a phone mode. *When there are fewer items than columns*: align left, centre or stretch.
* **1.0.0** — First release.

---

## 📄 License & Maintainer

* **License:** [GNU General Public License version 3](https://www.gnu.org/licenses/gpl-3.0.html) (GPL-3.0).
* **Maintainer:** [Merserwis](https://github.com/merserwis/)
* *Balbooa* and *Gridbox* are trademarks of their respective owners. This project is not affiliated with Balbooa.

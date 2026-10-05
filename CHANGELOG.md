# Changelog

All changes of **Better Categories for Gridbox**, newest first. Each version is also published as a [GitHub release](https://github.com/merserwis/plg_system_bettercategories/releases) with its installation package.

## 1.5.0 — 2026-10-05

Product filters on store category pages, and a reset of the settings to their defaults.

### 🔎 Product filters (off by default)

- A new **Filters** tab. Switch on *Product filters* and the store category pages get a filter panel; the product list stays Gridbox's own (cards, sorting, page size, pagination type) — it only shows the matching products.
- **What to filter by**, in any order and number:
  - **Price** — from–to, in the currency the visitor sees, with sale prices, store sales and variations as Gridbox calculates them. Products without a price (price on request) are left out of a price filter.
  - **Gridbox fields** — the options of list, radio and checkbox fields (e.g. *Producer*); text fields list every text used.
  - **Technical parameters from the description** — values with a unit read from the product title, introduction and description: voltage, current, power, frequency, resistance, capacitance, temperature, sound level, illuminance, pressure, energy, battery capacity, length, measurement category (CAT I–IV) and protection rating (IP). “1 kV” and “1000 V” are the same value. Shown as tick boxes of the values found or as from–to fields; a product counts with its highest value (e.g. the measuring range), its lowest, or every value found.
  - **Features from the description** — one per line, `True RMS = true rms, trms`: a product has the feature when its title or description contains one of the words (whole words, `*` for longer words).
  - **On sale** and **In stock**.
- **Panel position:** a side bar on the left or right of the products, one row above the products (each filter opens as a menu), a *Filters* button with a side panel, under Gridbox's categories element (e.g. in the page's side column) or before / after any Gridbox element.
- **On phones** a *Filters* button opens the panel from the side, with *Show N products* to close it (also in narrow desktop windows). Optional: the same position as on computers.
- **Counts that help:** every option shows how many products it would give with the filters already chosen; options that would give none are hidden (or greyed out). Within one filter any chosen option is enough — or all of them, the default for features. Long lists show 6 options and *Show more*.
- **Above the products:** the number of products found, the chosen filters as chips to remove one by one, and *Clear all*.
- **Instant:** a change shows the products at once, without reloading the page; the address follows (`?f-producer=sonel,metrel&f-price=100..500`), so a filtered list can be linked, bookmarked, paged, sorted and opened again with *Back*. Optional: an *Apply* button. Without JavaScript the panel works as a plain form.
- **SEO:** filtered lists get `noindex, follow` (setting), so search engines keep indexing the category page itself.
- **Texts in the site language:** English, Polish, Ukrainian and German, or your own texts in the settings. Colours, width and font size of the panel are settings too.
- Subcategories shown above the products stay above the filter bar, and a list hidden under the subcategories (*Hide products until the last category*) appears as soon as a filter is chosen.
- The parameters and features of the products are read once and kept in the cache; only products saved since are read again.

### ♻️ Reset to defaults

- *Settings file* has a new **Reset to defaults** button: every setting gets the value of a fresh installation, saved at once. By default this site's store IDs, element IDs, category images and filters are kept (a tick box).
- *Export styles only* and *Import styles only* also leave out the filters (they refer to this site's Gridbox fields).

---

**Tested on:** Joomla 6.1.3 (Atum), PHP 8.5, Gridbox 2.20.3.1, a store of 636 products: every filter type alone and combined, ranges with open ends and typed prefixes (`2.5k`, `1M`), unknown values in the address (ignored), pagination, sorting and *Back* in a real browser on desktop and phone, all panel positions, export / import / reset. No PHP warnings from the extension. With filters off — and with filters on — the list of subcategories is identical to 1.4.1 in all 42 tested combinations. Update from 1.4.1 keeps all settings.

## 1.4.1 — 2026-10-02

### ❓ Help tooltips

- Every option with a description has a **“?”** beside its name. The explanation appears on hover, on keyboard focus (Tab) and on a click (it then stays open until a click elsewhere or Escape).
- It is shown by CSS next to the “?” itself, so no administrator template can move or hide it. Joomla's *Toggle Inline Help* still works.

### 🌍 Languages

- The extension now ships **English (default), Polish, Ukrainian and German**. Czech, Slovak, Lithuanian, French, Hindi, Chinese, Arabic and Spanish were removed; sites in those languages show English. Their files left by 1.4.0 are removed on update.

---

**Tested on:** Joomla 6.1.3 (Atum), PHP 8.5, Gridbox 2.20.3.1: tooltips with a real mouse (hover, click) on a scrolled page; update from 1.4.0 with files of a removed language present. The list on the site is unchanged.

## 1.4.0 — 2026-10-02

The administrator in 12 languages, right-to-left support, prices calculated exactly as Gridbox does, and a round of security and reliability fixes.

### 🌍 12 administrator languages

- **English, Polish, Ukrainian, German, Czech, Slovak, Lithuanian, French, Hindi, Chinese (Simplified), Arabic and Spanish:** plugin settings, the module, the live preview, the tools and their messages.
- The language **follows the Joomla administrator language** automatically. For any other language, and for a text missing in a translation, **English** is used.
- Messages that were fixed in English (preview, tools, errors) are now translatable too.

### ↔️ Right-to-left sites

- On Arabic (and other right-to-left) sites the list is **mirrored**: the side panel slides in from the other side, toggle buttons, counters, prices and the back arrow follow the writing direction, and the drawer under a narrow tile opens across the whole row from its right edge.
- *Text alignment* Left / Right follows the writing direction (start / end of the line), like the column alignment already did.
- The **live preview** in the administrator shows the list in the direction of the administrator language.

### 💰 Prices exactly as Gridbox calculates them

- **Category sales** apply to the product's **own category and all its parents**, as in Gridbox. Before, a sale on a parent category was missed, and a sale on a category the product was only *additionally* assigned to was wrongly applied.
- **Automatic exchange rates:** with *automatic rates* on in the store, other currencies use the rate Gridbox fetched, not the stored one.
- **Language currency** is chosen as Gridbox does (only with *Associations* on, first match).
- **Publishing dates** of products are compared in the **site time zone**, as Gridbox does. Before, a product appeared or disappeared up to two hours off.
- All store sales are read in **one query** instead of one per sale.

### 🔒 Security and reliability

- **Cache that cannot be flooded:** the cache key uses the store currency picked (not the raw cookie) and no longer the `Host` header. Only existing categories get a cache entry. A page cached for one host never shows another host's address.
- **Fresh list on scheduled changes:** a store sale starting or ending, or a product being published or ending on schedule, gives a fresh list at once, even with a long cache time.
- **Expired cache entries are removed** once an hour. Joomla never does this on the site by itself.
- **Diagnostic comment off by default:** the HTML comment with the version, settings hash and timings is now a setting (*Diagnostic comment*). It is also added when Joomla's *Debug System* is on.
- **Errors stay private:** errors are written to the Joomla log (category `plg_system_bettercategories`). The preview shows a generic message unless debugging is on.
- **Stricter rights:** generating and deleting thumbnails needs the right to edit plugins, like the settings import.
- **Very large images** (over 40 megapixels) are not decoded for thumbnails, also on servers without a PHP memory limit.

### ⚙️ Technical notes

- The block is inserted **after Gridbox's own page processing** (lazy loading, minifying), so Gridbox no longer rewrites its images.
- Element IDs and class names are matched as **whole names** (`data-id` and similar names no longer count).
- The plugin settings are read **once per request**, also with several modules on a page.
- Administrator scripts and styles carry the **file time in their version**, so browsers never keep an old copy.
- The installer now **requires Joomla 6**. If the older *Gridbox Subcategories* plugin (`plg_system_gbsubcats`) is enabled, it is switched off, so the list does not appear twice.
- **Updating from 1.3.0 keeps all settings.** The HTML is identical to 1.3.0 in all 42 tested page × settings × device combinations. The CSS now uses logical properties, so it is the same on left-to-right sites.
- **No PHP warnings** with full error reporting. **Tested** on Joomla 6.1.3, PHP 8.5 and Gridbox 2.20.3.1.

## 1.3.0 — 2026-09-28

Prices on categories, a module for any page, a faster first view, a settings file you can move between sites, and a switch to GPL-3.0.

### 💰 Lowest price on every category

Each category can show the **lowest price of its products**, including its subcategories. For example: *from 1 290 zł*.

- It uses the **sale price** when one is set. Otherwise it uses the price after an **active store sale**, with the same rule order as Gridbox. It also covers **product variations**.
- **Products without a price** ("price on request") and **unpublished products** are left out.
- It is shown in the **store currency**: separators, decimals, symbol position and exchange rate. The currency picked in **Gridbox's currency switcher** is respected and cached separately.
- **Settings:** on / off (off by default), the text (`from %s`, e.g. `from %s net`) and the colour.

### 🧩 New module: `mod_bettercategories`

- Shows the list of **any store category**, or of the **store home**, anywhere. Place it in a module position or in a Gridbox page with the **Joomla Module** element, for example on the home page.
- Uses the **plugin's styles**. Heading, display, tile style, direction, columns per device, the *what is inside* mode and the lowest price can be overridden per module.
- **Never hides products** and adds **no structured data**.
- **Loads images lazily** and has its own cache entries.
- It is installed with the package, unpublished.

### ⚡ Faster first view

- **Generate thumbnails now:** one click makes the WebP thumbnails for every category of the store, in steps with progress, so no visitor waits for them. **Delete thumbnails** removes them all and clears the cache.
- **First row with high priority:** images of the first row of tiles load at once with `fetchpriority="high"`, as many as the columns on the visitor's device. The rest load lazily, which improves LCP in PageSpeed. It is on by default and can be switched off.

### 📁 Settings file: export and import

- **Export all settings** or **Export styles only.** Styles leave out this site's store IDs, element ID and category images, so the file fits another site.
- Export takes the values **currently in the form**, even before saving.
- **Import from file** checks the file. It takes only known settings with plain values, saves them at once and reloads the page. *Import styles only* keeps this site's store IDs, element ID and category images.
- Only administrators who may edit plugins can import, and the request needs a security token. Foreign files are rejected, and unknown or too deeply nested keys are ignored.

### ⚙️ Technical notes

- **Updating from 1.2.0 keeps all settings** and adds the module. The first-row priority starts working right away. The lowest price stays off until you enable it.
- With the new options off, the output is **identical to 1.2.0** in all 42 tested page × settings × device combinations.
- **No PHP warnings** from the plugin or the module appeared with full error reporting. This covered pages, the module, the preview, export, import and thumbnail generation.
- Uninstalling the package also removes the thumbnails.
- **Tested** on Joomla 6.1.3, PHP 8.5 and Gridbox 2.20.3.1.

## 1.2.0 — 2026-09-25

Faster category pages, a layout for each level of the tree, easier navigation on the last level, and structured data for search engines.

### ⚡ Fast WebP thumbnails

Tiles no longer load full-size product photos. The plugin makes small **WebP copies**: one at the tile width and one twice as wide for sharp screens, served with `srcset` and `sizes`.

- Copies are made once and kept in `media/plg_system_bettercategories/thumbs`. A changed image gets new copies automatically.
- Sideways phone photos keep their **EXIF rotation**, and transparent PNGs keep their **transparency**. No PHP exif extension is needed.
- There is a **time budget**. The first visit spends at most 1.5 s on new thumbnails and does not cache that page. The next visit finishes the rest. The diagnostics comment shows `cache miss, thumbnails pending` meanwhile.
- **Safe fallbacks:** the original image stays in use for external images, images not larger than the tile, servers without WebP support in PHP GD, and images too large for the PHP memory limit.
- **Settings:** on / off (on by default), thumbnail width (480 px) and WebP quality (80).

### 🧱 Per-level settings

A new **Per level** tab gives each level of the category tree its own layout: *Store home page*, *Level 1*, *Level 2* and *Level 3 and deeper*. For example, large image tiles on the store home page and compact text links deeper down.

Each row can override these settings. Everything left at *— main setting —* keeps the main value:

- list heading;
- display (text or tiles);
- tile style;
- list direction;
- columns for desktop, tablet and phone;
- the *what is inside* mode.

### 🧭 Sibling bar on the last level

A category without subcategories can show a bar with the **categories next to it**, so shoppers switch without going back.

- The **current category is highlighted** and marked with `aria-current="page"`.
- A **back link** leads to the parent category, or to the store on top-level categories. Its text is configurable (`Back to %s`).
- On phones the bar **scrolls sideways** with the current category in view, and never widens the page.
- The products below stay exactly as Gridbox shows them.
- It is off by default. Turn it on in the new **Navigation and SEO** tab.

### 🔎 Structured data (JSON-LD)

- **ItemList:** the listed categories with absolute URLs. On by default.
- **BreadcrumbList:** store → parent categories → current category. *Automatic* (the default) adds it only when the page has no breadcrumb data of its own, for example from a breadcrumbs module, so there are never two. Can be forced on or off.

### 🛠️ Improvements

- The live preview category list shows the **whole category tree**, including last-level categories for previewing the sibling bar.
- Category links are no longer escaped twice when SEF URLs are off.

### ⚙️ Technical notes

- **Upgrading from 1.1.0 keeps all settings.** Thumbnails and the ItemList start working right away. The sibling bar and per-level rows stay off until you set them.
- **No other visible changes.** With thumbnails and structured data switched off, the output is identical to 1.1.0 in all 42 tested page × settings × device combinations.
- **Thumbnails are the only files the plugin writes.** They go only into its own media folder and are made only from images inside the site. To regenerate them, delete the `thumbs` folder and save the plugin settings, which also clears the plugin cache.
- **Tested** on Joomla 6.1.3, PHP 8.5 and Gridbox 2.20.3.1. The tests used a real browser at desktop and phone widths. Full error reporting showed no PHP warnings from the extension.

## 1.1.0 — 2026-09-25

Shoppers can now see **what is inside a category before opening it**. The release also adds better handling for short lists, where there are fewer categories than columns.

### ✨ New features

#### 🗂️ What is inside each category
Every category in the list can show its own subcategories. Pick one of five modes in the new **Subcategories** tab:

| Mode | How it works |
|---|---|
| **Text under the category name** | The list is always visible under the name. |
| **Drawer sliding down** | Opens under the category and pushes the rest of the layout down. |
| **Panel sliding in from the side** | Slides over the tile. |
| **Card flip** | The tile turns over. The back lists the subcategories and has a *View all* link. |
| **Tooltip** | A modern bubble above the category. |

The panel is configurable:

- **Custom title**, e.g. *In this category:*. Leave it empty for no title.
- **List style**: one under another, in one line (comma separated), or chips.
- **Maximum subcategories**: longer lists end with a *+N more* link to the category.
- **Product counts** next to each subcategory, counted like the main list.
- **Panel background and text colour.**
- **Open on**: mouse hover and the **+** button, or the button only. Hover works only on devices with a real mouse. Touch screens always use the button.
- **On phones**: a separate mode for phones. *Automatic* (the default) turns the card flip and side panel into the drawer, because they are too small on a narrow tile.
- **Editable texts** for the *+N more* link, the *View all* link and the button label for screen readers.

#### 📐 Fewer categories than columns
A new layout option, **When there are fewer items than columns**, controls short lists. For example, 6 columns with only 3 subcategories:

- **Align left**: the current behaviour.
- **Centre**: items keep the width they have in a full row.
- **Stretch to full width**: items share the whole width of the module.

It works for every list direction and applies separately on desktop, tablet and phone.

### 📱 Phones and small tiles

- A drawer under a narrow tile (under 200 px) opens across the whole row, so the text is not squeezed into the tile width.
- The tooltip moves to stay inside the screen. It opens downwards when there is no room above.
- No horizontal scrolling in any mode.

### ♿️ Accessibility

- The **+** / **×** button has `aria-expanded`, `aria-controls` and a readable label with the category name.
- **Escape** closes the panel and returns focus to the button.
- A click outside closes the panel. Opening one panel closes the others.
- Keyboard focus on a category link opens its drawer or tooltip.

### ⚙️ Technical notes

- **No change for existing sites.** Both new features are off by default. With them off, the output is identical to 1.0.0 in all 42 tested page × settings × device combinations.
- **JavaScript only when needed.** Interactive modes add one small inline script per block, with no dependencies. The *Text under the category name* mode and a list without panels use no JavaScript.
- **Live preview** in the administrator covers the new tab. The **+** button works inside the preview. The preview always shows the desktop mode.
- **Updating from 1.0.0 keeps all settings.** The block cache is refreshed automatically because the version is part of the cache key.
- Tested on Joomla 6.1.3, PHP 8.5 and Gridbox 2.20.3.1 in a real browser, at desktop and phone widths, with no JavaScript errors.

## 1.0.0 — 2026-09-25

Initial release.

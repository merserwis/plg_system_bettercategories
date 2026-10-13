# Changelog

All changes of **Better Categories for Gridbox**, newest first. Each version is also published as a [GitHub release](https://github.com/merserwis/plg_system_bettercategories/releases) with its installation package.

## 1.6.11 — 2026-10-13

### 🎛 Buttons on the left, new look really shown

- **On the left of the sorting row.** The grid / list switch and *Show all (N)* now stand at the start of the row of Gridbox's sorting menu — *Default* stays on the right — in this order: grid / list first, then *Show all*.
- **The new look reaches the site.** With Gridbox's *CSS minification* on, Gridbox joins the style sheets of a page into one file named after the list of files, not their content — so after an update it kept serving the previous `filters.css` (the grid / list buttons stayed small, their names squeezed). The update and every save of the settings now remove Gridbox's joined style sheets (as Gridbox does when its cache is cleared) and mark the pages kept by MerTools and Joomla's page cache as stale; Gridbox makes the joined files again, with the current styles, on the next page view.

## 1.6.10 — 2026-10-13

### 🎛 “Show all” and grid / list next to the sorting menu

- **One row with “Default”.** *Show all (N)* and the grid / list switch now stand right before Gridbox's sorting menu, in its row and on its line (when the list has a sorting menu; otherwise they stay in the bar above the list, as before). They take the menu's text size, weight, font and colour, so all three look alike.
- **Grid / list buttons with their names.** Each button shows its icon and its name (*Grid*, *List*) with room around them inside the frame, instead of a 36 px icon box. The names were meant to be hidden for screen readers only, but Gridbox templates do not have Joomla's `visually-hidden` class, so they showed squeezed into the buttons. On screens up to 420 px only the icons are shown.
- On phones the row wraps: the buttons above, the sorting menu below (Gridbox makes the menu as wide as its longest option).
- A category without filters no longer gets a *Filters* button on phones.

## 1.6.9 — 2026-10-13

### 🎯 One features filter for the whole store

- **Features every product has are not offered.** A feature all products of a category have narrows nothing, so it is no longer shown there (unless chosen) — it appears in the categories where only some products have it. One general filter such as *Application* can now serve every category: each shows only the features that tell its products apart. Option *Hide features every product has* in the *Filters* tab (on).

### 📥 Filters files that fit any site

- **Categories by address.** In a filters file (*Only add the filters of the file*) a filter may name its categories by their address instead of IDs: `"categories_path": ["srhvacr/hvacr/analizatory-spalin"]` (the whole path from the top category, or its end), and `"exclude_path"` the same way. Category IDs differ from site to site — a file made on a copy of the site gave the filters of one category to unrelated ones.
- A filter whose categories this site does not have is **left out** instead of being shown everywhere, and the import says which addresses were not found.
- **Place in the list.** A filter of the file can go right after another one: `"after": "producent"` (by *Name in the address*) — so a store-wide price or application filter lands next to the manufacturer instead of at the end.
- Long texts are no longer dropped: a value of a settings file may now have up to 30 000 bytes (it was 5 000 — a store-wide features list is longer, and such a filter was imported with no features).

## 1.6.8 — 2026-10-13

### ✨ Icons of categories and filter groups

- **Category icons.** The table *Own category images* is now *Own category images and icons*: next to the image of a category, an **icon** — chosen from the **Font Awesome Free** icons in a window with a search (by name and words, in English: *fire*, *gas*, *phone*) and the styles *solid*, *regular*, *brands*, or typed as classes of another icon font the site loads (e.g. `zmdi zmdi-flash`, `flaticon-…`) — and/or an **icon file** of the media manager (SVG, PNG, WebP).
- **Where they show:** in text lists before the name (*Icons in text lists*, on), in tiles (*Icons in tiles*): in the picture's place where a category has no picture (default), instead of the picture, small before the name, or off. *Icon size* (0 = automatic: the size of the text, 42 % of the tile) and *Icon colour* (empty = the text, in tiles the links).
- **Filter group icons.** Every filter has an *Icon* and an *Icon file*: the icon goes before the title of the group in the panel, in the accent colour of the filters.
- **No extra loading on the site.** A Font Awesome icon is put into the page as inline SVG in the colour of the text: no font or style sheet is loaded and only the icons used reach the visitor, so nothing clashes with the icon fonts of the template (Gridbox ships Font Awesome 5, Joomla 6 Font Awesome 6). Other icon classes are shown as `<i>` with the site's own font; a file as an image.
- The table of category images and icons takes the whole width of the form and one line per category (the media fields without the large preview).
- Font Awesome Free 6.7.2 (icons CC BY 4.0, the sprites in `media/plg_system_bettercategories/icons/fa` with their licence) — the same version as Joomla 6. SVG files: add `svg` to the legal extensions in the Media options of Joomla first.

### 🧭 Product list: grid / list and “Show all”

- **Grid / list switch** at the end of the bar above the products: the products as a grid (as Gridbox shows them) or as a list — one a row, the whole photo in a square on the left, the name, description and price beside it. The view changes at once without loading the page, the visitor's browser remembers it (also after filtering and on other categories), and it is set before the cards are painted (the small script is kept in place for Gridbox's deferred loading). *View at first*: grid or list.
- **“Show all (N)”** at the end of the bar when a category has more products than one page of the list: all of them on one page — with the filters and the order chosen, which also keep it while filtering — up to *“Show all”: at most* (default 300); **“Show in pages”** goes back. Such a page is not indexed (like a filtered one).
- Both also in categories **without filters** (the bar then holds only these buttons). Options in the *Filters* tab (*Grid / list switch*, *“Show all” button*, both on), texts in *Texts on the site*.

## 1.6.7 — 2026-10-13

### 🐞 Filters

- **Descriptions kept in product fields are read.** Technical parameters and features were read from the title, the introduction and the page of a product only. Shops whose product pages show the description from fields of the product (Gridbox app fields — on merserwis.pl *Features*, *Technical data* and *Equipment* are text areas) had most of the description left out: the flue gas analysers showed 5 boilers instead of 11, two gases instead of seven. Text area and text fields of the products are now read too.
- **The field found by its name, more tolerant.** A filter of a Gridbox field without a chosen field finds the store field by its name also when the name has a colon, an asterisk or other spaces (“Producent:”), and link fields of the *button* kind (label + address) count as link fields too.
- **A check from outside.** The filter panel names in `data-bcf-diag` the Gridbox field each field filter found (number, type, products with a value) — or that none was found, with the names of the store fields.

## 1.6.6 — 2026-10-13

### ✨ Filters

- **Link fields of Gridbox.** A filter of a Gridbox field can use a link field (label + address) — e.g. a *Manufacturer* field that links to the manufacturer's own page: every label is an option (alphabetical), its products are counted and filtered as with a list field. Until now such a field was not offered at all, so on merserwis.pl the manufacturer filter never appeared.
- **The field found by its name.** A filter of a Gridbox field with no field chosen — or with a field that does not exist on this site, e.g. from a settings file of another site — uses the store field whose name is the title of the filter (*Producent*).
- **A compact list of filters in the settings.** Every filter folds to one line with its summary — type, title, number of options (features), parameters, name in the address, the categories it is limited to or left out of; a click opens it. Above the list: *Open all*, *Fold all* and a search over the summaries (“gazy”, “Analizatory spalin”, “f-producent”). Rows start folded, a row added with **+** starts open; Joomla's buttons (add, remove, move) sit on the line of each row. 14 filters take one screen instead of fourteen.
- **Importing only filters: remove and first.** In a settings file imported with *Only add the filters of the file*, a row `{"key": "…", "remove": "1"}` removes the saved filter with that name in the address, and `"first": "1"` puts a new filter at the top of the list. The message after the import also counts the removed filters.

## 1.6.5 — 2026-10-13

### ✨ Filters

- **A phrase with a comma in double quotes.** The words of a feature are separated by commas, so a phrase like `CO 0-10,000ppm` (descriptions write thousands with a comma) fell apart into “CO 0-10” and “000ppm”, and “000ppm” then matched every “10,000ppm” of any product. Now `"CO 0-10,000ppm"` in double quotes is one phrase; everything else reads as before.

## 1.6.4 — 2026-10-13

### ✨ Filters

- **Not in categories** (new field of every filter): the filter never appears in these categories and their subcategories. A general filter of a parent category (e.g. temperature, pressure and concentration for the whole HVACR department) can now leave out a subcategory that has filters of its own — on merserwis.pl the *Flue gas analysers* showed “800 °C (2)”, “8000 ppm (1)”, “3 m (2)”, values that say nothing about these instruments.
- **Only add the filters of a file** (*Settings file* → tick box beside *Import from file…*): the filters of a settings file are added to the saved ones and everything else stays — a filter with the same *Name in the address* is replaced, the others are added at the end, filters are switched on. A file of ready filters for one category can be imported, corrected and imported again without touching the other filters.
- **Features: a star at the end of any word of a phrase.** `ciąg* komin*` finds *ciągu kominowego* and *ciąg kominowy*; before, a star counted only at the very end of the phrase.
- **Sub- and superscripts read as plain characters.** Descriptions write gas formulas as O<sub>2</sub>, CO<sub>2</sub>, NO<sub>2</sub>, SO<sub>2</sub>: they were read as “O 2”, “NO 2”, so a feature `NO2` never matched and a feature `NO` matched NO<sub>2</sub> as well. Now O<sub>2</sub> reads `O2` and m<sup>3</sup> `m3` (technical parameters unchanged: checked on the 17 flue gas analysers of merserwis.pl and the 636 products of the test shop).

## 1.6.3 — 2026-10-13

### ✨ Changes

- **Close goes to the Gridbox panel.** **Close**, **Save & Close** and **Save** then **Close** in the Better Categories settings now go to the Gridbox panel instead of the list of plugins, as in MerTools (when Gridbox is installed; otherwise the list of plugins as before).

## 1.6.2 — 2026-10-12

### 🧱 Product cards in even rows (tab *Layout*, on by default)

- With Gridbox’s **Masonry** layout every product card goes right under the one above it: a shorter title or description moved the whole column up, so the rows of a category did not line up and the prices stood at different heights.
- Now the cards of a row start together and are as tall as the tallest one, and the price sits at the bottom of every card — on one line across the row. The description takes the free space, so nothing is cut or stretched.
- Works with the Masonry and Grid layouts of the product list, on every page of the list (also the next pages); phones with one column look as before. Gridbox’s files are not changed — a small style in the page head. Switch: *Layout → Product cards in even rows*.
- Checked on the merserwis.pl category *Analizatory spalin*: the rows that were shifted by 20 px start together, all prices of a row on one line.

## 1.6.1 — 2026-10-07

### 🌍 Languages

- The extension now ships **English (default), German, Polish, French, Czech and Dutch** — the settings follow the language of the Joomla administrator, the filter panel and the texts on the site the language of the site; any other language shows English, also text by text where a translation lacks one.
- **French, Czech and Dutch** translated in full (plugin settings, module, tools, filter panel).
- **German and Polish** reviewed against the English texts and corrected.
- **Ukrainian removed**; its files left by earlier versions are removed on update, so those sites show English.

## 1.6.0 — 2026-10-05

### 🔎 Many technical parameters in one filter

- *Technical parameter* now takes **any number of parameters**, ticked in a list with **Select all / Clear all**. On the site every ticked parameter is a filter of its own, shown only in categories whose products have it — so one row serves every store page. Filters of earlier versions keep working as they are.
- **Minimum products with the parameter** (default 2): a parameter mentioned by a single product of the category (often in passing) is not offered.
- **Only in categories** — any filter can be limited to chosen categories and their subcategories, e.g. humidity, pressure and air velocity only under HVACR, voltage and measurement category only under meters. Empty = everywhere.

### 🌡️ HVACR parameters

- New kinds read from the descriptions: **relative humidity** (%RH), **air velocity** (m/s), **flow** (m³/h; l/min and l/h converted), **concentration** (ppm) and **irradiance** (W/m²).
- **Pressure** in any unit is one parameter: Pa, hPa, kPa, MPa, mbar, bar and psi (shown as Pa, kPa or bar, as usual in HVAC). The former *Pressure (bar)* filters are now part of it.
- **°F** is converted to °C, so “-4…122 °F” and “-20…50 °C” are the same range.
- From–to fields of a parameter also take a unit: “5 bar”, “2 l/min”, “68 °F”.

---

**Tested on:** the 636-product test store: an HVACR category gets temperature, pressure, humidity, air velocity and flow, a meters category voltage, current, CAT and IP — from one configuration with category limits; filters by humidity and by a pressure range typed in bar. The list of subcategories identical to 1.4.1 in all 42 combinations; no PHP warnings.

## 1.5.2 — 2026-10-05

### 🐞 Fixes

- **Technical parameters no longer read numbers of standards and model names as values.** “Tests to PN-EN 62446 up to 1000 V DC” gave the voltage range 1000…62 446 V (shown as *62,446 kV*): the number of the standard and the next value were taken for a range. Now skipped:
  - designations of standards — PN-EN, PN-HD, EN, IEC, ISO, DIN, VDE, BS, UL… (a nominal pressure such as “PN 16 bar” stays a value);
  - numbers glued to a model name with a hyphen — “C-4A”, “APS-1102A”, “M10-522-10 260V” (“DC-150 kHz” still reads as a band up to 150 kHz);
  - a “range” written from a larger number down to a smaller positive one without a unit after the first (“5 700~1000 A” in a table); ranges falling to negative values stay (“0 ~ -32 V”, “+25 to -100 °C”).
- On the 636-product test store this removes wrong values from 45 products (e.g. 4 MΩ from “IEC61557-4 MΩ”, 50.26 kV from “M10-522-50 260V”, 1102 A from “APS-1102A”) and recovers a range the standard number had hidden (110 A…199.9 kA).
- The parameters of all products are read again after the update (automatically, on the next visit of each category). Filter addresses with a value that no longer exists show the unfiltered category.

## 1.5.1 — 2026-10-05

### 🐞 Fixes

- **Filters did nothing on stores with the “Default” product order.** When the Gridbox product list is set to its custom order (*Default* in the sorting menu), choosing a filter showed the unfiltered category instead of the matching products. The plugin asked the database for the order in a form that both products and categories have, the query failed and the plugin gave up. The order is now handed over exactly as Gridbox does it, and filtered lists keep Gridbox's custom order.
- **The side panel opened under the header.** On sites with a sticky header the top of the panel (and its title) was hidden behind the menu. The open panel is now placed above everything on the page.
- The settings of the product list element are also found when the list is part of a Gridbox **global item** or the store uses Gridbox's default layout.

### ✨ Changes

- **New panel position: in the side column next to the products** (at the top or at the bottom of it) — the column beside the product list in the same Gridbox row, e.g. with the category tree or the product advisers. Found automatically; on phones the *Filters* button is used as before.
- **The plugin's own log file:** errors go to `administrator/logs/plg_system_bettercategories.php`, and the **Filters** tab shows the latest lines, so a problem can be seen without access to the server.
- **The diagnostic HTML comment was removed** (the *Diagnostic comment* setting is gone).

---

**Tested on:** Joomla 6.1.3, PHP 8.5, Gridbox 2.20.3.1, the 636-product test store with the list set to the custom order, as on merserwis.pl: the problem reproduced and fixed; filtered lists in Gridbox's order, sorting and pages; both side-column positions; the side panel on a phone. No PHP warnings from the extension; the list of subcategories unchanged.

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

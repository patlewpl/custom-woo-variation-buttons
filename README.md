Custom WooCommerce Variation Buttons
====================================

Renders a WooCommerce variable product anywhere on the site: chosen attributes
as selects, the rest as buttons, with an AJAX add-to-cart.

Note: every string the store or the editor sees — the widget name, the Elementor
panel labels and the customer-facing messages — is Polish by design and
hardcoded, not translatable. This document is in English and names those
controls by function and by their position in the panel, not by their on-screen
label.

Requirements
------------
WordPress 6.5+, PHP 7.4+, WooCommerce.

Installation
------------
1. Upload the folder `custom-woo-variation-buttons` to:
   wp-content/plugins/
2. Activate the plugin in WordPress.
3. Add the shortcode to an Elementor Shortcode widget.

Basic usage
------------
[woo_variations_buttons product_id="123"]

Recommended usage
-----------------
[woo_variations_buttons product_id="123" select_attribute="pa_size"]

Optional attributes
-------------------
product_id       Required WooCommerce variable product ID.
select_attribute Attribute(s) to render as a select; the rest become buttons.
                 Comma separated for more than one. If omitted, the first
                 product attribute is used. Unknown names are ignored.
quantity         Initial quantity. Default: 1.
button_text      Add-to-cart button text. Defaults to the built-in Polish label
                 (see the defaults in `CWVB_Shortcode::build()`).
show_quantity    Show the quantity field. "no" hides it and sends `quantity`
                 to the cart instead. Default: "yes".
price_prefix     Text shown to the left of the price, e.g. a "Price:" label. It
                 appears only once a variation resolves. Default: empty.
step_numbers     Number the attributes, starting at 1. Default: "yes".
step_format      Number format, {n} = the number, e.g. "Step {n}:".
                 Default: "{n}.".
benefits_title   Heading of the package block shown between the attributes and
                 the price. Default: empty.
benefits         The lines of that block, separated by "|" (or by newlines).
                 Default: empty.
benefits_marker  Character in front of every line. Default: "✓". Empty = none.
benefits_note    Small print under the list. Default: empty.

The package block is static — it does not change with the selected variation,
and it is shown before anything is picked. Nothing is output at all when the
title, the list and the note are all empty. In the Elementor widget these four
controls come prefilled with the store's own Polish copy; through the shortcode
they start empty.

    [woo_variations_buttons product_id="123"
      benefits_title="What you get in the selected package:"
      benefits="20 online 1:1 lessons|A personal study plan|Materials and tutor support"
      benefits_note="Platform access depends on the language and course chosen."]

Example
-------
[woo_variations_buttons product_id="123" select_attribute="pa_size" button_text="Buy now"]

Two selects
-----------
[woo_variations_buttons product_id="123" select_attribute="pa_size,pa_date"]

Selects are rendered in the product's own attribute order, not in the order
listed here. Because picking an attribute resets every attribute below it,
put the attribute that narrows the choice the most first on the product.

Structure
---------
    custom-woo-variation-buttons.php        bootstrap: header, constants, requires
    includes/
      class-custom-woo-variation-buttons.php  hook wiring
      class-cwvb-assets.php                   CSS/JS registration and enqueue
      class-cwvb-attributes.php               value normalisation, labels, select vs buttons
      class-cwvb-product-data.php             reads variations from WooCommerce + cache
      class-cwvb-cart.php                     the add-to-cart endpoint
      class-cwvb-shortcode.php                shortcode, guards, error logging
      class-cwvb-elementor.php                Elementor widget registration (lazy)
      class-cwvb-updater.php                  updates from GitHub releases
      widgets/
        class-cwvb-elementor-widget.php       the widget and its style controls
    templates/
      variation-buttons.php                   markup (overridable from the theme)
    assets/
      variations-buttons.css
      variations-buttons.js
    .distignore                             files kept out of the release zip
    .github/workflows/release.yml           tag -> build zip -> GitHub release

Constants: `CWVB_VERSION`, `CWVB_FILE`, `CWVB_PATH`, `CWVB_URL`.

Bump the version in the `Version:` header of the main file **only** —
`CWVB_VERSION` is read back from it with `get_file_data()`. Changing the version
invalidates the variation cache, because the constant is part of the transient
key. The release workflow refuses to build if the git tag and this header
disagree.

Releasing an update
-------------------
Installed sites are told about new versions by the plugin itself, from GitHub
releases. Shipping one is three commands:

    # 1. bump "Version: 1.4.0" in custom-woo-variation-buttons.php
    git commit -am "Release 1.4.0"
    git tag v1.4.0
    git push && git push --tags

Pushing the tag runs `.github/workflows/release.yml`, which

1. fails the build if the tag and the `Version:` header disagree,
2. builds `custom-woo-variation-buttons.zip` (everything in `.distignore` left
   out, unpacking to a folder named exactly like the installed plugin),
3. publishes the GitHub release with auto-generated notes and the zip attached.

Sites then show "Update available" within about a day, or immediately after
Dashboard → Updates → **Check again**. The release notes become the changelog in
the "View version details" modal.

The repository has to stay **public**: the update check runs unauthenticated
from each customer's server, with no token anywhere. Mark a release as a
pre-release to test a tag without offering it to anyone — `/releases/latest`
skips drafts and pre-releases.

How the update check works
--------------------------
`class-cwvb-updater.php`, no third-party library:

    Update URI: https://github.com/patlewpl/custom-woo-variation-buttons

That header (WP 5.8+) makes WordPress skip wordpress.org for this plugin — so
nobody can hijack it by publishing the same slug there — and fire
`update_plugins_github.com` during its own twice-daily update check. The class
answers with the latest release, and:

* caches the GitHub response for 6 hours, and a failed lookup for 30 minutes, so
  an outage or a rate limit never puts an HTTP timeout in front of wp-admin;
* returns its payload even when the release is *not* newer, which is what makes
  the per-site auto-update toggle available;
* prefers the zip attached to the release, falling back to GitHub's generated
  source zip, in which case `upgrader_source_selection` renames the unpacked
  `owner-repo-<commit>/` folder back to the plugin slug — without that WordPress
  would install a second copy and deactivate the plugin;
* never throws or prints. A broken release means no update offered, nothing else.

To move the plugin to a different repo, change `CWVB_Updater::REPO` and the
`Update URI` header together; `HOST` has to match the header's hostname, because
WordPress builds the filter name from it.

Template override
-----------------
Copy `templates/variation-buttons.php` into your theme:

    yourtheme/custom-wvb/variation-buttons.php

The template receives everything in a single `$cwvb` array (`instance_id`,
`attributes`, `variations`, `order`, `quantity`, `button_text`, `show_quantity`,
`price_prefix`, `step_format`, `benefits_title`, `benefits`, `benefits_marker`,
`benefits_note`, `config`). Each entry in `attributes` carries its 1 based `step`
number, and `benefits` is a plain array of strings whatever it was configured as.
The path can also be swapped with a filter:

    add_filter( 'custom_wvb_template', function ( $path, $cwvb ) {
        return $path;
    }, 10, 2 );

Elementor widget
----------------
Besides the shortcode, the plugin registers an Elementor widget in the General
category, listed under a Polish name and findable by searching for "variation",
"woocommerce" or "cart". It renders through the exact same code path as the
shortcode, so both stay in sync.

Content tab, in panel order:

1. Product — picker for up to 200 published variable products, attributes to
   render as selects, button text, text before the price, attribute numbering
   and its format, quantity field on/off, initial quantity.
2. Package contents — heading, a repeater for the list, the marker character,
   and the small print under it.

Style tab, in panel order: Layout, Package contents, Attribute labels, Option
buttons (normal / hover / selected / unavailable), Select (normal / selected /
option list), Price (including the text before it), Messages, Quantity, Cart
button.

The package style section covers the box (spacing above and below, padding,
background, border, corner radius, shadow) and then each part separately:
heading typography, colour and the gap under it; list typography and colour;
marker colour and its own typography, so the marker can be larger than the line
it sits on; the gap between lines and the gap after the marker; note typography,
colour and the gap above it. Every one of those writes to a variable or a
selector of its own, so restyling this block never touches the attribute labels,
the buttons or the select. The one thing not exposed is text alignment — the
list is a flex row per line, and left alignment is the only sensible one; centre
it with `.custom-wvb__benefit { justify-content: center; }` if you ever need to.

Select styling
--------------
A native `<select>` opens a list drawn by the operating system. macOS, iOS and
Safari ignore CSS on `option` completely; Windows Chrome/Edge and Firefox honour
it. So the selected state is styled on the **closed** field instead: the script
adds `.is-selected` to a select that has a value. That is the second tab of the
Select style section, and it works in every browser. The third tab writes to
`option:checked` and says so in the panel — treat it as a bonus, not as the
design. A dropdown styled identically everywhere needs a custom listbox, not a
`<select>`.

Requires Elementor 3.5+ (`elementor/widgets/register`). Without Elementor
nothing is loaded and the shortcode is unaffected.

Styling
-------
Every value in the stylesheet is a CSS custom property set on `.custom-wvb`,
so styling means setting variables, not overriding rules. Defaults chain into
Elementor's global kit variables:

    --cwvb-accent      var(--e-global-color-primary, #222)
    --cwvb-text        var(--e-global-color-text, #222)
    --cwvb-muted       var(--e-global-color-secondary, #666)
    --cwvb-font-family var(--e-global-typography-text-font-family, inherit)

Which means changing the palette or typography in Elementor > Site Settings
restyles the widget with no CSS at all.

Full list: `--cwvb-accent`, `--cwvb-accent-contrast`, `--cwvb-text`,
`--cwvb-muted`, `--cwvb-surface`, `--cwvb-border`, `--cwvb-border-hover`,
`--cwvb-font-family`, `--cwvb-font-size`, `--cwvb-label-weight`,
`--cwvb-label-spacing`, `--cwvb-price-size`, `--cwvb-price-gap`,
`--cwvb-message-size`, `--cwvb-benefits-spacing-top`,
`--cwvb-benefits-spacing`, `--cwvb-benefits-gap`, `--cwvb-benefits-marker-gap`,
`--cwvb-benefits-marker-color`, `--cwvb-benefits-title-spacing`,
`--cwvb-benefits-note-spacing`,
`--cwvb-radius`, `--cwvb-gap`, `--cwvb-attribute-spacing`,
`--cwvb-field-height`, `--cwvb-option-padding`, `--cwvb-border-width`,
`--cwvb-transition`, `--cwvb-disabled-opacity`.

Override everywhere, or per instance:

    .custom-wvb   { --cwvb-radius: 0; }
    #custom-wvb-3 { --cwvb-accent: #c00; }

Class map for anything the variables do not cover:

    .custom-wvb                     wrapper (also #custom-wvb-N)
    .custom-wvb__attribute          one attribute group
                                    [data-attribute-type="select|buttons"]
                                    [data-attribute-name="attribute_pa_x"]
    .custom-wvb__label              attribute label
    .custom-wvb__step               step number inside the label
    .custom-wvb__select             the select (.is-selected once it has a value)
    .custom-wvb__options            button container
    .custom-wvb__option             option button (.is-selected, :disabled)
    .custom-wvb__benefits           package block between attributes and price
    .custom-wvb__benefits-title     its heading
    .custom-wvb__benefits-list      the <ul>
    .custom-wvb__benefit            one line
    .custom-wvb__benefit-marker     the check mark (aria-hidden)
    .custom-wvb__benefit-text       the line's text
    .custom-wvb__benefits-note      small print under the list
    .custom-wvb__price-row          price line ([hidden] until a variation resolves)
    .custom-wvb__price-prefix       text left of the price
    .custom-wvb__price              price
    .custom-wvb__message            validation message
    .custom-wvb__quantity           quantity wrapper (absent when hidden)
    .custom-wvb__qty                quantity input
    .custom-wvb__add                add-to-cart button (:disabled)

Assets are registered under the handle `custom-woo-variation-buttons`, versioned
with `CWVB_VERSION` — releasing a new version is what busts the browser cache.
On a `WP_DEBUG` install the file's `filemtime()` is used instead, so editing CSS
locally still shows up; production pays no `stat()` call for it.

To load your own CSS after the plugin's:

    wp_enqueue_style( 'my-styles', $url, array( 'custom-woo-variation-buttons' ), $ver );

Cache
-----
Variation data is cached in a transient, because `get_available_variations()`
loads every variation object on each render. The key includes WooCommerce's own
product transient version, so saving a product or variation (including stock
changes) invalidates it automatically. Currency and the shop tax display setting
are part of the key as well.

TTL is 1 day; change or disable it with:

    add_filter( 'custom_wvb_cache_ttl', function ( $ttl, $product ) {
        return 0; // 0 = no caching
    }, 10, 2 );

If your shop calculates VAT per customer (B2B / EU VAT plugins), set the TTL to
0 — price_html would otherwise be shared between customers.

Inside a single request the payload is memoised per product as well, so two
widgets for the same product (or a widget next to a shortcode) cost one lookup.
That holds with the TTL filtered to 0 too: currency, tax display and stock
cannot change mid-request.

Adding to the cart
------------------
The widget posts to its own endpoint, `?wc-ajax=cwvb_add_to_cart`, rather than
to WooCommerce's `add_to_cart`, because that one reads `product_id` and
`quantity` and nothing else. Handed a variation ID it rebuilds the attributes
with `WC_Product_Variation::get_variation_attributes()`, which returns an empty
string for every attribute the variation leaves as **Any** — and
`WC_Cart::add_to_cart()` then refuses the item with "<attribute> is a required
field". The customer's choice had no parameter to travel in.

So this endpoint posts the parent product, the variation, the quantity and the
`attribute_*` values the customer actually picked, which is what the cart wants.
It checks the variation really belongs to that product, drops any key that is
not an `attribute_*` one, and leaves value validation to `WC_Cart`, which
compares each one against the parent's own list and words the error better than
this plugin could. That error is returned to the browser and shown under the
price, instead of a generic failure message.

On success it returns exactly what WooCommerce's own refresh returns, by calling
`WC_AJAX::get_refreshed_fragments()`. That matters: `wc-cart-fragments.js`
caches whatever arrives with the `added_to_cart` event in `sessionStorage` and
re-applies it on every later page load, looking for
`div.widget_shopping_cart_content`. Returning a fragment set without it leaves
themes and the Elementor menu cart working from a mini cart that never
refreshes.

The handler also buffers its own output, because cart templates and third-party
callbacks print; anything they emit would otherwise sit in front of the JSON and
leave the browser unable to parse the response.

The script hands the add-to-cart button to the `added_to_cart` event as a jQuery
object, which is what WooCommerce's own listener expects — a bare DOM element
throws there and aborts every later listener on the event, the mini cart
included. WooCommerce then appends its "view cart" link after the button; the
stylesheet hides it with `.custom-wvb .added_to_cart { display: none }`.

There is no nonce, deliberately: the markup can be served from a full-page
cache, and a cached nonce is a broken nonce. WooCommerce omits it on its own
add-to-cart endpoint for the same reason, and the action only ever writes to the
caller's own cart.

Error handling
--------------
The shortcode never lets an error escape: a fatal inside a shortcode would take
down the whole page render, so everything runs inside try/catch. Visitors get
nothing, users who can `edit_posts` get a short notice, and the error goes to the
WooCommerce log (WooCommerce → Status → Logs) or the PHP error log.
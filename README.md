Custom WooCommerce Variation Buttons
====================================

Renders a WooCommerce variable product anywhere on the site: chosen attributes
as selects, the rest as buttons, with an AJAX add-to-cart.

Note: all user-facing strings are Polish by design and are hardcoded, not
translatable.

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
[woo_variations_buttons product_id="123" select_attribute="pa_egzamin"]

Optional attributes
-------------------
product_id       Required WooCommerce variable product ID.
select_attribute Attribute(s) to render as a select; the rest become buttons.
                 Comma separated for more than one. If omitted, the first
                 product attribute is used. Unknown names are ignored.
quantity         Initial quantity. Default: 1.
button_text      Add-to-cart button text. Default: "Dodaj do koszyka".

Example
-------
[woo_variations_buttons product_id="123" select_attribute="pa_egzamin" button_text="Dodaj do koszyka"]

Two selects
-----------
[woo_variations_buttons product_id="123" select_attribute="pa_egzamin,pa_termin"]

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
      class-cwvb-shortcode.php                shortcode, guards, error logging
      class-cwvb-elementor.php                Elementor widget registration (lazy)
      widgets/
        class-cwvb-elementor-widget.php       the widget and its style controls
    templates/
      variation-buttons.php                   markup (overridable from the theme)
    assets/
      variations-buttons.css
      variations-buttons.js

Constants: `CWVB_VERSION`, `CWVB_FILE`, `CWVB_PATH`, `CWVB_URL`.

Bump the version in the `Version:` header of the main file **only** —
`CWVB_VERSION` is read back from it with `get_file_data()`. Changing the version
invalidates the variation cache, because the constant is part of the transient
key.

Template override
-----------------
Copy `templates/variation-buttons.php` into your theme:

    yourtheme/custom-wvb/variation-buttons.php

The template receives everything in a single `$cwvb` array (`instance_id`,
`attributes`, `variations`, `order`, `quantity`, `button_text`, `config`).
The path can also be swapped with a filter:

    add_filter( 'custom_wvb_template', function ( $path, $cwvb ) {
        return $path;
    }, 10, 2 );

Elementor widget
----------------
Besides the shortcode, the plugin registers an Elementor widget: **Warianty
produktu (przyciski)** (General category). It renders through the exact same
code path as the shortcode, so both stay in sync.

Content tab: product picker (up to 200 published variable products), attributes
to render as selects, button text, initial quantity.

Style tab: Layout, Attribute labels, Option buttons (normal / hover / selected /
unavailable), Select, Price, Messages, Quantity, Cart button.

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
`--cwvb-label-spacing`, `--cwvb-price-size`, `--cwvb-message-size`,
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
    .custom-wvb__select             the select
    .custom-wvb__options            button container
    .custom-wvb__option             option button (.is-selected, :disabled)
    .custom-wvb__price              price
    .custom-wvb__message            validation message
    .custom-wvb__quantity           quantity wrapper
    .custom-wvb__qty                quantity input
    .custom-wvb__add                add-to-cart button (:disabled)

Assets are registered under the handle `custom-woo-variation-buttons`.
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

Error handling
--------------
The shortcode never lets an error escape: a fatal inside a shortcode would take
down the whole page render, so everything runs inside try/catch. Visitors get
nothing, users who can `edit_posts` get a short notice, and the error goes to the
WooCommerce log (WooCommerce → Status → Logs) or the PHP error log.
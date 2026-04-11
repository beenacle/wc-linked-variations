=== WC Linked Variations ===
Contributors: beenacle
Tags: woocommerce, linked products, variations, product linking, swatches
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
WC requires at least: 6.0
WC tested up to: 9.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Link separate WooCommerce products together by shared attributes and display them as variable-product-style switchers on product pages.

== Description ==

WC Linked Variations lets you group individual WooCommerce products and present them on product pages as if they were variations of the same product. Customers see buttons, dropdowns, or image thumbnails to switch between linked products — just like a variable product.

**Key features:**

* Link products manually or automatically via taxonomy (category, tag, or custom taxonomy)
* Link by any WooCommerce product attribute (color, size, material, etc.)
* Three display styles: buttons, dropdowns, image thumbnails
* Multi-attribute support (e.g. Color + Size simultaneously)
* Out-of-stock products shown as faded/disabled
* Shortcode support for page builders: `[wclv_links]`
* Extensible with action hooks and filters

== Installation ==

1. Upload the `wc-linked-variations` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu in WordPress
3. Go to Products > Linked Variations to create your first group

== Frequently Asked Questions ==

= What product types does this work with? =
The plugin works with any WooCommerce product type — simple, external, grouped, etc.

= Can I link products by multiple attributes? =
Yes. When creating a group, select multiple attributes and the plugin will render one row per attribute on the product page.

= Does it work with page builders? =
Yes. Use the `[wclv_links]` shortcode inside any page builder element.

== Changelog ==

= 1.0.0 =
* Initial release

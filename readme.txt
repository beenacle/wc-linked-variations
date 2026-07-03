=== WC Linked Variations ===
Contributors: beenacle
Tags: woocommerce, linked products, variations, product linking, swatches
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
WC requires at least: 6.0
WC tested up to: 10.6.2
Stable tag: 1.2.4
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

= 1.2.4 =
* Fix: variation selectors now respect custom term ordering created by third-party term-ordering plugins (e.g. Post Types Order / Taxonomy Terms Order), which store the position under the generic `order` term meta key. Term ordering is read from WooCommerce's native `order_{taxonomy}` key first and falls back to `order`, so both native and plugin-driven ordering are honoured
* Fix: permanently deleting a linked-variation group now removes its stored data instead of leaving an orphaned row (wired to `before_delete_post`; trashing is unaffected so restores stay lossless)

= 1.2.3 =
* Variation selectors now respect WooCommerce's custom term ordering (menu order) instead of product order; can be toggled with the `wclv_respect_menu_order` filter
* WordPress-native hardening: explicit text-domain loading on init, input unslashing at the save/import boundaries, `wp_kses_post()` on the filtered option markup, translator comments, Select2 stylesheet enqueued on the group screen, non-autoloaded dismissal flag, and complete option cleanup on uninstall

= 1.2.2 =
* Product selection: the admin picker can now link draft, pending, scheduled and private products, not just published ones — each non-published product is labelled with its status so it's clear what you're selecting
* Storefront: linked products are now shown only while published, keeping unpublished products out of the switcher (also fixes a previously-published product that was later unpublished still appearing to visitors)
* Added `wclv_search_product_statuses` and `wclv_is_product_displayable` filters

= 1.2.1 =
* In-plugin self-updater: install updates straight from GitHub Releases via Dashboard → Updates

= 1.2.0 =
* Add automated GitHub Releases build-and-publish workflow

= 1.1.1 =
* Move Iconic import tool from sidebar menu to auto-detecting admin notice
* Update tested compatibility to WordPress 6.9 and WooCommerce 10.6.2

= 1.1.0 =
* Add import tool for Iconic WooCommerce Linked Variations (Products > Import from Iconic)
* Declare HPOS compatibility with WooCommerce
* Add GitHub auto-update support

= 1.0.0 =
* Initial release

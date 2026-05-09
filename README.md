# Woo-Poly Integration (Modernized Fork)

> **This is a fork of the archived [hyyan/woo-poly-integration](https://github.com/hyyan/woo-poly-integration) plugin (last upstream release 2021).**
> Maintained by IntegrIT Solutions to bring HPOS, Cart/Checkout block, Polylang 3.7+ options-object, and WordPress 6.6+ / WooCommerce 9.0+ / PHP 7.4+ compatibility back to the codebase.
>
> Upstream is unmaintained; this fork tracks current versions of WP, WC, and Polylang Free.

## Compatibility (v2.0.0-alpha.1)

| Component | Minimum | Tested up to |
|---|---|---|
| WordPress | 6.6 | 6.9 |
| WooCommerce | 9.0 | 10.7 |
| Polylang Free | 3.4.5 | 3.8 |
| PHP | 7.4 | 8.3 |

**HPOS** (High-Performance Order Storage) and **Cart/Checkout block** compatibility are both declared `true` since v2.0.0-alpha.1. Order language storage is dual-written to (a) the WooCommerce order CRUD meta `_hyyan_wpi_language` (HPOS-native) and (b) Polylang's `language` taxonomy. Block cart line items are translated at session-load time via `woocommerce_get_cart_item_from_session` so the translated product flows through Store API responses naturally.

---

[This plugin](https://github.com/IntegrITSolutions/woo-poly-integration) makes it possible to run multilingual e-commerce sites using
WooCommerce and Polylang. It makes products and store pages translatable, lets
visitors switch languages and order products in their language — all from
the same interface you love.

[Read the full docs](https://github.com/hyyan/woo-poly-integration/wiki)

## Features

- [x] Auto Download Woocommerce Translation Files
- [x] Page Translation
- [x] Endpoints Translation
- [x] Product Translation
  - [x] Categories
  - [x] Tags
  - [x] Attributes
  - [x] Shipping Classes
  - [x] Meta Synchronization
  - [x] Variation Product
  - [x] Product Gallery
- [x] Order Translation
- [x] Stock Synchronization
- [x] Cart Synchronization
- [x] Coupon Synchronization
- [x] Emails
- [x] Reports
  - [x] Filter by language
  - [x] Combine reports for all languages


## What you need to know about this plugin

1. Requires PHP 7.4 or higher (Polylang 3.8 and WooCommerce 10.7 also require 7.4+).
2. Developed in sync with [Polylang Free](https://wordpress.org/plugins/polylang)
   and [WooCommerce](https://wordpress.org/plugins/woocommerce/) latest versions. Polylang Pro is supported but not required (no `polylang` entry in `Requires Plugins` header so Pro users aren't blocked from activation).
3. Variable products are supported, but using them will `disallow you to
   change the default language`, because of how the plugin implements this
   support. Choose the default language before adding any variable products.
4. Polylang URL modification mode `The language is set from content` is not
   supported (deprecated for new installs in Polylang 3.7+ anyway).

## How to install

### Classical way

1. Download the plugin as zip archive and then upload it to your wordpress plugins folder and
extract it there.
2. Activate the plugin from your admin panel

### Composer way

1. run composer command : ``` composer require hyyan/woo-poly-integration```

In all cases please do ensure you have Polylang and WooCommerce activated and setup
before you Activate this plugin from your admin panel

Please note the getting started notes:
https://github.com/hyyan/woo-poly-integration/wiki/Getting-Started


## Setup your environment

1. You need to translate woocommerce pages by yourself
2. The plugin will handle the rest for you

## Translations

* Arabic by [Hyyan Abo Fakher](https://github.com/hyyan)
* Spanish by [nunhes](https://github.com/nunhes)

## Contributing

Everyone is welcome to help contribute and improve this plugin. There are several
ways you can contribute:

* Reporting issues (please read [issue guidelines](https://github.com/necolas/issue-guidelines))
* Suggesting new features
* Writing or refactoring code
* Fixing [issues](https://github.com/hyyan/woo-poly-integration/issues)

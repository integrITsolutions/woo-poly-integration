/*
 * This file is part of the woo-poly-integration plugin.
 * Original (c) Hyyan Abo Fakher <hyyanaf@gmail.com>
 * Modernized fork (c) IntegrIT Solutions
 *
 * Polylang language-change detection on top of WooCommerce's native cart fragments.
 *
 * v1.x of this plugin shipped a forked clone of wc-cart-fragments.js. That has been
 * removed: we now depend on WooCommerce's own wc-cart-fragments and only add the
 * language-switch detection layer on top.
 *
 * Triggers `wc_fragment_refresh` whenever the `pll_language` cookie value differs
 * from the value previously stored in sessionStorage. This forces the cart widget
 * (mini-cart) to repaint after a language switch.
 *
 * No external cookie library required — uses document.cookie directly.
 */
(function () {
	'use strict';

	function readCookie(name) {
		var prefix = name + '=';
		var parts = document.cookie ? document.cookie.split(';') : [];
		for (var i = 0; i < parts.length; i++) {
			var c = parts[i];
			while (c.charAt(0) === ' ') {
				c = c.substring(1);
			}
			if (c.indexOf(prefix) === 0) {
				try {
					return decodeURIComponent(c.substring(prefix.length));
				} catch (e) {
					return '';
				}
			}
		}
		return '';
	}

	function supportsStorage() {
		try {
			window.sessionStorage.setItem('wpi_test', '1');
			window.sessionStorage.removeItem('wpi_test');
			return true;
		} catch (e) {
			return false;
		}
	}

	function checkLanguageChange() {
		if (!supportsStorage()) {
			return;
		}
		var current = readCookie('pll_language');
		var previous = window.sessionStorage.getItem('pll_language') || '';

		if (current && current !== previous) {
			window.sessionStorage.setItem('pll_language', current);
			if (window.jQuery) {
				window.jQuery(document.body).trigger('wc_fragment_refresh');
			}
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', checkLanguageChange);
	} else {
		checkLanguageChange();
	}
})();

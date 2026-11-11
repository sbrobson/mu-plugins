<?php

/**
 * Plugin Name: Meta for WooCommerce tweaks
 * Description: Respects the cookie banner's Decline for Meta's server-side events, skips shop staff, and keeps collection-only products out of the Meta catalogue. See docs/marketing/ads-setup.md.
 */

defined('ABSPATH') || exit;

function cc_meta_declined(): bool
{
	return 'deny' === ($_COOKIE['cmplz_marketing'] ?? '');
}

/**
 * Whether this request's HTML is never cached (Cloudflare/Varnish/Breeze), so it may differ per visitor.
 */
function cc_meta_request_is_uncached(): bool
{
	if ('GET' !== ($_SERVER['REQUEST_METHOD'] ?? 'GET') || isset($_GET['wc-ajax']) || wp_doing_ajax()) {
		return true;
	}

	$path = trim((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

	if (str_starts_with($path, 'wp-json')) {
		return true;
	}

	foreach (['cart', 'checkout', 'myaccount'] as $page) {
		$uri = get_page_uri((int) get_option("woocommerce_{$page}_page_id"));

		if ($uri && ($path === $uri || str_starts_with($path, $uri . '/'))) {
			return true;
		}
	}

	return false;
}

/**
 * The filter switches off the pixel and the Conversions API together. Complianz already blocks the
 * pixel in the browser after Decline; this stops the server-side events (add to cart, checkout,
 * purchase) too. Only on uncached requests: a cached page without the pixel would be served to
 * everyone. Cached pages are covered by the two hooks below.
 */
add_filter('facebook_for_woocommerce_integration_pixel_enabled', function ($enabled) {
	if (current_user_can('manage_woocommerce')) {
		return false;
	}

	if (cc_meta_declined() && cc_meta_request_is_uncached()) {
		return false;
	}

	return $enabled;
});

/**
 * On any request (cached pages too), send no server-side events for visitors who declined, e.g. the
 * PageView/ViewContent a product page sends on a cache miss. The plugin checks this filter before
 * every send, and it doesn't change the page HTML.
 */
add_filter('wc_facebook_is_crawler_request', fn($is_crawler) => $is_crawler || cc_meta_declined());

/**
 * The plugin also sets Meta's _fbp/_fbc cookies from the server; drop them for visitors who declined.
 * header_remove() can't target one cookie, so the other Set-Cookie headers are re-added.
 */
add_action('template_redirect', function () {
	if (! cc_meta_declined() || headers_sent()) {
		return;
	}

	$cookies = array_filter(headers_list(), fn($h) => 0 === stripos($h, 'Set-Cookie:'));
	$keep    = array_filter($cookies, fn($h) => ! preg_match('/^Set-Cookie:\s*_fb[pc]=/i', $h));

	if (count($keep) === count($cookies)) {
		return;
	}

	header_remove('Set-Cookie');

	foreach ($keep as $header) {
		header($header, false);
	}
}, PHP_INT_MAX);

/** Collection-only products (e.g. Wonky Bottles) can't be ordered from an ad. */
add_filter('wc_facebook_should_sync_product', function ($sync, $product) {
	if ($product instanceof WC_Product_Variation) {
		$product = wc_get_product($product->get_parent_id()) ?: $product;
	}

	return $sync && ! ($product instanceof WC_Product && 'collection-only' === $product->get_shipping_class());
}, 10, 2);

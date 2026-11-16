<?php

/**
 * Plugin Name: Bought Product Cache Fix
 * Description: Clears a customer's cached "bought this product" answer when one of their orders changes status, so a paid order unlocks reviews straight away.
 */

defined('ABSPATH') || exit;

/**
 * WC 11.2 caches wc_customer_bought_product('', $user_id, …) in the object cache and only treats it as
 * stale when the customer's order count changes. A page view while an order is still unpaid therefore
 * caches "bought nothing", and paying that order doesn't refresh it. Keys mirror WC's
 * ($use_lookup_tables false/true, aggregation 'v3'); if WC changes them, this just stops matching.
 */
add_action('woocommerce_order_status_changed', function ($order_id, $from, $to, $order) {
	$user_id = $order instanceof WC_Order ? $order->get_customer_id() : 0;

	if (! $user_id) {
		return;
	}

	foreach (['', '1'] as $use_lookup_tables) {
		wp_cache_delete('wc_customer_bought_product_' . md5("-{$user_id}-{$use_lookup_tables}-v3"), 'orders');
	}
}, 10, 4);

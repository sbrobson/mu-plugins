<?php

/**
 * Plugin Name: Shop Assistant Role
 * Description: Adds a Shop Assistant role that can only open WooCommerce orders (to pack them) and print packing slips. No reports, sales figures, products, customers or settings.
 */

defined('ABSPATH') || exit;

const SAR_ROLE    = 'shop_assistant';
const SAR_VERSION = 1; // Bump after changing SAR_CAPS so the stored role is rebuilt.
const SAR_CAPS    = [
	'read'                       => true,
	'view_admin_dashboard'       => true, // WooCommerce otherwise redirects non-editors away from wp-admin.
	'read_shop_order'            => true,
	'read_private_shop_orders'   => true,
	'edit_shop_orders'           => true,
	'edit_others_shop_orders'    => true, // Orders menu (HPOS) needs this.
	'edit_published_shop_orders' => true,
	'edit_private_shop_orders'   => true,
];

add_action('init', function () {
	if ((int) get_option('sar_role_version') === SAR_VERSION && get_role(SAR_ROLE)) {
		return;
	}
	remove_role(SAR_ROLE);
	add_role(SAR_ROLE, 'Shop Assistant', SAR_CAPS);
	update_option('sar_role_version', SAR_VERSION);
});

<?php

/**
 * Plugin Name: Hide User Names
 * Description: Hides login names from logged-out visitors: REST users endpoint, author archives, oEmbed author and users sitemap.
 */

defined('ABSPATH') || exit;

add_filter('rest_pre_dispatch', function ($result, $server, $request) {
	if (! is_user_logged_in() && str_starts_with($request->get_route(), '/wp/v2/users')) {
		return new WP_Error('rest_user_cannot_view', __('Sorry, you are not allowed to list users.'), ['status' => 401]);
	}
	return $result;
}, 10, 3);

// Priority 1: before redirect_canonical turns ?author=N into /author/<login-slug>/.
add_action('template_redirect', function () {
	if (! is_user_logged_in() && (is_author() || isset($_GET['author']))) {
		global $wp_query;
		$wp_query->set_404();
		status_header(404);
		nocache_headers();
	}
}, 1);

add_filter('oembed_response_data', function ($data) {
	unset($data['author_name'], $data['author_url']);
	return $data;
});

add_filter('wp_sitemaps_add_provider', function ($provider, $name) {
	return 'users' === $name ? false : $provider;
}, 10, 2);

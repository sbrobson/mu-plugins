<?php

/**
 * Plugin Name: Review Moderation Notifications
 * Description: Sends WordPress's "Please moderate" email for product reviews to every Administrator, not only the site admin email.
 */

defined('ABSPATH') || exit;

// WooCommerce (priority 10) narrows product review notices to admin_email; add every Administrator back.
add_filter('comment_moderation_recipients', function ($emails, $comment_id) {
	$comment = get_comment($comment_id);

	if (! $comment || 'product' !== get_post_type($comment->comment_post_ID)) {
		return $emails;
	}

	foreach (get_users(['role' => 'administrator', 'fields' => ['user_email']]) as $admin) {
		if (is_email($admin->user_email)) {
			$emails[] = $admin->user_email;
		}
	}

	return array_values(array_unique(array_map('strtolower', $emails)));
}, 20, 2);

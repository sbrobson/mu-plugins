<?php

/**
 * Plugin Name: Admin Account Notifications
 * Description: Emails every Administrator when a user account is created (checkout, My Account, social login, wp-admin) or a password is reset. Replaces WordPress's own admin-only notices.
 */

defined('ABSPATH') || exit;

// Core's notices go to the single admin_email only; ours go to every Administrator.
add_filter('wp_send_new_user_notification_to_admin', '__return_false');

if (! function_exists('wp_password_change_notification')) {
	function wp_password_change_notification($user) {}
}

/**
 * Email every Administrator except $exclude_id.
 */
function aan_notify_admins(string $subject, array $lines, int $exclude_id = 0): void
{
	$emails = [];
	foreach (get_users(['role' => 'administrator', 'exclude' => [$exclude_id]]) as $admin) {
		if (is_email($admin->user_email)) {
			$emails[] = $admin->user_email;
		}
	}
	if (! $emails) {
		return;
	}

	$site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
	wp_mail($emails, sprintf('[%s] %s', $site, $subject), implode("\n", $lines));
}

function aan_user_lines(WP_User $user): array
{
	$name = trim($user->first_name . ' ' . $user->last_name);

	return [
		'Name: ' . ($name ?: '(not given)'),
		'Username: ' . $user->user_login,
		'Email: ' . $user->user_email,
		'Roles: ' . implode(', ', $user->roles),
		'Time: ' . wp_date('Y-m-d H:i'),
	];
}

$aan_social_provider = '';

add_action('nsl_before_register', function ($provider) use (&$aan_social_provider) {
	$aan_social_provider = method_exists($provider, 'getLabel') ? $provider->getLabel() : $provider->getId();
});

function aan_signup_source(int $user_id, string $social_provider): string
{
	$creator = wp_get_current_user();
	if ($creator->exists() && $creator->ID !== $user_id) {
		return 'Added in wp-admin by ' . $creator->display_name . ' (' . $creator->user_login . ')';
	}
	if ($social_provider) {
		return $social_provider . ' sign-in';
	}

	$uri = wp_unslash($_SERVER['REQUEST_URI'] ?? '');
	// Classic (?wc-ajax=checkout) and block (/wc/store/v1/checkout) checkout.
	if (str_contains($uri, 'checkout')) {
		return 'Checkout';
	}
	if (did_action('woocommerce_register_post')) {
		return 'My Account registration form';
	}

	return 'Signed up at ' . strtok($uri, '?');
}

// Priority 30: Nextend Social Login redirects and exits at 40 (doAutoLogin).
add_action('user_register', function ($user_id) use (&$aan_social_provider) {
	$user = get_userdata($user_id);
	if (! $user) {
		return;
	}

	$source = aan_signup_source($user_id, $aan_social_provider);

	aan_notify_admins('New user account', array_merge(
		['A new user account has been created.', ''],
		aan_user_lines($user),
		[
			'Source: ' . $source,
			'',
			'Profile: ' . admin_url('user-edit.php?user_id=' . $user_id),
		]
	));
}, 30);

// Fires for both wp-login.php and WooCommerce My Account password resets.
add_action('password_reset', function ($user) {
	aan_notify_admins('Password reset', array_merge(
		['A user has reset their password.', ''],
		aan_user_lines($user),
		[
			'',
			'Profile: ' . admin_url('user-edit.php?user_id=' . $user->ID),
		]
	), $user->ID);
});

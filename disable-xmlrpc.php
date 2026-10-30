<?php

/**
 * Plugin Name: Disable XML-RPC
 * Description: Turns off XML-RPC logins (they bypass 2FA) and pingbacks. Nothing on the site uses XML-RPC.
 */

defined('ABSPATH') || exit;

add_filter('xmlrpc_enabled', '__return_false');

// xmlrpc_enabled doesn't cover pingbacks, which can be abused for DDoS reflection.
add_filter('xmlrpc_methods', function ($methods) {
	unset($methods['pingback.ping'], $methods['pingback.extensions.getPingbacks']);
	return $methods;
});

add_filter('wp_headers', function ($headers) {
	unset($headers['X-Pingback']);
	return $headers;
});

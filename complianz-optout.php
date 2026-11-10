<?php

/**
 * Plugin Name: Complianz opt-out for POPIA
 * Description: Uses Complianz's opt-out consent (cookies on by default, visitor can decline) for South Africa, which Complianz hard-codes as opt-in. See docs/marketing/ads-setup.md.
 */

defined('ABSPATH') || exit;

add_filter('cmplz_consenttype', function ($consenttype, $region) {
	return 'za' === $region ? 'optout' : $consenttype;
}, 20, 2);

<?php

/**
 * Plugin Name: Guest Reviews From Email
 * Description: Lets guest buyers review products from FluentCRM's review request email via a signed per-contact link, with "verified owners only" still enforced.
 */

defined('ABSPATH') || exit;

const CC_GR_PARAM = 'ccr';

/**
 * Bob Go's custom "Shipped" and "Partially Shipped" statuses are paid orders; without this,
 * wc_customer_bought_product() (verified-owner check and badge) ignores them.
 */
add_filter('woocommerce_order_is_paid_statuses', function ($statuses) {
	$statuses[] = 'shipped';
	$statuses[] = 'partially-shipped';
	return array_values(array_unique($statuses));
});

function cc_gr_token($contact_id)
{
	$contact_id = (int) $contact_id;
	return $contact_id . '-' . substr(hash_hmac('sha256', 'cc-review|' . $contact_id, wp_salt('auth')), 0, 20);
}

/**
 * The FluentCRM contact a token belongs to, or null.
 */
function cc_gr_contact_from_token($token)
{
	if (! is_string($token) || ! preg_match('/^(\d+)-[a-f0-9]{20}$/', $token, $m)) {
		return null;
	}
	if (! hash_equals(cc_gr_token($m[1]), $token) || ! function_exists('FluentCrmApi')) {
		return null;
	}

	$contact = FluentCrmApi('contacts')->getContact((int) $m[1]);
	return ($contact && is_email($contact->email)) ? $contact : null;
}

/* ── Email: signed link on each "Leave a review" button ── */

$GLOBALS['cc_gr_subscriber'] = null;

// Runs just before FluentCRM renders {{woo_order.*}}, so the review table knows the recipient.
add_filter('fluent_crm/smartcode_group_callback_woo_order', function ($code, $valueKey, $defaultValue, $subscriber) {
	$GLOBALS['cc_gr_subscriber'] = $subscriber;
	return $code;
}, 9, 4);

// FluentCRM appends this to each product permalink.
add_filter('fluent_crm/woo_product_review_hash', function () {
	$subscriber = $GLOBALS['cc_gr_subscriber'];
	if (empty($subscriber->id)) {
		return '#review_form_wrapper';
	}
	return '?' . CC_GR_PARAM . '=' . cc_gr_token($subscriber->id) . '#review_form_wrapper';
});

/* ── Product page: show the form to the guest buyer ── */

/**
 * Contact for this request if the token is valid and they bought this product, else null.
 * $token defaults to the link parameter; the comment POST passes its hidden field.
 */
function cc_gr_buyer($product_id, $token = null)
{
	if (is_user_logged_in()) {
		return null;
	}
	if (null === $token) {
		$token = isset($_GET[CC_GR_PARAM]) ? sanitize_text_field(wp_unslash($_GET[CC_GR_PARAM])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}
	$contact = cc_gr_contact_from_token($token);
	if (! $contact || ! wc_customer_bought_product($contact->email, 0, $product_id)) {
		return null;
	}
	return $contact;
}

add_action('wp', function () {
	if (! is_singular('product') || empty($_GET[CC_GR_PARAM])) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}

	$contact = cc_gr_buyer(get_queried_object_id());
	if (! $contact) {
		return;
	}

	// Personalised page (prefilled email): keep it out of page caches.
	if (! defined('DONOTCACHEPAGE')) {
		define('DONOTCACHEPAGE', true);
	}
	nocache_headers();

	// WooCommerce shows the form when verification isn't required; we've verified the purchase above.
	add_filter('pre_option_woocommerce_review_rating_verification_required', function () {
		return 'no';
	});

	add_filter('wp_get_current_commenter', function ($commenter) use ($contact) {
		$name = trim($contact->first_name . ' ' . $contact->last_name);
		$commenter['comment_author']       = $name;
		$commenter['comment_author_email'] = $contact->email;
		return $commenter;
	});

	add_action('comment_form', function () {
		printf(
			'<input type="hidden" name="%s" value="%s">',
			esc_attr(CC_GR_PARAM),
			esc_attr(sanitize_text_field(wp_unslash($_GET[CC_GR_PARAM]))) // phpcs:ignore WordPress.Security.NonceVerification
		);
	});
});

/* ── Comment POST: re-check, and lock the email to the buyer's ── */

add_filter('preprocess_comment', function ($data) {
	if (empty($_POST[CC_GR_PARAM]) || 'product' !== get_post_type($data['comment_post_ID'])) { // phpcs:ignore WordPress.Security.NonceVerification
		return $data;
	}

	$token   = sanitize_text_field(wp_unslash($_POST[CC_GR_PARAM])); // phpcs:ignore WordPress.Security.NonceVerification
	$contact = cc_gr_buyer((int) $data['comment_post_ID'], $token);
	if (! $contact) {
		wp_die(esc_html__('This review link is not valid for this product.', 'your-td'), '', ['response' => 403, 'back_link' => true]);
	}

	// WooCommerce sets the "verified owner" flag from this email after the comment is saved.
	$data['comment_author_email'] = $contact->email;
	return $data;
}, 5);

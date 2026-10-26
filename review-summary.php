<?php

/**
 * Plugin Name: Review Summary Shortcode
 * Description: [review_summary] rating summary and [review_list] reviews list/form (with review titles) for WooCommerce product pages.
 */

defined('ABSPATH') || exit;

/**
 * Anchor for the "Write a review" button.
 *
 * Mirrors WooCommerce's single-product-reviews.php: the form (#review_form_wrapper)
 * is only rendered if verification isn't required or the customer bought the product.
 * Otherwise link to #reviews, which holds WooCommerce's "verified owners only" notice.
 */
function rs_review_anchor(WC_Product $product)
{
	$can_review = 'no' === get_option('woocommerce_review_rating_verification_required')
		|| wc_customer_bought_product('', get_current_user_id(), $product->get_id());

	return $can_review ? '#review_form_wrapper' : '#reviews';
}

add_shortcode('review_summary', function () {
	if (! function_exists('wc_get_product')) {
		return '';
	}

	$product = wc_get_product(get_the_ID());
	if (! $product || ! wc_review_ratings_enabled()) {
		return '';
	}

	$anchor = rs_review_anchor($product);
	$counts = $product->get_rating_counts(); // [ rating => count ]
	$total  = array_sum($counts);

	if (! $total) {
		return '<div class="rs rs--empty"><p>' . esc_html__('No reviews yet.', 'woocommerce') . '</p>'
			. '<a class="button rs__btn" href="' . esc_attr($anchor) . '">' . esc_html__('Be the first to review', 'woocommerce') . '</a></div>';
	}

	$avg       = (float) $product->get_average_rating();
	$recommend = (int) ($counts[4] ?? 0) + (int) ($counts[5] ?? 0);

	ob_start(); ?>
	<div class="rs">
		<div class="rs__score">
			<?php echo wc_get_rating_html($avg, $total); // phpcs:ignore -- core-escaped 
			?>
			<span class="rs__text">
				<?php printf(esc_html__('%1$s out of 5 stars from %2$d reviews', 'your-td'), esc_html(number_format($avg, 1)), (int) $total); ?>
			</span>
		</div>
		<p class="rs__recommend">
			<?php printf(esc_html__('%1$d out of %2$d people would recommend this product', 'your-td'), $recommend, (int) $total); ?>
		</p>
		<a class="button rs__btn" href="<?php echo esc_attr($anchor); ?>"><?php esc_html_e('Write a review', 'your-td'); ?></a>
	</div>
<?php
	return ob_get_clean();
});

/**
 * [review_list] – WooCommerce's reviews template (list + form) for Elementor product templates
 * that have no Product Data Tabs widget. The form stays hidden until "Write a review" is clicked.
 */
add_shortcode('review_list', function () {
	static $rendered = false;

	if ($rendered || ! function_exists('wc_get_product') || ! is_singular('product')) {
		return '';
	}

	$product = wc_get_product(get_the_ID());
	if (! $product || ! wc_reviews_enabled() || ! comments_open($product->get_id())) {
		return '';
	}

	$rendered = true;

	ob_start(); ?>
	<div class="woocommerce rs-reviews">
		<?php comments_template(); // WC swaps in single-product-reviews.php 
		?>
	</div>
	<script>
		(function() {
			var root = document.querySelector('.rs-reviews');
			if (!root) return;

			function show(target, focus) {
				if (!target) return;
				target.scrollIntoView({ behavior: 'smooth', block: 'start' });
				if (focus) {
					var field = [].find.call(target.querySelectorAll('input:not([type=hidden]), textarea'), function(el) {
						return el.offsetParent;
					});
					if (field) field.focus({ preventScroll: true });
				}
			}

			function openForm() {
				root.classList.add('is-open');
				show(document.getElementById('review_form_wrapper'), true);
			}

			// Capture phase, so Elementor's anchor-scroll handler doesn't swallow the click.
			document.addEventListener('click', function(e) {
				var btn = e.target.closest('.rs__btn');
				if (!btn) return;
				e.preventDefault();
				e.stopPropagation();
				if (btn.getAttribute('href') === '#review_form_wrapper') {
					openForm();
				} else {
					show(root.querySelector('.woocommerce-verification-required') || root, false);
				}
			}, true);

			function openFromHash() {
				if (location.hash === '#review_form_wrapper' || location.hash === '#respond') {
					openForm();
				}
			}

			// Wait for load so the scroll target isn't moved by late layout shifts.
			if (document.readyState === 'complete') {
				openFromHash();
			} else {
				window.addEventListener('load', openFromHash);
			}
			window.addEventListener('hashchange', openFromHash);
		})();
	</script>
<?php
	return ob_get_clean();
});

/* ── Review titles ── */

add_filter('woocommerce_product_review_comment_form_args', function ($args) {
	$args['comment_field'] = '<p class="comment-form-review-title"><label for="review_title">' . esc_html__('Review title', 'your-td') . '</label>'
		. '<input id="review_title" name="review_title" type="text" maxlength="100"></p>'
		. $args['comment_field'];

	return $args;
});

add_action('comment_post', function ($comment_id) {
	if (empty($_POST['review_title']) || 'product' !== get_post_type(get_comment($comment_id)->comment_post_ID)) { // phpcs:ignore WordPress.Security.NonceVerification -- core comment form
		return;
	}

	$title = sanitize_text_field(wp_unslash($_POST['review_title'])); // phpcs:ignore WordPress.Security.NonceVerification
	if ('' !== $title) {
		add_comment_meta($comment_id, 'review_title', $title, true);
	}
});

// Priority 15: after the stars (woocommerce_review_display_rating, priority 10).
add_action('woocommerce_review_before_comment_meta', function ($comment) {
	$title = get_comment_meta($comment->comment_ID, 'review_title', true);
	if ('' !== $title) {
		echo '<p class="rs-review__title">' . esc_html($title) . '</p>';
	}
}, 15);

// Admin: edit the title on the comment edit screen.
add_action('add_meta_boxes_comment', function ($comment) {
	if ('product' !== get_post_type($comment->comment_post_ID)) {
		return;
	}

	add_meta_box('rs-review-title', __('Review title', 'your-td'), function ($comment) {
		wp_nonce_field('rs_review_title', 'rs_review_title_nonce');
		printf(
			'<input type="text" name="review_title" value="%s" maxlength="100" class="widefat">',
			esc_attr(get_comment_meta($comment->comment_ID, 'review_title', true))
		);
	}, 'comment', 'normal');
});

add_action('edit_comment', function ($comment_id) {
	if (
		! isset($_POST['rs_review_title_nonce'], $_POST['review_title'])
		|| ! wp_verify_nonce(sanitize_key($_POST['rs_review_title_nonce']), 'rs_review_title')
		|| ! current_user_can('edit_comment', $comment_id)
	) {
		return;
	}

	$title = sanitize_text_field(wp_unslash($_POST['review_title']));
	if ('' === $title) {
		delete_comment_meta($comment_id, 'review_title');
	} else {
		update_comment_meta($comment_id, 'review_title', $title);
	}
});

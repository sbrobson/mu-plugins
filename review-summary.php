<?php

/**
 * Plugin Name: Review Summary Shortcode
 * Description: [review_summary] – rating summary block for WooCommerce product pages.
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

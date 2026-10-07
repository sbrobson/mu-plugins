<?php
/**
 * Plugin Name: Review Summary Shortcode
 * Description: [review_summary] – rating summary block for WooCommerce product pages.
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'review_summary', function () {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return '';
	}

	$product = wc_get_product( get_the_ID() );
	if ( ! $product || ! wc_review_ratings_enabled() ) {
		return '';
	}

	$counts = $product->get_rating_counts(); // [ rating => count ]
	$total  = array_sum( $counts );

	if ( ! $total ) {
		return '<div class="rs rs--empty"><p>' . esc_html__( 'No reviews yet.', 'woocommerce' ) . '</p>'
			. '<a class="button rs__btn" href="#review_form_wrapper">' . esc_html__( 'Be the first to review', 'woocommerce' ) . '</a></div>';
	}

	$avg       = (float) $product->get_average_rating();
	$recommend = (int) ( $counts[4] ?? 0 ) + (int) ( $counts[5] ?? 0 );

	ob_start(); ?>
	<div class="rs">
		<div class="rs__score">
			<?php echo wc_get_rating_html( $avg, $total ); // phpcs:ignore -- core-escaped ?>
			<span class="rs__text">
				<?php printf( esc_html__( '%1$s out of 5 stars from %2$d reviews', 'your-td' ), esc_html( number_format( $avg, 1 ) ), (int) $total ); ?>
			</span>
		</div>
		<p class="rs__recommend">
			<?php printf( esc_html__( '%1$d out of %2$d people would recommend this product', 'your-td' ), $recommend, (int) $total ); ?>
		</p>
		<a class="button rs__btn" href="#review_form_wrapper"><?php esc_html_e( 'Write a review', 'your-td' ); ?></a>
	</div>
	<?php
	return ob_get_clean();
} );

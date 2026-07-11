<?php
/**
 * Single Travel Package template.
 *
 * A theme can override this by placing single-travel_package.php in its own
 * directory. When no override exists, this file is loaded via the single_template filter.
 *
 * @package TravelPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();

	$post_id     = get_the_ID();
	$duration    = get_post_meta( $post_id, Travel_Pack_Metaboxes::META_DURATION, true );
	$group_size  = get_post_meta( $post_id, Travel_Pack_Metaboxes::META_GROUP_SIZE, true );
	$price       = get_post_meta( $post_id, Travel_Pack_Metaboxes::META_PRICE, true );
	$terrain     = get_post_meta( $post_id, Travel_Pack_Metaboxes::META_TERRAIN, true );
	$season      = get_post_meta( $post_id, Travel_Pack_Metaboxes::META_SEASON, true );
	$difficulty  = get_post_meta( $post_id, Travel_Pack_Metaboxes::META_DIFFICULTY, true );
	$included    = get_post_meta( $post_id, Travel_Pack_Metaboxes::META_INCLUDED, true );
	$not_incl    = get_post_meta( $post_id, Travel_Pack_Metaboxes::META_NOT_INCLUDED, true );
	$important   = get_post_meta( $post_id, Travel_Pack_Metaboxes::META_IMPORTANT, true );
	$itinerary   = Travel_Pack_Metaboxes::get_itinerary_with_labels( $post_id );
	$categories  = get_the_terms( $post_id, TRAVEL_PACK_TAX_CATEGORY );
	$tags        = get_the_terms( $post_id, TRAVEL_PACK_TAX_TAG );

	$facts = array(
		'duration'   => array( __( 'Duration', 'travel-pack' ),   $duration,   'dashicons-clock' ),
		'group_size' => array( __( 'Group Size', 'travel-pack' ), $group_size, 'dashicons-groups' ),
		'price'      => array( __( 'Price', 'travel-pack' ),      $price,      'dashicons-tag' ),
		'terrain'    => array( __( 'Terrain', 'travel-pack' ),    $terrain,    'dashicons-location-alt' ),
		'season'     => array( __( 'Season', 'travel-pack' ),     $season,     'dashicons-calendar-alt' ),
		'difficulty' => array( __( 'Difficulty', 'travel-pack' ), $difficulty, 'dashicons-chart-bar' ),
	);
	?>

	<article id="post-<?php the_ID(); ?>" <?php post_class( 'travel-pack-single' ); ?>>

		<header class="travel-pack-single__header" <?php if ( has_post_thumbnail() ) : ?>style="background-image:linear-gradient(180deg,rgba(0,0,0,.35),rgba(0,0,0,.55)),url('<?php echo esc_url( get_the_post_thumbnail_url( $post_id, 'full' ) ); ?>')"<?php endif; ?>>
			<div class="travel-pack-single__hero">
				<?php if ( $categories && ! is_wp_error( $categories ) ) : ?>
					<div class="travel-pack-single__categories">
						<?php foreach ( $categories as $cat ) : ?>
							<a href="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<h1 class="travel-pack-single__title"><?php the_title(); ?></h1>
				<?php if ( get_the_excerpt() ) : ?>
					<p class="travel-pack-single__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php endif; ?>
				<a href="#travel-pack-booking" class="travel-pack-single__cta"><?php esc_html_e( 'Book This Trip', 'travel-pack' ); ?></a>
			</div>
		</header>

		<div class="travel-pack-single__body">

			<section class="travel-pack-facts">
				<?php foreach ( $facts as $key => $conf ) :
					list( $label, $value, $icon ) = $conf;
					if ( '' === trim( (string) $value ) ) {
						continue;
					}
					?>
					<div class="travel-pack-fact">
						<span class="travel-pack-fact__icon dashicons <?php echo esc_attr( $icon ); ?>"></span>
						<div>
							<span class="travel-pack-fact__label"><?php echo esc_html( $label ); ?></span>
							<span class="travel-pack-fact__value"><?php echo esc_html( $value ); ?></span>
						</div>
					</div>
				<?php endforeach; ?>
			</section>

			<div class="travel-pack-single__layout">
				<main class="travel-pack-single__main">

					<section class="travel-pack-section">
						<h2><?php esc_html_e( 'Overview', 'travel-pack' ); ?></h2>
						<div class="travel-pack-single__content">
							<?php the_content(); ?>
						</div>
					</section>

					<?php if ( ! empty( $itinerary ) ) : ?>
						<section class="travel-pack-section travel-pack-itinerary-frontend">
							<h2><?php esc_html_e( 'Itinerary', 'travel-pack' ); ?></h2>
							<ol class="travel-pack-itinerary-frontend__list">
								<?php foreach ( $itinerary as $item ) : ?>
									<li>
										<span class="travel-pack-itinerary-frontend__day"><?php echo esc_html( $item['label'] ); ?></span>
										<span class="travel-pack-itinerary-frontend__text"><?php echo wp_kses_post( wpautop( $item['text'] ) ); ?></span>
									</li>
								<?php endforeach; ?>
							</ol>
						</section>
					<?php endif; ?>

					<div class="travel-pack-two-col">
						<?php if ( is_array( $included ) && ! empty( $included ) ) : ?>
							<section class="travel-pack-section travel-pack-list travel-pack-list--included">
								<h2><?php esc_html_e( 'What\'s Included', 'travel-pack' ); ?></h2>
								<ul>
									<?php foreach ( $included as $item ) : ?>
										<li><?php echo wp_kses_post( $item ); ?></li>
									<?php endforeach; ?>
								</ul>
							</section>
						<?php endif; ?>

						<?php if ( is_array( $not_incl ) && ! empty( $not_incl ) ) : ?>
							<section class="travel-pack-section travel-pack-list travel-pack-list--excluded">
								<h2><?php esc_html_e( 'What\'s Not Included', 'travel-pack' ); ?></h2>
								<ul>
									<?php foreach ( $not_incl as $item ) : ?>
										<li><?php echo wp_kses_post( $item ); ?></li>
									<?php endforeach; ?>
								</ul>
							</section>
						<?php endif; ?>
					</div>

					<?php if ( is_array( $important ) && ! empty( $important ) ) : ?>
						<section class="travel-pack-section travel-pack-notes">
							<h2><?php esc_html_e( 'Important Notes', 'travel-pack' ); ?></h2>
							<ul>
								<?php foreach ( $important as $item ) : ?>
									<li><?php echo wp_kses_post( $item ); ?></li>
								<?php endforeach; ?>
							</ul>
						</section>
					<?php endif; ?>

					<?php if ( $tags && ! is_wp_error( $tags ) ) : ?>
						<div class="travel-pack-tags">
							<?php foreach ( $tags as $tag ) : ?>
								<a class="travel-pack-tag" href="<?php echo esc_url( get_term_link( $tag ) ); ?>">#<?php echo esc_html( $tag->name ); ?></a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

				</main>

				<aside class="travel-pack-single__aside">
					<?php Travel_Pack_Booking::render_form( $post_id ); ?>
				</aside>
			</div>
		</div>
	</article>

<?php endwhile;

get_footer();

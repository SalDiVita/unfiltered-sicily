<?php
/**
 * Archivio tassonomia: format (Itinerary / Guide / Story / List)
 *
 * Asse "che tipo di pezzo è". Header come gli altri archivi + griglia
 * degli articoli di quel formato. Niente filtro per luogo: gli itinerari
 * isola-intera spesso non hanno destination, quindi qui sarebbe vuoto.
 *
 * Il CSS è condiviso ed è in style.css.
 *
 * @package UnmappedSicily
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$term = get_queried_object();

/** Helper ACF sicuro. */
$us_field = function ( $name, $t ) {
	return function_exists( 'get_field' ) ? get_field( $name, $t ) : '';
};

/* ---- DATI HEADER ---- */
$subtitle    = $us_field( 'subtitle', $term );
$intro       = $us_field( 'intro', $term );
$image       = $us_field( 'image', $term );
$accent      = $us_field( 'accent_color', $term );
$accent      = $accent ? $accent : '#3a4042';
$h1_override = $us_field( 'h1_override', $term );
$h1_text     = $h1_override ? $h1_override : $term->name . '.';
$bottom_text = $us_field( 'bottom_text', $term );
$piece_count = (int) $term->count;

/* Posizione del formato fra i formati (es. 01 of 04). */
$formats       = get_terms( array( 'taxonomy' => 'format', 'hide_empty' => false, 'orderby' => 'name' ) );
$total_formats = is_array( $formats ) ? count( $formats ) : 0;
$format_index  = 0;
if ( is_array( $formats ) ) {
	foreach ( $formats as $i => $t ) {
		if ( (int) $t->term_id === (int) $term->term_id ) {
			$format_index = $i + 1;
			break;
		}
	}
}

/* ---- QUERY ARTICOLI ---- */
$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$aq    = new WP_Query( array(
	'post_type'      => 'post',
	'posts_per_page' => (int) get_option( 'posts_per_page' ),
	'paged'          => $paged,
	'tax_query'      => array(
		array( 'taxonomy' => 'format', 'field' => 'term_id', 'terms' => $term->term_id ),
	),
) );
?>

<div class="us-wrap">

	<div class="us-breadcrumb us-mono">
		<?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?> &middot; format &middot; <?php echo esc_html( $term->slug ); ?>
	</div>

	<!-- ============ HERO ============ -->
	<header class="us-hero">
		<?php if ( $image && ! empty( $image['url'] ) ) : ?>
			<div class="us-hero__bg" style="background-image:url('<?php echo esc_url( $image['url'] ); ?>')"></div>
		<?php endif; ?>
		<div class="us-hero__tint" style="background:<?php echo esc_attr( $accent ); ?>"></div>

		<div class="us-hero__label us-mono">img &middot; <?php echo esc_html( $term->name ); ?></div>

		<div>
			<div class="us-hero__index us-mono">
				format &middot; <?php echo esc_html( sprintf( '%02d of %02d', $format_index, $total_formats ) ); ?>
				&nbsp;&mdash;&nbsp; <?php echo esc_html( $piece_count ); ?> pieces
			</div>
			<div class="us-hero__bottom">
				<div>
					<h1 class="us-hero__title us-serif"><?php echo esc_html( $h1_text ); ?></h1>
					<?php if ( $subtitle ) : ?>
						<div class="us-hero__subtitle us-serif"><?php echo esc_html( $subtitle ); ?></div>
					<?php endif; ?>
				</div>
				<?php if ( $intro ) : ?>
					<div class="us-hero__intro us-serif"><?php echo wp_kses_post( wpautop( $intro ) ); ?></div>
				<?php endif; ?>
			</div>
		</div>
	</header>

	<!-- ============ ARTICOLI ============ -->
	<div class="us-section__head">
		<h2 class="us-section__title us-serif">All <?php echo esc_html( $term->name ); ?> pieces.</h2>
		<span class="us-mono" style="color:var(--global-palette5,#888)"><?php echo esc_html( (int) $aq->found_posts ); ?> results</span>
	</div>

	<div class="us-articles">
		<?php
		if ( $aq->have_posts() ) :
			while ( $aq->have_posts() ) :
				$aq->the_post();
				$thumb = get_the_post_thumbnail_url( get_the_ID(), 'large' );

				// Meta: mostra il luogo se c'è, altrimenti il tema (i formati variano).
				$meta_bits = array();
				$dests     = get_the_terms( get_the_ID(), 'destination' );
				if ( is_array( $dests ) ) {
					foreach ( $dests as $d ) {
						if ( $d->parent ) {
							$meta_bits[] = $d->name;
						}
					}
				}
				if ( empty( $meta_bits ) ) {
					$post_themes = get_the_terms( get_the_ID(), 'theme' );
					if ( is_array( $post_themes ) ) {
						foreach ( $post_themes as $th ) {
							$meta_bits[] = $th->name;
						}
					}
				}
				?>
				<a class="us-article" href="<?php echo esc_url( get_permalink() ); ?>">
					<div class="us-article__thumb" style="background:<?php echo esc_attr( $accent ); ?>">
						<?php if ( $thumb ) : ?>
							<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" loading="lazy">
						<?php else : ?>
							<div class="us-card__tile-stripes"></div>
						<?php endif; ?>
					</div>
					<h3 class="us-article__title us-serif"><?php the_title(); ?></h3>
					<p class="us-article__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
					<div class="us-article__foot">
						<div class="us-card__pillars us-mono" style="color:var(--global-palette5,#888)">
							<?php echo esc_html( $meta_bits ? implode( ' &middot; ', array_map( 'wp_strip_all_tags', $meta_bits ) ) : '' ); ?>
						</div>
						<span class="us-card__browse us-mono">read &rarr;</span>
					</div>
				</a>
				<?php
			endwhile;
			wp_reset_postdata();
		else :
			echo '<p>No pieces here yet.</p>';
		endif;
		?>
	</div>

	<?php
	if ( $aq->max_num_pages > 1 ) {
		echo '<div class="us-pagination">';
		echo paginate_links( array(
			'total'     => $aq->max_num_pages,
			'current'   => $paged,
			'prev_text' => '&larr;',
			'next_text' => '&rarr;',
		) );
		echo '</div>';
	}
	?>

	<?php if ( $bottom_text ) : ?>
		<section class="us-bottom">
			<div class="us-longtext" id="us-longtext"><?php echo wp_kses_post( $bottom_text ); ?></div>
			<button class="us-longtext__toggle us-mono" type="button" hidden>read more &darr;</button>
		</section>
	<?php endif; ?>

</div>

<script>
(function(){
	var box = document.getElementById('us-longtext');
	if ( ! box ) { return; }
	var btn = box.parentNode.querySelector('.us-longtext__toggle');
	if ( box.scrollHeight > 260 ) {
		box.classList.add('is-collapsed');
		btn.hidden = false;
		btn.addEventListener('click', function(){
			var collapsed = box.classList.toggle('is-collapsed');
			btn.innerHTML = collapsed ? 'read more &darr;' : 'read less &uarr;';
		});
	}
})();
</script>

<?php
get_footer();

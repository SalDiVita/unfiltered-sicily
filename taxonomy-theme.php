<?php
/**
 * Archivio tassonomia: theme (es. Nature Wild)
 *
 * Header (H1/subtitle/intro/image) + bottom text, come gli altri archivi.
 * Corpo: ARTICOLI del tema, con filtro geografico a due livelli:
 *  - riga 1: le REGIONI (con conteggio, solo quelle non vuote);
 *  - riga 2 (compare selezionando una regione): le sue LOCALITÀ in ordine
 *    alfabetico. Tutto server-side via ?region= e ?place=.
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

/** Conta i post in una destination (con figli opz.) e in un tema. */
$us_count = function ( $dest_id, $children, $theme_id ) {
	$q = new WP_Query( array(
		'post_type'      => 'post',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => false,
		'tax_query'      => array(
			'relation' => 'AND',
			array( 'taxonomy' => 'destination', 'field' => 'term_id', 'terms' => $dest_id, 'include_children' => $children ),
			array( 'taxonomy' => 'theme', 'field' => 'term_id', 'terms' => $theme_id ),
		),
	) );
	$n = $q->found_posts;
	wp_reset_postdata();
	return $n;
};

/* ---- DATI HEADER ---- */
$subtitle    = $us_field( 'subtitle', $term );
$intro       = $us_field( 'intro', $term );
$image       = $us_field( 'image', $term );
$accent      = $us_field( 'accent_color', $term );
$accent      = $accent ? $accent : '#264653';
$h1_override = $us_field( 'h1_override', $term );
$h1_text     = $h1_override ? $h1_override : $term->name . '.';
$bottom_text = $us_field( 'bottom_text', $term );
$dispatch_count = (int) $term->count;

/* Posizione del tema fra i temi (es. 02 of 04). */
$themes       = get_terms( array( 'taxonomy' => 'theme', 'hide_empty' => false, 'orderby' => 'name' ) );
$total_themes = is_array( $themes ) ? count( $themes ) : 0;
$theme_index  = 0;
if ( is_array( $themes ) ) {
	foreach ( $themes as $i => $t ) {
		if ( (int) $t->term_id === (int) $term->term_id ) {
			$theme_index = $i + 1;
			break;
		}
	}
}

/* ---- REGIONI ---- */
$regions = get_terms( array( 'taxonomy' => 'destination', 'parent' => 0, 'hide_empty' => false, 'orderby' => 'name' ) );
$regions = is_array( $regions ) ? $regions : array();

/* ---- FILTRI ATTIVI (?region= / ?place=) ---- */
$req_region = isset( $_GET['region'] ) ? sanitize_title( wp_unslash( $_GET['region'] ) ) : '';
$req_place  = isset( $_GET['place'] ) ? sanitize_title( wp_unslash( $_GET['place'] ) ) : '';

$active_place = $req_place ? get_term_by( 'slug', $req_place, 'destination' ) : null;
if ( ! $active_place || is_wp_error( $active_place ) ) {
	$active_place = null;
}

$active_region = null;
if ( $active_place && $active_place->parent ) {
	$active_region = get_term( $active_place->parent, 'destination' );
} elseif ( $req_region ) {
	$r = get_term_by( 'slug', $req_region, 'destination' );
	if ( $r && ! is_wp_error( $r ) && 0 === (int) $r->parent ) {
		$active_region = $r;
	}
}
if ( $active_region && is_wp_error( $active_region ) ) {
	$active_region = null;
}

$clean = get_term_link( $term );

/* ---- QUERY ARTICOLI ---- */
$paged   = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$art_tax = array( array( 'taxonomy' => 'theme', 'field' => 'term_id', 'terms' => $term->term_id ) );
if ( $active_place ) {
	$art_tax['relation'] = 'AND';
	$art_tax[]           = array( 'taxonomy' => 'destination', 'field' => 'term_id', 'terms' => $active_place->term_id );
} elseif ( $active_region ) {
	$art_tax['relation'] = 'AND';
	$art_tax[]           = array( 'taxonomy' => 'destination', 'field' => 'term_id', 'terms' => $active_region->term_id, 'include_children' => true );
}
$aq = new WP_Query( array(
	'post_type'      => 'post',
	'posts_per_page' => (int) get_option( 'posts_per_page' ),
	'paged'          => $paged,
	'tax_query'      => $art_tax,
) );

/* ---- LOCALITÀ della regione attiva (alfabetico) ---- */
$localities = $active_region ? get_terms( array( 'taxonomy' => 'destination', 'parent' => $active_region->term_id, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) ) : array();
$localities = is_array( $localities ) ? $localities : array();

/* Titolo contestuale della sezione articoli. */
if ( $active_place ) {
	$section_title = $term->name . ' in ' . $active_place->name . '.';
} elseif ( $active_region ) {
	$section_title = $term->name . ' in ' . $active_region->name . '.';
} else {
	$section_title = 'All ' . $term->name . ' dispatches.';
}
?>

<div class="us-wrap">

	<div class="us-breadcrumb us-mono">
		<?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?> &middot; theme &middot; <?php echo esc_html( $term->slug ); ?>
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
				pillar &middot; <?php echo esc_html( sprintf( '%02d of %02d', $theme_index, $total_themes ) ); ?>
				&nbsp;&mdash;&nbsp; <?php echo esc_html( $dispatch_count ); ?> dispatches
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

	<!-- ============ FILTRO PER LUOGO ============ -->
	<div class="us-filter__kicker us-mono">filter &middot; by place</div>
	<div class="us-filter__head">
		<h2 class="us-filter__q us-serif">Where in Sicily?</h2>
		<div class="us-mono" style="color:var(--global-palette5,#888)"><?php echo esc_html( (int) $aq->found_posts ); ?> dispatches</div>
	</div>

	<!-- riga 1: regioni -->
	<div class="us-chips" style="margin-bottom:12px">
		<a class="us-chip <?php echo ( ! $active_region && ! $active_place ) ? 'us-chip--active' : ''; ?>" href="<?php echo esc_url( $clean ); ?>">
			All <span class="us-chip__count"><?php echo esc_html( $dispatch_count ); ?></span>
		</a>
		<?php
		foreach ( $regions as $reg ) :
			$reg_count = $us_count( $reg->term_id, true, $term->term_id );
			if ( $reg_count < 1 ) {
				continue;
			}
			$reg_link   = add_query_arg( 'region', $reg->slug, $clean );
			$reg_active = ( $active_region && (int) $active_region->term_id === (int) $reg->term_id );
			?>
			<a class="us-chip <?php echo $reg_active ? 'us-chip--active' : ''; ?>" href="<?php echo esc_url( $reg_link ); ?>">
				<?php echo esc_html( $reg->name ); ?> <span class="us-chip__count"><?php echo esc_html( $reg_count ); ?></span>
			</a>
			<?php
		endforeach;
		?>
	</div>

	<!-- riga 2: località della regione attiva (compare solo se una regione è selezionata) -->
	<?php if ( $active_region ) : ?>
		<div class="us-chips us-subchips">
			<a class="us-chip <?php echo ( ! $active_place ) ? 'us-chip--active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'region', $active_region->slug, $clean ) ); ?>">
				All <?php echo esc_html( $active_region->name ); ?>
			</a>
			<?php
			foreach ( $localities as $loc ) :
				$loc_count = $us_count( $loc->term_id, false, $term->term_id );
				if ( $loc_count < 1 ) {
					continue;
				}
				$loc_link   = add_query_arg( 'place', $loc->slug, $clean );
				$loc_active = ( $active_place && (int) $active_place->term_id === (int) $loc->term_id );
				?>
				<a class="us-chip <?php echo $loc_active ? 'us-chip--active' : ''; ?>" href="<?php echo esc_url( $loc_link ); ?>">
					<?php echo esc_html( $loc->name ); ?> <span class="us-chip__count"><?php echo esc_html( $loc_count ); ?></span>
				</a>
				<?php
			endforeach;
			?>
		</div>
	<?php endif; ?>

	<!-- ============ ARTICOLI ============ -->
	<div class="us-section__head">
		<h2 class="us-section__title us-serif"><?php echo esc_html( $section_title ); ?></h2>
		<span class="us-mono" style="color:var(--global-palette5,#888)"><?php echo esc_html( (int) $aq->found_posts ); ?> results</span>
	</div>

	<div class="us-articles">
		<?php
		if ( $aq->have_posts() ) :
			while ( $aq->have_posts() ) :
				$aq->the_post();
				$thumb = get_the_post_thumbnail_url( get_the_ID(), 'large' );

				// Mostra il LUOGO dell'articolo (la località) invece del tema, qui ridondante.
				$dests       = get_the_terms( get_the_ID(), 'destination' );
				$place_names = array();
				if ( is_array( $dests ) ) {
					foreach ( $dests as $d ) {
						if ( $d->parent ) {
							$place_names[] = $d->name;
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
							<?php echo esc_html( $place_names ? implode( ' &middot; ', array_map( 'wp_strip_all_tags', $place_names ) ) : '' ); ?>
						</div>
						<span class="us-card__browse us-mono">read &rarr;</span>
					</div>
				</a>
				<?php
			endwhile;
			wp_reset_postdata();
		else :
			echo '<p>No dispatches here yet.</p>';
		endif;
		?>
	</div>

	<?php
	if ( $aq->max_num_pages > 1 ) {
		$extra = array();
		if ( $active_place ) {
			$extra['place'] = $active_place->slug;
		} elseif ( $active_region ) {
			$extra['region'] = $active_region->slug;
		}
		echo '<div class="us-pagination">';
		echo paginate_links( array(
			'total'     => $aq->max_num_pages,
			'current'   => $paged,
			'add_args'  => $extra ? $extra : false,
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

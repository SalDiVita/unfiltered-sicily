<?php
/**
 * Archivio tassonomia: destination
 *
 * Due rami:
 *  - REGIONE (termine padre, parent === 0): header + chip filtro per pillar
 *    + griglia delle località figlie.
 *  - LOCALITÀ (termine figlio): header + chip filtro + ARTICOLI a sinistra
 *    + sidebar con le altre località della stessa regione.
 *
 * Tutto il contenuto editoriale arriva dai campi ACF del term meta.
 * I numeri (dispatches, pieces, conteggi pillar) sono calcolati qui.
 *
 * @package UnmappedSicily
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$term      = get_queried_object();
$is_region = ( $term && 0 === (int) $term->parent );

/** Helper: legge un campo ACF in sicurezza, anche se ACF non c'è. */
$us_field = function ( $name, $t ) {
	return function_exists( 'get_field' ) ? get_field( $name, $t ) : '';
};

/** Helper: conta i post in una destination (regione = include i figli). */
$us_count = function ( $term_id, $children = false, $theme_id = 0 ) {
	$tax_query = array(
		array(
			'taxonomy'         => 'destination',
			'field'            => 'term_id',
			'terms'            => $term_id,
			'include_children' => $children,
		),
	);
	if ( $theme_id ) {
		$tax_query['relation'] = 'AND';
		$tax_query[]           = array(
			'taxonomy' => 'theme',
			'field'    => 'term_id',
			'terms'    => $theme_id,
		);
	}
	$q = new WP_Query(
		array(
			'post_type'      => 'post',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
			'tax_query'      => $tax_query,
		)
	);
	$found = $q->found_posts;
	wp_reset_postdata();
	return $found;
};

/** Mappa dei temi (id => nome/slug/colore), usata da entrambi i rami. */
$us_theme_map = function () use ( $us_field ) {
	$map    = array();
	$themes = get_terms( array( 'taxonomy' => 'theme', 'hide_empty' => false, 'orderby' => 'name' ) );
	if ( is_array( $themes ) ) {
		foreach ( $themes as $th ) {
			$map[ $th->term_id ] = array(
				'name'   => $th->name,
				'slug'   => $th->slug,
				'accent' => $us_field( 'accent_color', $th ),
			);
		}
	}
	return $map;
};

// Filtro attivo (?theme=slug) — condiviso.
$active_theme = isset( $_GET['theme'] ) ? sanitize_title( wp_unslash( $_GET['theme'] ) ) : '';


if ( $is_region ) :

	/* =====================================================
	   DATI DELLA REGIONE
	   ===================================================== */
	$subtitle = $us_field( 'subtitle', $term );
	$intro    = $us_field( 'intro', $term );
	$image    = $us_field( 'image', $term );
	$accent   = $us_field( 'accent_color', $term );
	$accent   = $accent ? $accent : '#264653';

	$h1_override = $us_field( 'h1_override', $term );
	$h1_text     = $h1_override ? $h1_override : $term->name . '.';
	$bottom_text = $us_field( 'bottom_text', $term );

	$dispatch_count = $us_count( $term->term_id, true );

	// Posizione della regione fra tutte le regioni (es. 01 of 06).
	$regions = get_terms( array( 'taxonomy' => 'destination', 'parent' => 0, 'hide_empty' => false, 'orderby' => 'name' ) );
	$total_regions = is_array( $regions ) ? count( $regions ) : 0;
	$region_index  = 0;
	if ( is_array( $regions ) ) {
		foreach ( $regions as $i => $r ) {
			if ( (int) $r->term_id === (int) $term->term_id ) {
				$region_index = $i + 1;
				break;
			}
		}
	}

	$themes    = get_terms( array( 'taxonomy' => 'theme', 'hide_empty' => false, 'orderby' => 'name' ) );
	$theme_map = $us_theme_map();

	$all_localities = get_terms( array( 'taxonomy' => 'destination', 'parent' => $term->term_id, 'hide_empty' => false, 'orderby' => 'count', 'order' => 'DESC' ) );
	$all_localities = is_array( $all_localities ) ? $all_localities : array();
	$total_loc      = count( $all_localities );

	$base_link = get_term_link( $term );

	// ---- Modalita filtro: tema attivo -> mostro ARTICOLI, non le localita ----
	$active_place_slug = isset( $_GET['place'] ) ? sanitize_title( wp_unslash( $_GET['place'] ) ) : '';
	$active_theme_term = $active_theme ? get_term_by( 'slug', $active_theme, 'theme' ) : null;
	if ( ! $active_theme_term || is_wp_error( $active_theme_term ) ) {
		$active_theme_term = null;
	}
	$active_place_term = null;
	if ( $active_theme_term && $active_place_slug ) {
		$pt = get_term_by( 'slug', $active_place_slug, 'destination' );
		if ( $pt && ! is_wp_error( $pt ) && (int) $pt->parent === (int) $term->term_id ) {
			$active_place_term = $pt;
		}
	}
	$filtering = ( null !== $active_theme_term );

	$aq                = null;
	$paged             = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
	$filter_localities = array();
	if ( $filtering ) {
		$art_tax = array(
			'relation' => 'AND',
			array( 'taxonomy' => 'theme', 'field' => 'term_id', 'terms' => $active_theme_term->term_id ),
		);
		if ( $active_place_term ) {
			$art_tax[] = array( 'taxonomy' => 'destination', 'field' => 'term_id', 'terms' => $active_place_term->term_id );
		} else {
			$art_tax[] = array( 'taxonomy' => 'destination', 'field' => 'term_id', 'terms' => $term->term_id, 'include_children' => true );
		}
		$aq = new WP_Query( array(
			'post_type'      => 'post',
			'posts_per_page' => (int) get_option( 'posts_per_page' ),
			'paged'          => $paged,
			'tax_query'      => $art_tax,
		) );
		$filter_localities = get_terms( array( 'taxonomy' => 'destination', 'parent' => $term->term_id, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) );
		$filter_localities = is_array( $filter_localities ) ? $filter_localities : array();
	}
	?>

	<div class="us-wrap">

		<div class="us-breadcrumb us-mono">
			<?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?> &middot; category &middot; <?php echo esc_html( $term->slug ); ?>
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
					region &middot; <?php echo esc_html( sprintf( '%02d of %02d', $region_index, $total_regions ) ); ?>
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

		<!-- ============ FILTRO PER PILLAR ============ -->
		<div class="us-filter__kicker us-mono">filter &middot; by pillar</div>
		<div class="us-filter__head">
			<h2 class="us-filter__q us-serif">What are you looking for?</h2>
			<div class="us-mono" style="color:var(--global-palette5,#888)"><?php echo $filtering ? esc_html( (int) $aq->found_posts ) . ' dispatches' : esc_html( $total_loc ) . ' localities'; ?></div>
		</div>

		<div class="us-chips">
			<a class="us-chip <?php echo $active_theme ? '' : 'us-chip--active'; ?>" href="<?php echo esc_url( $base_link ); ?>">
				All <span class="us-chip__count"><?php echo esc_html( $total_loc ); ?></span>
			</a>
			<?php
			if ( is_array( $themes ) ) :
				foreach ( $themes as $th ) :
					$th_count = $us_count( $term->term_id, true, $th->term_id );
					if ( $th_count < 1 ) {
						continue;
					}
					$th_link   = add_query_arg( 'theme', $th->slug, $base_link );
					$is_active = ( $active_theme === $th->slug );
					?>
					<a class="us-chip <?php echo $is_active ? 'us-chip--active' : ''; ?>" href="<?php echo esc_url( $th_link ); ?>">
						#<?php echo esc_html( str_replace( ' ', '', $th->name ) ); ?>
						<span class="us-chip__count"><?php echo esc_html( $th_count ); ?></span>
					</a>
					<?php
				endforeach;
			endif;
			?>
		</div>

		<?php if ( $filtering ) : ?>

			<div class="us-chips us-subchips">
				<a class="us-chip <?php echo $active_place_term ? '' : 'us-chip--active'; ?>" href="<?php echo esc_url( add_query_arg( 'theme', $active_theme_term->slug, $base_link ) ); ?>">
					All <?php echo esc_html( $term->name ); ?>
				</a>
				<?php
				foreach ( $filter_localities as $loc ) :
					$loc_count = $us_count( $loc->term_id, false, $active_theme_term->term_id );
					if ( $loc_count < 1 ) {
						continue;
					}
					$loc_link   = add_query_arg( array( 'theme' => $active_theme_term->slug, 'place' => $loc->slug ), $base_link );
					$loc_active = ( $active_place_term && (int) $active_place_term->term_id === (int) $loc->term_id );
					?>
					<a class="us-chip <?php echo $loc_active ? 'us-chip--active' : ''; ?>" href="<?php echo esc_url( $loc_link ); ?>">
						<?php echo esc_html( $loc->name ); ?> <span class="us-chip__count"><?php echo esc_html( $loc_count ); ?></span>
					</a>
					<?php
				endforeach;
				?>
			</div>

			<div class="us-filtercrumb us-mono">
				<a href="<?php echo esc_url( $base_link ); ?>">&larr; <?php echo esc_html( $term->name ); ?></a>
				<?php if ( $active_place_term ) : ?>
					<span class="us-filtercrumb__sep">&middot;</span>
					<a href="<?php echo esc_url( add_query_arg( 'theme', $active_theme_term->slug, $base_link ) ); ?>">&larr; All <?php echo esc_html( $active_theme_term->name ); ?> in <?php echo esc_html( $term->name ); ?></a>
				<?php endif; ?>
			</div>

			<div class="us-section__head">
				<h2 class="us-section__title us-serif"><?php echo esc_html( $active_place_term ? $active_theme_term->name . ' in ' . $active_place_term->name . '.' : $active_theme_term->name . ' in ' . $term->name . '.' ); ?></h2>
				<span class="us-mono" style="color:var(--global-palette5,#888)"><?php echo esc_html( (int) $aq->found_posts ); ?> results</span>
			</div>

			<div class="us-articles">
				<?php
				if ( $aq->have_posts() ) :
					while ( $aq->have_posts() ) :
						$aq->the_post();
						$thumb       = get_the_post_thumbnail_url( get_the_ID(), 'large' );
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
								<div class="us-card__pillars us-mono" style="color:var(--global-palette5,#888)"><?php echo esc_html( $place_names ? implode( ' Â· ', array_map( 'wp_strip_all_tags', $place_names ) ) : '' ); ?></div>
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
				$extra = array( 'theme' => $active_theme_term->slug );
				if ( $active_place_term ) {
					$extra['place'] = $active_place_term->slug;
				}
				echo '<div class="us-pagination">';
				echo paginate_links( array(
					'total'     => $aq->max_num_pages,
					'current'   => $paged,
					'add_args'  => $extra,
					'prev_text' => '&larr;',
					'next_text' => '&rarr;',
				) );
				echo '</div>';
			}
			?>

		<?php else : ?>

		<!-- ============ GRIGLIA LOCALITÀ ============ -->
		<div class="us-grid__head">
			<h2 class="us-grid__title us-serif">Localities in <?php echo esc_html( $term->name ); ?>.</h2>
		</div>

		<div class="us-grid">
			<?php
			$shown = 0;
			foreach ( $all_localities as $loc ) :
				$loc_post_ids = get_posts( array( 'post_type' => 'post', 'posts_per_page' => -1, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => 'destination', 'field' => 'term_id', 'terms' => $loc->term_id ) ) ) );
				$loc_themes    = $loc_post_ids ? wp_get_object_terms( $loc_post_ids, 'theme' ) : array();
				$loc_theme_ids = wp_list_pluck( is_array( $loc_themes ) ? $loc_themes : array(), 'term_id' );

				$shown++;

				$loc_img    = $us_field( 'image', $loc );
				$loc_accent = $us_field( 'accent_color', $loc );
				$loc_accent = $loc_accent ? $loc_accent : '#5b6b73';
				$loc_desc   = $us_field( 'descriptor', $loc );
				$loc_exc    = $us_field( 'card_excerpt', $loc );
				$loc_link   = get_term_link( $loc );
				?>
				<a class="us-card" href="<?php echo esc_url( $loc_link ); ?>">
					<div class="us-card__tile" style="background:<?php echo esc_attr( $loc_accent ); ?>">
						<?php if ( $loc_img && ! empty( $loc_img['url'] ) ) : ?>
							<div class="us-card__tile-bg" style="background-image:url('<?php echo esc_url( $loc_img['url'] ); ?>')"></div>
						<?php endif; ?>
						<div class="us-card__tile-stripes"></div>
						<div class="us-card__tile-label us-mono">img &middot; <?php echo esc_html( $loc->name ); ?></div>
					</div>
					<div class="us-card__row">
						<h3 class="us-card__name us-serif"><?php echo esc_html( $loc->name ); ?></h3>
						<span class="us-card__count us-mono"><?php echo esc_html( $loc->count ); ?> pieces</span>
					</div>
					<?php if ( $loc_desc ) : ?>
						<div class="us-card__descriptor us-mono"><?php echo esc_html( $loc_desc ); ?></div>
					<?php endif; ?>
					<?php if ( $loc_exc ) : ?>
						<p class="us-card__excerpt"><?php echo esc_html( $loc_exc ); ?></p>
					<?php endif; ?>
					<div class="us-card__foot">
						<div class="us-card__pillars us-mono">
							<?php
							foreach ( $loc_theme_ids as $tid ) :
								if ( empty( $theme_map[ $tid ] ) ) {
									continue;
								}
								$tm    = $theme_map[ $tid ];
								$color = $tm['accent'] ? $tm['accent'] : 'inherit';
								?>
								<span class="us-card__pillar" style="color:<?php echo esc_attr( $color ); ?>">#<?php echo esc_html( str_replace( ' ', '', $tm['name'] ) ); ?></span>
							<?php endforeach; ?>
						</div>
						<span class="us-card__browse us-mono">browse &rarr;</span>
					</div>
				</a>
				<?php
			endforeach;
			if ( 0 === $shown ) {
				echo '<p>No localities to show yet.</p>';
			}
			?>
		</div>

		<?php endif; ?>

		<?php if ( $bottom_text ) : ?>
			<section class="us-bottom">
				<div class="us-longtext" id="us-longtext"><?php echo wp_kses_post( $bottom_text ); ?></div>
				<button class="us-longtext__toggle us-mono" type="button" hidden>read more &darr;</button>
			</section>
		<?php endif; ?>

	</div>

	<?php
	/* =====================================================
	   RAMO LOCALITÀ — articoli + sidebar "More in ..."
	   ===================================================== */
else :

	$subtitle    = $us_field( 'subtitle', $term );
	$intro       = $us_field( 'intro', $term );
	$image       = $us_field( 'image', $term );
	$accent      = $us_field( 'accent_color', $term );
	$accent      = $accent ? $accent : '#5b6b73';
	$h1_override = $us_field( 'h1_override', $term );
	$h1_text     = $h1_override ? $h1_override : $term->name . '.';
	$bottom_text = $us_field( 'bottom_text', $term );

	$parent      = $term->parent ? get_term( $term->parent, 'destination' ) : null;
	$piece_count = (int) $term->count;

	$themes    = get_terms( array( 'taxonomy' => 'theme', 'hide_empty' => false, 'orderby' => 'name' ) );
	$theme_map = $us_theme_map();

	$base_link = get_term_link( $term );

	// Query articoli (con filtro tema opzionale + paginazione).
	$paged   = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
	$art_tax = array( array( 'taxonomy' => 'destination', 'field' => 'term_id', 'terms' => $term->term_id ) );
	if ( $active_theme ) {
		$th_obj = get_term_by( 'slug', $active_theme, 'theme' );
		if ( $th_obj ) {
			$art_tax['relation'] = 'AND';
			$art_tax[]           = array( 'taxonomy' => 'theme', 'field' => 'term_id', 'terms' => $th_obj->term_id );
		}
	}
	$aq = new WP_Query( array(
		'post_type'      => 'post',
		'posts_per_page' => (int) get_option( 'posts_per_page' ),
		'paged'          => $paged,
		'tax_query'      => $art_tax,
	) );

	// Località sorelle (stessa regione, esclusa quella corrente).
	$siblings = $parent ? get_terms( array( 'taxonomy' => 'destination', 'parent' => $parent->term_id, 'hide_empty' => false, 'orderby' => 'count', 'order' => 'DESC', 'exclude' => array( $term->term_id ) ) ) : array();
	$siblings = is_array( $siblings ) ? $siblings : array();
	?>

	<div class="us-wrap">

		<div class="us-breadcrumb us-mono">
			<?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?>
			<?php if ( $parent ) : ?>&middot; <?php echo esc_html( $parent->slug ); ?><?php endif; ?>
			&middot; <?php echo esc_html( $term->slug ); ?>
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
					<?php if ( $parent ) : ?>dispatch &middot; in <?php echo esc_html( $parent->name ); ?>&nbsp;&mdash;&nbsp;<?php endif; ?>
					<?php echo esc_html( $piece_count ); ?> dispatches
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

		<!-- ============ FILTRO PER PILLAR ============ -->
		<div class="us-filter__kicker us-mono">filter &middot; by pillar</div>
		<div class="us-filter__head">
			<h2 class="us-filter__q us-serif">What are you looking for?</h2>
			<div class="us-mono" style="color:var(--global-palette5,#888)"><?php echo esc_html( $piece_count ); ?> dispatches</div>
		</div>

		<div class="us-chips">
			<a class="us-chip <?php echo $active_theme ? '' : 'us-chip--active'; ?>" href="<?php echo esc_url( $base_link ); ?>">
				All <span class="us-chip__count"><?php echo esc_html( $piece_count ); ?></span>
			</a>
			<?php
			if ( is_array( $themes ) ) :
				foreach ( $themes as $th ) :
					$th_count = $us_count( $term->term_id, false, $th->term_id );
					if ( $th_count < 1 ) {
						continue;
					}
					$th_link   = add_query_arg( 'theme', $th->slug, $base_link );
					$is_active = ( $active_theme === $th->slug );
					?>
					<a class="us-chip <?php echo $is_active ? 'us-chip--active' : ''; ?>" href="<?php echo esc_url( $th_link ); ?>">
						#<?php echo esc_html( str_replace( ' ', '', $th->name ) ); ?>
						<span class="us-chip__count"><?php echo esc_html( $th_count ); ?></span>
					</a>
					<?php
				endforeach;
			endif;
			?>
		</div>

		<!-- ============ ARTICOLI + SIDEBAR ============ -->
		<div class="us-layout">

			<div class="us-main">
				<div class="us-section__head">
					<h2 class="us-section__title us-serif">Dispatches from <?php echo esc_html( $term->name ); ?>.</h2>
					<span class="us-mono" style="color:var(--global-palette5,#888)"><?php echo esc_html( (int) $aq->found_posts ); ?> results</span>
				</div>

				<div class="us-articles">
					<?php
					if ( $aq->have_posts() ) :
						while ( $aq->have_posts() ) :
							$aq->the_post();
							$thumb    = get_the_post_thumbnail_url( get_the_ID(), 'large' );
							$p_themes = get_the_terms( get_the_ID(), 'theme' );
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
									<div class="us-card__pillars us-mono">
										<?php
										if ( is_array( $p_themes ) ) :
											foreach ( $p_themes as $pt ) :
												$color = ! empty( $theme_map[ $pt->term_id ]['accent'] ) ? $theme_map[ $pt->term_id ]['accent'] : 'inherit';
												?>
												<span class="us-card__pillar" style="color:<?php echo esc_attr( $color ); ?>">#<?php echo esc_html( str_replace( ' ', '', $pt->name ) ); ?></span>
											<?php endforeach; endif; ?>
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
					echo '<div class="us-pagination">';
					echo paginate_links( array(
						'total'     => $aq->max_num_pages,
						'current'   => $paged,
						'add_args'  => $active_theme ? array( 'theme' => $active_theme ) : false,
						'prev_text' => '&larr;',
						'next_text' => '&rarr;',
					) );
					echo '</div>';
				}
				?>
			</div>

			<aside class="us-aside">
				<div class="us-aside__box">
					<h2 class="us-aside__title us-serif">More in <?php echo esc_html( $parent ? $parent->name : 'Sicily' ); ?></h2>
					<?php
					if ( $siblings ) :
						foreach ( $siblings as $sib ) :
							$sib_img    = $us_field( 'image', $sib );
							$sib_accent = $us_field( 'accent_color', $sib );
							$sib_accent = $sib_accent ? $sib_accent : '#5b6b73';
							$sib_link   = get_term_link( $sib );
							?>
							<a class="us-sib" href="<?php echo esc_url( $sib_link ); ?>">
								<span class="us-sib__dot" style="background:<?php echo esc_attr( $sib_accent ); ?>">
									<?php if ( $sib_img && ! empty( $sib_img['url'] ) ) : ?>
										<span class="us-sib__dot-bg" style="background-image:url('<?php echo esc_url( $sib_img['url'] ); ?>')"></span>
									<?php endif; ?>
								</span>
								<span>
									<span class="us-sib__name us-serif"><?php echo esc_html( $sib->name ); ?></span><br>
									<span class="us-sib__count us-mono"><?php echo esc_html( $sib->count ); ?> pieces</span>
								</span>
							</a>
							<?php
						endforeach;
						if ( $parent ) :
							?>
							<a class="us-sib" href="<?php echo esc_url( get_term_link( $parent ) ); ?>" style="border-bottom:none">
								<span class="us-sib__name us-mono" style="color:#1b2a4a">all of <?php echo esc_html( $parent->name ); ?> &rarr;</span>
							</a>
							<?php
						endif;
					else :
						echo '<p class="us-sib__count us-mono">No other destinations yet.</p>';
					endif;
					?>
				</div>
			</aside>

		</div>

		<?php if ( $bottom_text ) : ?>
			<section class="us-bottom">
				<div class="us-longtext" id="us-longtext"><?php echo wp_kses_post( $bottom_text ); ?></div>
				<button class="us-longtext__toggle us-mono" type="button" hidden>read more &darr;</button>
			</section>
		<?php endif; ?>

	</div>

	<?php
endif;
?>

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

<?php
/**
 * Regole SEO del tema.
 *
 * @package UnmappedSicily
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical pulito per le viste filtrate (?theme=) degli archivi destination.
 *
 * Su una URL tipo /destination/western-sicily/palermo/?theme=nature-wild
 * il canonical deve puntare alla versione SENZA query string:
 * /destination/western-sicily/palermo/
 * Così i segnali SEO si consolidano sulla pagina principale invece di
 * disperdersi sulle varianti filtrate (niente noindex, che invece le scarterebbe).
 *
 * Ci agganciamo al canonical che Yoast genera già, per non averne due in pagina.
 * L'eventuale paginazione (/page/2/) viene preservata.
 */
add_filter( 'wpseo_canonical', 'unmapped_clean_filtered_canonical' );

function unmapped_clean_filtered_canonical( $canonical ) {

	// Casi gestiti:
	//  - archivio destination con ?theme=    (filtro pillar)
	//  - archivio theme con ?region= o ?place= (filtro luogo)
	$on_destination = is_tax( 'destination' ) && ( isset( $_GET['theme'] ) || isset( $_GET['place'] ) );
	$on_theme       = is_tax( 'theme' ) && ( isset( $_GET['region'] ) || isset( $_GET['place'] ) );

	if ( ! $on_destination && ! $on_theme ) {
		return $canonical;
	}

	$term = get_queried_object();
	if ( ! $term || is_wp_error( $term ) ) {
		return $canonical;
	}

	$link = get_term_link( $term );
	if ( is_wp_error( $link ) ) {
		return $canonical;
	}

	// Preserva la paginazione, se presente.
	$paged = (int) get_query_var( 'paged' );
	if ( $paged > 1 ) {
		$link = user_trailingslashit( trailingslashit( $link ) . 'page/' . $paged, 'paged' );
	}

	return $link;
}

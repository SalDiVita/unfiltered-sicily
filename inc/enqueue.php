<?php
/**
 * Caricamento di CSS e JS.
 *
 * @package UnmappedSicily
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carica lo stylesheet del child theme.
 *
 * NOTA su Kadence: il tema parent carica i propri stili da solo
 * (in modo dinamico e ottimizzato), quindi NON serve accodare
 * manualmente lo style.css del parent. Basta caricare quello del child,
 * che viene letto dopo e quindi sovrascrive Kadence dove serve.
 */
add_action( 'wp_enqueue_scripts', 'unmapped_enqueue_assets' );

function unmapped_enqueue_assets() {

	// CSS del child theme (lo style.css nella root del tema).
	wp_enqueue_style(
		'unmapped-style',
		get_stylesheet_uri(),
		array(),            // nessuna dipendenza: Kadence gestisce i propri stili
		UNMAPPED_VERSION    // versione → cache busting
	);

	/**
	 * JS del child theme (opzionale).
	 * Crea il file /assets/js/main.js e decommenta quando ti serve.
	 */
	// wp_enqueue_script(
	// 	'unmapped-main',
	// 	UNMAPPED_URI . '/assets/js/main.js',
	// 	array(),
	// 	UNMAPPED_VERSION,
	// 	true // carica nel footer
	// );
}

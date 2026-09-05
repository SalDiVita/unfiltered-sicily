<?php
/**
 * Unmapped Sicily — Child theme di Kadence
 *
 * Questo file fa SOLO da loader: include i moduli dentro /inc/.
 * Non aggiungere logica qui — crea un nuovo file in /inc/ e includilo.
 *
 * @package UnmappedSicily
 */

// Blocca l'accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Costanti del tema. Comode per i path e per il versioning degli asset
 * (cambiando la versione forzi il refresh della cache del browser).
 */
define( 'UNMAPPED_VERSION', '1.2.0' );
define( 'UNMAPPED_DIR', get_stylesheet_directory() );
define( 'UNMAPPED_URI', get_stylesheet_directory_uri() );

/**
 * Carica i moduli del tema.
 * Aggiungi qui i nuovi file man mano che il tema cresce.
 */
require_once UNMAPPED_DIR . '/inc/enqueue.php';     // CSS e JS
require_once UNMAPPED_DIR . '/inc/setup.php';       // Setup e supporti del tema
require_once UNMAPPED_DIR . '/inc/taxonomies.php';  // destination + theme
require_once UNMAPPED_DIR . '/inc/seo.php';         // canonical viste filtrate

/**
 * MODULI FUTURI (decommentare quando li creeremo):
 *
 * require_once UNMAPPED_DIR . '/inc/term-meta.php';    // intro/hero/SEO sui termini
 * require_once UNMAPPED_DIR . '/inc/template-tags.php'; // funzioni helper per i template
 */

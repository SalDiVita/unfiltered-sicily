<?php
/**
 * Setup del tema e supporti.
 *
 * @package UnmappedSicily
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carica il text domain per la traduzione delle stringhe del child theme.
 * Metti i file .po/.mo dentro /languages/ se in futuro localizzi il tema.
 */
add_action( 'after_setup_theme', 'unmapped_setup' );

function unmapped_setup() {
	load_child_theme_textdomain( 'unmapped-sicily', UNMAPPED_DIR . '/languages' );
}

/**
 * Spazio per i tuoi hook futuri.
 *
 * Esempi di cose che metterai qui crescendo:
 * - register_nav_menus() per menu custom
 * - add_image_size() per formati immagine dedicati agli hub
 * - filtri sulle query degli archivi tassonomia
 *
 * Lascio lo scheletro pronto, da riempire quando serve.
 */

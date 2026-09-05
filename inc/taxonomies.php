<?php
/**
 * Registrazione tassonomie custom: destination + theme + format.
 *
 * - destination : geografia, GERARCHICA (regione › località).
 * - theme       : i 4 pillar tematici, vocabolario chiuso (UI a checkbox).
 *
 * Questo file REGISTRA le tassonomie (il contenitore). I termini
 * (Western Sicily, Food Truth, le località...) si creano a mano dalla
 * bacheca, sotto Articoli → Destinations / Themes, oppure via n8n.
 *
 * @package UnmappedSicily
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
   REGISTRAZIONE
   ========================================================= */
add_action( 'init', 'unmapped_register_taxonomies' );

function unmapped_register_taxonomies() {

	/* ---- destination (geografia, gerarchica) ---- */
	register_taxonomy(
		'destination',
		array( 'post' ),
		array(
			'labels'            => array(
				'name'              => 'Destinations',
				'singular_name'     => 'Destination',
				'menu_name'         => 'Destinations',
				'all_items'         => 'All destinations',
				'parent_item'       => 'Parent region',
				'parent_item_colon' => 'Parent region:',
				'edit_item'         => 'Edit destination',
				'update_item'       => 'Update destination',
				'add_new_item'      => 'Add new destination',
				'new_item_name'     => 'New destination name',
				'search_items'      => 'Search destinations',
				'not_found'         => 'No destinations found',
			),

			// true = comportamento "categoria": parent/child + checkbox.
			'hierarchical'      => true,

			'public'            => true,

			// CRITICO per n8n e per l'editor a blocchi.
			'show_in_rest'      => true,

			// Colonna "Destinations" nella lista articoli.
			'show_admin_column' => true,

			'rewrite'           => array(
				'slug'         => 'destination',
				'hierarchical' => true,   // annida i figli: /destination/western-sicily/palermo/
				'with_front'   => false,
			),
		)
	);

	/* ---- theme (4 pillar, vocabolario chiuso) ---- */
	register_taxonomy(
		'theme',
		array( 'post' ),
		array(
			'labels'            => array(
				'name'          => 'Themes',
				'singular_name' => 'Theme',
				'menu_name'     => 'Themes',
				'all_items'     => 'All themes',
				'edit_item'     => 'Edit theme',
				'update_item'   => 'Update theme',
				'add_new_item'  => 'Add new theme',
				'new_item_name' => 'New theme name',
				'search_items'  => 'Search themes',
				'not_found'     => 'No themes found',
			),

			/*
			 * true non per la gerarchia, ma per ottenere l'interfaccia a
			 * CHECKBOX invece del campo testo libero: così l'editor sceglie
			 * tra i temi esistenti e non ne crea di nuovi per sbaglio.
			 */
			'hierarchical'      => true,

			'public'            => true,
			'show_in_rest'      => true,   // serve a n8n e all'editor
			'show_admin_column' => true,

			'rewrite'           => array(
				'slug'       => 'theme',
				'with_front' => false,
			),
		)
	);

	/* ---- format (tipo di pezzo: Itinerary / Guide / Story / List) ---- */
	register_taxonomy(
		'format',
		array( 'post' ),
		array(
			'labels'            => array(
				'name'          => 'Formats',
				'singular_name' => 'Format',
				'menu_name'     => 'Formats',
				'all_items'     => 'All formats',
				'edit_item'     => 'Edit format',
				'update_item'   => 'Update format',
				'add_new_item'  => 'Add new format',
				'new_item_name' => 'New format name',
				'search_items'  => 'Search formats',
				'not_found'     => 'No formats found',
			),

			/*
			 * Asse "che tipo di pezzo è" (non l'argomento): un itinerario
			 * attraversa più temi, quindi vive su un asse separato. true per
			 * la UI a checkbox e il vocabolario chiuso, come per theme.
			 */
			'hierarchical'      => true,

			'public'            => true,
			'show_in_rest'      => true,   // serve a n8n e all'editor
			'show_admin_column' => true,

			'rewrite'           => array(
				'slug'       => 'format',
				'with_front' => false,
			),
		)
	);
}

/* =========================================================
   FLUSH DEGLI URL (all'attivazione del tema)

   Non crea termini: rigenera solo le rewrite rules una volta,
   così gli URL puliti (/destination/..., /theme/...) funzionano
   subito senza passare da Impostazioni → Permalink.
   ========================================================= */
add_action( 'after_switch_theme', 'unmapped_flush_rewrite' );

function unmapped_flush_rewrite() {
	unmapped_register_taxonomies();
	flush_rewrite_rules();
}

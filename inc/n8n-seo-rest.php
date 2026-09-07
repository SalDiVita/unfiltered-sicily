<?php
/**
 * Accesso REST ai campi SEO di Yoast per l'automazione n8n.
 *
 * PERCHÉ QUESTO FILE ESISTE
 * -------------------------
 * n8n deve scrivere il SEO di due tipi di pagina che Yoast NON espone via
 * REST di default. Questo modulo apre le due porte, e SOLO quelle.
 *
 *  1) TERMINI (pagine-luogo: destination, theme).
 *     Yoast salva il SEO dei termini in un'UNICA opzione serializzata
 *     (`wpseo_taxonomy_meta`), senza endpoint REST ufficiale. Qui esponiamo
 *     una rotta custom — POST /wp-json/unmapped/v1/term-seo — che scrive
 *     seo_title e meta_description (+ focus keyword opzionale) sul termine.
 *
 *  2) POST (articoli).
 *     Yoast salva il SEO dei post in post-meta "protette" (underscore:
 *     _yoast_wpseo_title, _yoast_wpseo_metadesc, _yoast_wpseo_focuskw), che
 *     il core NON lascia scrivere via REST finché non le registri. Qui le
 *     registriamo con show_in_rest: così entrano nel campo `meta` della
 *     normale POST /wp-json/wp/v2/posts — nessun endpoint custom necessario.
 *
 * COSA QUESTO FILE NON FA
 * -----------------------
 * I 6 campi ACF dei termini (subtitle, intro, card_excerpt, descriptor,
 * h1_override, bottom_text) NON passano da qui: il gruppo ACF ha già
 * `show_in_rest`, quindi si scrivono con la chiave `acf` sulla normale
 * PATCH del termine. Questo file tocca SOLTANTO Yoast.
 *
 * IMPORTANTE — I TOKEN NON SI RIPULISCONO
 * ---------------------------------------
 * I valori `seo_title` arrivano con le variabili Yoast LETTERALI dentro
 * (es. "Discover Catania %%page%% %%sep%% %%sitename%%"). Vanno salvati
 * così come sono: li espande Yoast al render. sanitize_text_field non
 * tocca i %% %%, quindi restano intatti.
 *
 * @package UnmappedSicily
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
   1) TERMINI — endpoint REST custom per il SEO di Yoast

   POST /wp-json/unmapped/v1/term-seo
   Body JSON:
   {
     "term_id": 123,
     "taxonomy": "destination",
     "seo_title": "Discover Catania %%page%% %%sep%% %%sitename%%",
     "meta_description": "Where locals actually eat in Catania...",
     "focus_keyword": "catania"          // opzionale
   }
   Scrive solo i campi effettivamente passati (merge, non azzeramento).
   ========================================================= */

add_action( 'rest_api_init', 'unmapped_register_term_seo_route' );

function unmapped_register_term_seo_route() {

	register_rest_route(
		'unmapped/v1',
		'/term-seo',
		array(
			'methods'             => 'POST',
			'permission_callback' => function () {
				// Stessa soglia richiesta per editare le tassonomie in bacheca.
				return current_user_can( 'manage_categories' );
			},
			'args'                => array(
				'term_id'          => array(
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
				'taxonomy'         => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_key',
				),
				'seo_title'        => array(
					'required'          => false,
					'type'              => 'string',
					// NB: sanitize_text_field NON tocca i token %%...%%.
					'sanitize_callback' => 'sanitize_text_field',
				),
				'meta_description' => array(
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'focus_keyword'    => array(
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
			'callback'            => 'unmapped_write_term_seo',
		)
	);
}

function unmapped_write_term_seo( WP_REST_Request $request ) {

	$term_id  = (int) $request['term_id'];
	$taxonomy = (string) $request['taxonomy'];

	// Il termine deve esistere DENTRO quella tassonomia.
	$term = get_term( $term_id, $taxonomy );
	if ( ! $term || is_wp_error( $term ) ) {
		return new WP_Error(
			'unmapped_term_not_found',
			"Termine {$term_id} non trovato nella tassonomia '{$taxonomy}'.",
			array( 'status' => 404 )
		);
	}

	// Struttura dell'opzione Yoast: [ taxonomy ][ term_id ][ chiave ] = valore.
	$option_key = 'wpseo_taxonomy_meta';
	$all        = get_option( $option_key );
	if ( ! is_array( $all ) ) {
		$all = array();
	}
	if ( empty( $all[ $taxonomy ] ) || ! is_array( $all[ $taxonomy ] ) ) {
		$all[ $taxonomy ] = array();
	}
	if ( empty( $all[ $taxonomy ][ $term_id ] ) || ! is_array( $all[ $taxonomy ][ $term_id ] ) ) {
		$all[ $taxonomy ][ $term_id ] = array();
	}

	// Scriviamo SOLO i campi effettivamente passati: merge, mai azzeramento.
	$written = array();

	if ( $request->has_param( 'seo_title' ) ) {
		$all[ $taxonomy ][ $term_id ]['wpseo_title'] = (string) $request['seo_title'];
		$written['wpseo_title']                      = $all[ $taxonomy ][ $term_id ]['wpseo_title'];
	}
	if ( $request->has_param( 'meta_description' ) ) {
		$all[ $taxonomy ][ $term_id ]['wpseo_desc'] = (string) $request['meta_description'];
		$written['wpseo_desc']                      = $all[ $taxonomy ][ $term_id ]['wpseo_desc'];
	}
	if ( $request->has_param( 'focus_keyword' ) ) {
		$all[ $taxonomy ][ $term_id ]['wpseo_focuskw'] = (string) $request['focus_keyword'];
		$written['wpseo_focuskw']                      = $all[ $taxonomy ][ $term_id ]['wpseo_focuskw'];
	}

	if ( empty( $written ) ) {
		return new WP_Error(
			'unmapped_nothing_to_write',
			'Nessun campo SEO fornito (seo_title, meta_description o focus_keyword).',
			array( 'status' => 400 )
		);
	}

	// Un solo update_option scrive l'intero blob aggiornato.
	update_option( $option_key, $all );

	return new WP_REST_Response(
		array(
			'success'  => true,
			'term_id'  => $term_id,
			'taxonomy' => $taxonomy,
			'written'  => $written,
		),
		200
	);
}

/* =========================================================
   2) POST — espone le meta SEO di Yoast alla REST

   Dopo questa registrazione, i tre campi si scrivono nel campo
   `meta` della normale POST /wp-json/wp/v2/posts:

   {
     "title": "...",
     "content": "<h2>...</h2>",
     "status": "draft",
     "meta": {
       "_yoast_wpseo_title":    "Testa breve %%page%% %%sep%% %%sitename%%",
       "_yoast_wpseo_metadesc": "Meta description dell'articolo...",
       "_yoast_wpseo_focuskw":  "main keyword"
     }
   }

   Priorità 99: gira DOPO la registrazione interna di Yoast, così la
   nostra esposizione REST (show_in_rest) è quella che vince.
   ========================================================= */

add_action( 'init', 'unmapped_expose_post_seo_meta', 99 );

function unmapped_expose_post_seo_meta() {

	$keys = array(
		'_yoast_wpseo_title',
		'_yoast_wpseo_metadesc',
		'_yoast_wpseo_focuskw',
	);

	foreach ( $keys as $key ) {
		register_post_meta(
			'post',
			$key,
			array(
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'string',
				// Meta "protetta" (underscore): serve un auth_callback esplicito
				// o la REST la rifiuta in scrittura.
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}

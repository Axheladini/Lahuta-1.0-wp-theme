<?php
/**
 * API Only theme functions.
 *
 * Everything here is aimed at one goal: WordPress as a headless data source,
 * consumed via the REST API (and/or WPGraphQL if you install that plugin).
 * No templates, no widgets, no menus rendered on the front-end.
 */

defined( 'ABSPATH' ) || exit;

/**
 * ---------------------------------------------------------------------
 * 1. Basic theme setup
 * ---------------------------------------------------------------------
 * Keep just enough support so post data (featured images, titles) is
 * useful over the API. No nav-menu / widget-area registration since
 * there's no front-end to place them in.
 */
function api_only_theme_setup() {

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'comment-list', 'comment-form', 'search-form', 'gallery', 'caption' ) );
	add_theme_support( 'custom_logo', array(
		'height'      => 100,
		'width'       => 400,
		'flex-height' => true,
		'flex-width'  => true,
	) );

	/* Register menues */
	register_nav_menus( array(
	  'primary' => __( 'Top Menu', 'api-only' ),
	  'footer'  => __( 'Footer Menu', 'api-only' ),
    ));

}
add_action( 'after_setup_theme', 'api_only_theme_setup' );

/**
 * ---------------------------------------------------------------------
 * 2. Strip front-end bloat from <head> and disable things a headless
 *    site never needs.
 * ---------------------------------------------------------------------
 */
function api_only_strip_head_bloat() {
	remove_action( 'wp_head', 'rsd_link' );                     // EditURI / RSD
	remove_action( 'wp_head', 'wlwmanifest_link' );              // Windows Live Writer
	remove_action( 'wp_head', 'wp_generator' );                  // WP version number
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );          // Shortlink
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );
	remove_action( 'wp_head', 'rest_output_link_wp_head' );      // don't need the REST <link> hint either
	remove_action( 'template_redirect', 'rest_output_link_header', 11 );
}
add_action( 'init', 'api_only_strip_head_bloat' );

/**
 * Disable XML-RPC. If you specifically need it (e.g. some old mobile
 * apps use it for auth), comment this out.
 */
add_filter( 'xmlrpc_enabled', '__return_false' );

/**
 * Disable the block editor's REST-based front-end assets and emoji script
 * that WordPress otherwise loads on every front-end request.
 */
add_action( 'wp_enqueue_scripts', function () {
	wp_dequeue_script( 'wp-embed' );
}, 100 );

/**
 * ---------------------------------------------------------------------
 * 3. Redirect all normal front-end requests to the REST API root.
 * ---------------------------------------------------------------------
 * Anything that isn't wp-admin, wp-login, REST (/wp-json/), a cron
 * request, or an XML-RPC request gets redirected. This keeps `/`,
 * `/2026/09/some-post/`, `/?p=123`, etc. from rendering a theme template
 * that doesn't really exist for humans anyway.
 *
 * Set API_ONLY_REDIRECT_FRONTEND to false in wp-config.php if you'd
 * rather return a 404/JSON instead of a redirect (e.g. for SEO reasons
 * during a migration).
 */
function api_only_redirect_frontend() {
	if ( is_admin() ) {
		return;
	}
	if ( is_admin() || is_customize_preview() ) {
		return;
	}

	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		return;
	}

	if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
		return;
	}

	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

	// Let wp-login.php, wp-cron.php, wp-json, and any real files (e.g. uploads) through.
	if ( preg_match( '#^/(wp-login\.php|wp-cron\.php|wp-json|wp-content|wp-includes)#', $request_uri ) ) {
		return;
	}

	//$should_redirect = defined( 'API_ONLY_REDIRECT_FRONTEND' ) ? API_ONLY_REDIRECT_FRONTEND : true;

	/*if ( $should_redirect ) {
		wp_safe_redirect( rest_url(), 302 );
		exit;
	}*/

	/* Solution for SPA websites */

    if (is_front_page() && !is_admin()) { $file = get_stylesheet_directory() . '/index.html'; if (file_exists($file)) { header('Content-Type: text/html; charset=UTF-8'); readfile($file); exit; } }

	}
add_action( 'template_redirect', 'api_only_redirect_frontend' );

/**
 * ---------------------------------------------------------------------
 * 4. CORS for the REST API.
 * ---------------------------------------------------------------------
 * Your frontend almost certainly lives on a different origin
 * (e.g. https://app.example.com calling https://cms.example.com/wp-json/).
 * Configure allowed origins in wp-config.php:
 *
 *   define( 'API_ONLY_ALLOWED_ORIGINS', 'https://app.example.com,https://staging.example.com' );
 *
 * Leaving it undefined allows all origins ('*'), which is fine for
 * public read-only content but NOT recommended once you accept
 * authenticated write requests.
 */
function api_only_rest_cors() {
	remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );

	add_filter( 'rest_pre_serve_request', function ( $value ) {
		$allowed_origins = defined( 'API_ONLY_ALLOWED_ORIGINS' )
			? array_map( 'trim', explode( ',', API_ONLY_ALLOWED_ORIGINS ) )
			: array();

		$origin = get_http_origin();

		if ( ! empty( $allowed_origins ) && $origin ) {
			if ( in_array( $origin, $allowed_origins, true ) ) {
				header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
			}
		} else {
			header( 'Access-Control-Allow-Origin: *' );
		}

		header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
		header( 'Access-Control-Allow-Credentials: true' );
		header( 'Access-Control-Allow-Headers: Authorization, X-WP-Nonce, Content-Type' );

		return $value;
	} );
}
add_action( 'rest_api_init', 'api_only_rest_cors', 15 );

/**
 * ---------------------------------------------------------------------
 * 5. Make REST responses more useful for a headless frontend.
 * ---------------------------------------------------------------------
 */

// Add the featured image URL (a few sizes) directly on post/page REST responses.
function api_only_add_featured_image_to_rest( $response, $post ) {
	$thumb_id = get_post_thumbnail_id( $post->ID );

	if ( $thumb_id ) {
		$response->data['featured_image'] = array(
			'full'      => wp_get_attachment_image_url( $thumb_id, 'full' ),
			'large'     => wp_get_attachment_image_url( $thumb_id, 'large' ),
			'medium'    => wp_get_attachment_image_url( $thumb_id, 'medium' ),
			'thumbnail' => wp_get_attachment_image_url( $thumb_id, 'thumbnail' ),
			'alt'       => get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ),
		);
	} else {
		/* Retrieve the featured image thumbnail, or use a placeholder if not available. */
		$placeholder = get_template_directory_uri() . '/assets/images/placeholder.jpg';
		$response->data['featured_image'] = array(
			'full'      => $placeholder,
			'large'     => $placeholder,
			'medium'    => $placeholder,
			'thumbnail' => $placeholder,
			'alt'       => '',
		);
	}

	return $response;
}
foreach ( array( 'post', 'page' ) as $api_only_pt ) {
	add_filter( "rest_prepare_{$api_only_pt}", 'api_only_add_featured_image_to_rest', 10, 2 );
}



/**
 * Make sure any custom post types you register are exposed via REST.
 * Example (put your own CPTs here, or in a separate mu-plugin):
 *
 * register_post_type( 'product', array(
 *     'public'       => true,
 *     'show_in_rest' => true,
 *     'rest_base'    => 'products',
 *     'supports'     => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
 * ) );
 */

/**
 * Expose all post meta registered with register_post_meta() automatically,
 * as long as it was registered with 'show_in_rest' => true. This is just
 * a reminder helper — register your fields like:
 *
 * register_post_meta( 'post', 'subtitle', array(
 *     'type'         => 'string',
 *     'single'       => true,
 *     'show_in_rest' => true,
 * ) );
 */

/**
 * ---------------------------------------------------------------------
 * 6. Example custom REST endpoint.
 * ---------------------------------------------------------------------
 * A small demonstration of a custom namespace/route beyond the default
 * wp/v2 endpoints — handy for aggregating data your frontend needs in
 * one call (e.g. site settings + latest posts).
 */
function api_only_register_routes() {
	
    /* Register site-info REST endpoint */
    register_rest_route(
		'api-only/v1',
		'/site-info',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => '__return_true',
			'callback'            => function () {
                
			 $logo_id  = get_theme_mod( 'custom_logo' );
			 $logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : null;
			 //$logo_test = "test";
			 

				return rest_ensure_response(
					array(
						'name'        => get_bloginfo( 'name' ),
						'description' => get_bloginfo( 'description' ),
						'url'         => home_url(),
						'timezone'    => wp_timezone_string(),
						'logo'    => $logo_url,
					)
				);
			},
		)
	);
    /* End of site-info REST endpoint */
	/* ------------------------------ */
	/* Register menu REST endpoint */
	register_rest_route(
		'api-only/v1',
		'/menu/(?P<location>[a-zA-Z0-9_-]+)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => '__return_true',
			'args'                => array(
				'location' => array( 'required' => true ),
			),
			'callback'            => function ( $request ) {
				$locations = get_nav_menu_locations();
				$location  = $request->get_param( 'location' );

				if ( empty( $locations[ $location ] ) ) {
					return new WP_Error( 'no_menu', 'No menu assigned to this location.', array( 'status' => 404 ) );
				}

				$menu_items = wp_get_nav_menu_items( $locations[ $location ] );
				if ( ! $menu_items ) {
					return rest_ensure_response( array() );
				}

				// Build a flat lookup keyed by item ID, normalizing each URL
				// against the CURRENT home_url() so stale/old-domain links
				// (e.g. left over from a site URL change) resolve correctly.
				$items_by_id = array();
				foreach ( $menu_items as $item ) {
					$items_by_id[ $item->ID ] = array(
						'id'        => $item->ID,
						'title'     => $item->title,
						'rest_url' => rest_url( 'wp/v2/' . $item->object . '/' . $item->object_id ),
						'url'       => home_url( wp_make_link_relative( $item->url ) ),
						'target'    => $item->target ?: '_self',
						'is_custom' => 'custom' === $item->type,
						'type'      => $item->type,        // 'custom' | 'post_type' | 'taxonomy' | 'post_type_archive'
						'object'    => $item->object,       // e.g. 'page', 'post', 'category', or '' for custom links
						'object_id' => $item->object_id ? (int) $item->object_id : null,
						'children'  => array(),
					);
				}

				// Nest children under their parents.
				$tree = array();
				foreach ( $menu_items as $item ) {
					if ( $item->menu_item_parent && isset( $items_by_id[ $item->menu_item_parent ] ) ) {
						$items_by_id[ $item->menu_item_parent ]['children'][] = &$items_by_id[ $item->ID ];
					} else {
						$tree[] = &$items_by_id[ $item->ID ];
					}
				}

				return rest_ensure_response( $tree );
			},
		)
	);
    /* End of menu REST endpoint */	

}
add_action( 'rest_api_init', 'api_only_register_routes' );




function redirect_frontend() {
    if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }

    if (is_front_page()) {
        wp_safe_redirect(
            get_stylesheet_directory_uri() . '/index.html',
            302
        );
        exit;
    }
}
add_action( 'template_redirect', 'redirect_frontend' );


/**
 * ---------------------------------------------------------------------
 * 7. Security niceties for an API-only setup.
 * ---------------------------------------------------------------------
 */

// Don't leak the WP version in the REST index or elsewhere.
add_filter( 'the_generator', '__return_empty_string' );

// Application Passwords (core since WP 5.6) are the standard way to
// authenticate write requests to the REST API from a trusted frontend/
// server. No extra plugin needed — enable them per-user under
// Users > Profile > Application Passwords. For public untrusted clients,
// front the API with OAuth2/JWT via a plugin instead.

<?php
/**
 * Fallback template.
 *
 * This theme has no front-end. Front-end requests are redirected to the
 * REST API root in functions.php (see api_only_redirect_frontend()).
 * This file exists only because WordPress requires index.php in every theme.
 * If it's ever reached, return a small JSON pointer instead of HTML.
 */

/*header( 'Content-Type: application/json; charset=' . get_bloginfo( 'charset' ) );
echo wp_json_encode(
	array(
		'message'  => 'This site runs WordPress headlessly. There is no front-end here.',
		'rest_api' => esc_url_raw( rest_url() ),
	)
);*/

/** * Redirect front-end requests to the theme's index.html. */ wp_safe_redirect( get_stylesheet_directory_uri() . '/index.html', 302 ); exit;
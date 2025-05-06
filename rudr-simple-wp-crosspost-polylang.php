<?php
/*
 * Plugin name: Simple WP Crossposting – Polylang
 * Author: Misha Rudrastyh
 * Author URI: https://rudrastyh.com
 * Description: Adds translations support to crossposting.
 * Plugin URI: https://rudrastyh.com/support/crosspost-with-polylang
 * Version: 1.0
 */

add_filter( 'rudr_swc_pre_request_url', function( $url, $post_id, $blog ) {

	// Polylang check
	if( ! function_exists( 'pll_get_post_language' ) ) {
		return $url;
	}

	// Add post current language first
	$lang = pll_get_post_language( $post_id );
	if( $lang ) {
		$url = add_query_arg( 'lang', $lang, $url );
	}

	// add other languages (if there were crossposted)
	$translations = pll_get_post_translations( $post_id );
	if( $translations ) {
		$blog_id = Rudr_Simple_WP_Crosspost::get_blog_id( $blog );
		foreach( $translations as $lang => $id ) {
			if( $crossposted_id = Rudr_Simple_WP_Crosspost::is_crossposted( $id, $blog_id ) ) {
				$url = add_query_arg( 'translations[' . $lang . ']', $crossposted_id, $url );
			}
		}
	}

	return $url;

}, 10, 3 );

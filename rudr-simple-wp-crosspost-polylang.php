<?php
/*
 * Plugin name: Simple WP Crossposting – Polylang
 * Author: Misha Rudrastyh
 * Author URI: https://rudrastyh.com
 * Description: Adds translations support to crossposting.
 * Plugin URI: https://rudrastyh.com/support/crosspost-with-polylang
 * Version: 1.3
 */

 if( ! class_exists( 'Rudr_Simple_WP_Crosspost_Polylang' ) ) {

 	class Rudr_Simple_WP_Crosspost_Polylang{

 		public function __construct() {

			add_filter( 'rudr_swc_pre_request_url', array( $this, 'post_language_and_translations' ), 25, 3 );
      // taxonomies
			add_filter( 'rudr_swc_pre_term_request_url', array( $this, 'term_language_and_translations' ), 25, 3 );
			add_filter( 'rudr_swc_pre_terms_request_url', array( $this, 'terms_by_post_language' ), 25, 4 );
      // WooCommerce products, SKU connection
      add_filter( 'rudr_swc_pre_products_request_url', array( $this, 'products_by_sku_and_by_language' ), 25, 2 );
      // bulk add-on
      add_filter( 'rudr_swc_pre_batch_request_url', array( $this, 'product_language_to_batch' ), 25, 2 );
      add_action( 'admin_footer', array( $this, 'check_lang_in_bulk_actions' ) );
		}

		// Add post language and translations to the REST API endpoint
		public function post_language_and_translations( $url, $post_id, $blog ) {

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

		}

		// Add term language and translations to the REST API endpoint
		public function term_language_and_translations( $url, $term_id, $blog ) {

			// Polylang check
			if( ! function_exists( 'pll_get_term_language' ) ) {
				return $url;
			}

			// Add post term language first
			$lang = pll_get_term_language( $term_id );
			if( $lang ) {
				$url = add_query_arg( 'lang', $lang, $url );
			}

			$translations = pll_get_term_translations( $term_id );
			if( $translations ) {
				foreach( $translations as $lang => $id ) {
					if( $crossposted_term_id = $this->is_term_crossposted_in_language( $id, $lang, $blog ) ) {
						$url = add_query_arg( 'translations[' . $lang . ']', $crossposted_term_id, $url );
					}
				}
			}

			return $url;

		}

		// when using get_synced_term_ids() function, apply a post language to the REST API endpoint
		public function terms_by_post_language( $url, $slugs, $blog, $post_id ) {
			// Polylang check
			if( ! function_exists( 'pll_get_post_language' ) ) {
				return $url;
			}

			// We are going to get terms depending on a post language they are currently attached to
			$lang = pll_get_post_language( $post_id );
			if( $lang ) {
				$url = add_query_arg( 'lang', $lang, $url );
			}

			return $url;

		}

		// bypass the get_synced_term_ids() function
		private function is_term_crossposted_in_language( $id, $lang, $blog ) {

			$term = get_term( $id );
			if( ! $term ) {
				return 0;
			}

			$taxonomy = get_taxonomy( $term->taxonomy );
			if( ! $taxonomy ) {
				return 0;
			}

			$taxonomy_rest_base = is_string( $taxonomy ) ? $taxonomy : ( $taxonomy->rest_base ? $taxonomy->rest_base : $taxonomy->name );

			$request = wp_remote_get(
				add_query_arg(
					array(
						'slug' => $term->slug,
						'hide_empty' => 0,
						'per_page' => 1,
						'lang' => $lang,
					),
					"{$blog[ 'url' ]}/wp-json/wp/v2/{$taxonomy_rest_base}"
				),
				array(
					'headers' => array(
						'Authorization' => 'Basic ' . base64_encode( "{$blog[ 'login' ]}:{$blog[ 'pwd' ]}" )
					),
					'timeout' => 30,
				)
			);

			$crossposted_term_id = 0;
			if( 'OK' === wp_remote_retrieve_response_message( $request ) ) {
				$crossposted_terms = json_decode( wp_remote_retrieve_body( $request ) );
				if( $crossposted_terms ) {
					$crossposted_term_id = reset( wp_list_pluck( $crossposted_terms, 'id' ) );
				}
			}

			return $crossposted_term_id;

		}

    public function products_by_sku_and_by_language( $url, $product_id ) {

      // Polylang check
			if( ! function_exists( 'pll_get_post_language' ) ) {
				return $url;
			}

			// Add post current language first
			$lang = pll_get_post_language( $product_id );
			if( $lang ) {
				$url = add_query_arg( 'lang', $lang, $url );
			}

      return $url;

    }

    public function product_language_to_batch( $url, $body ) {

      // Polylang check
      if( ! function_exists( 'pll_get_post_language' ) ) {
        return $url;
      }

      // WooCommerce REST API only
      if( false === strpos( $url, 'wc/v3/products/batch' ) ) {
        return $url;
      }

      // find the first product to find out its language
      $first_product_id = ! empty( $body[ 'create' ][0][ 'source_product_id' ] )
        ? $body[ 'create' ][0][ 'source_product_id' ]
        : ( ! empty( $body[ 'update' ][0][ 'source_product_id' ] ) ? $body[ 'update' ][0][ 'source_product_id' ] : 0 );

      if( ! $first_product_id ) {
        return $url;
      }

      // we simply add the language of the first product to URL
      $lang = pll_get_post_language( $first_product_id );
      if( $lang ) {
        $url = add_query_arg( 'lang', $lang, $url );
      }

      return $url;

    }

    public function check_lang_in_bulk_actions() {

      $screen = get_current_screen();

      if( ! class_exists( 'Rudr_Simple_WP_Crosspost' ) || ! function_exists( 'pll_current_language' ) ) {
        return;
      }

      if( ! Rudr_Simple_WP_Crosspost::is_woocommerce() || 'edit-product' !== $screen->id ) {
        return;
      }

      // language is set, all is good
      if( pll_current_language() ) {
        return;
      }

      ?><script>
      jQuery( function( $ ) {

        $( 'select[name="action"]' ).change( function( e ) {
          const bulkAction = $(this).val();

          if( bulkAction.startsWith( 'crosspost_to_' ) ) {
            alert( 'In order to bulk sync products, please select a language first.' );
            $(this).next().prop( 'disabled', true );
          } else {
            $(this).next().prop( 'disabled', false );
          }
        } );
      } );
      </script>
      <?php
    }

	}

	new Rudr_Simple_WP_Crosspost_Polylang;
}

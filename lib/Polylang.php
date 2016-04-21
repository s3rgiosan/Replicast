<?php

/**
 * Add Polylang support
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib
 */

namespace Replicast;

use Replicast\API;

/**
 * Add Polylang support.
 *
 * @since      1.0.0
 * @package    Replicast
 * @subpackage Replicast/lib
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class Polylang {

	/**
	 * The plugin's instance.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @var       \Replicast\Plugin    This plugin's instance.
	 */
	private $plugin;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    \Replicast\Plugin    $plugin    This plugin's instance.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register hooks.
	 *
	 * @since    1.0.0
	 */
	public function register() {

		\add_filter( 'replicast_suppress_object_taxonomies', array( $this, 'suppress_taxonomies' ), 10, 3 );
		\add_filter( 'replicast_get_object_terms',           array( $this, 'get_object_terms_translations' ) );
		\add_filter( 'replicast_prepare_object_for_create',  array( $this, 'prepare_object_translations' ), 10, 2 );
		\add_filter( 'replicast_prepare_object_for_update',  array( $this, 'prepare_object_translations' ), 10, 2 );
		\add_filter( 'replicast_prepare_object_for_create',  array( $this, 'prepare_object_terms_translations' ), 20, 2 );
		\add_filter( 'replicast_prepare_object_for_update',  array( $this, 'prepare_object_terms_translations' ), 20, 2 );
		\add_action( 'replicast_update_object_terms',        array( $this, 'update_object_terms_translations' ) );

	}

	/**
	 * Suppress taxonomies.
	 *
	 * @since     1.0.0
	 * @param     array    $suppressed    Name(s) of the suppressed taxonomies.
	 * @param     array    $taxonomies    List of registered taxonomies.
	 * @param     int      $object_id     The object ID.
	 * @return    array                   Possibly-modified name(s) of the suppressed taxonomies.
	 */
	public function suppress_taxonomies( $suppressed = array(), $taxonomies, $object_id ) {
		return array_merge( array(
			'term_translations',
		), $suppressed );
	}

	/**
	 * Retrieve Polylang terms translations.
	 *
	 * @since     1.0.0
	 * @param     array    $terms    Object terms.
	 * @return    array              Possibly-modified object terms.
	 */
	public function get_object_terms_translations( $terms ) {

		foreach ( $terms as $term ) {

			if ( in_array( $term->taxonomy, array( 'post_translations', 'language' ) ) ) {
				continue;
			}

			$term->polylang = array();

			if ( function_exists( 'pll_get_term_language' ) ) {
				$term->polylang['language'] = \pll_get_term_language( $term->term_id );
			}

			if ( function_exists( 'pll_get_term_translations' ) ) {
				$term->polylang['translations'] = \pll_get_term_translations( $term->term_id );
			}

		}

		return $terms;
	}

	/**
	 * Prepare object translations.
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified data.
	 */
	public function prepare_object_translations( $data, $site ) {

		if ( empty( $data['replicast']['terms'] ) ) {
			return $data;
		}

		foreach ( $data['replicast']['terms'] as $term ) {

			if ( $term->taxonomy !== 'post_translations' ) {
				continue;
			}

			$translations = $this->get_translations( $term->description );

			foreach ( $translations as $lang => $translated_object_id ) {

				$remote_info = API::get_remote_info( \get_post( $translated_object_id ) );

				// Update object ID
				unset( $translations[ $lang ] );
				if ( ! empty( $remote_info ) ) {
					$translations[ $lang ] = $remote_info[ $site->get_id() ]['id'];
				}

			}

			$term->description = $this->set_translations( $translations );

		}

		return $data;
	}

	/**
	 * Prepare object terms translations.
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified data.
	 */
	public function prepare_object_terms_translations( $data, $site ) {

		if ( empty( $data['replicast']['terms'] ) ) {
			return $data;
		}

		foreach ( $data['replicast']['terms'] as $term_id => $term ) {

			if ( empty( $term->polylang['translations'] ) ) {
				continue;
			}

			foreach ( $term->polylang['translations'] as $lang => $translated_object_id ) {

				$remote_info = API::get_remote_info( \get_term( $translated_object_id ) );

				// Update object ID's
				unset( $data['replicast']['terms'][ $term_id ]->polylang['translations'][ $lang ] );
				if ( ! empty( $remote_info ) ) {
					$data['replicast']['terms'][ $term_id ]->polylang['translations'][ $lang ] = $remote_info[ $site->get_id() ]['id'];
				}

			}

		}

		return $data;
	}

	/**
	 * Update Polylang terms translations.
	 *
	 * @since    1.0.0
	 * @param    array    $terms    Object terms.
	 */
	public function update_object_terms_translations( $terms ) {

		foreach ( $terms as $term_data ) {

			if ( empty( $term_data['polylang'] ) ) {
				continue;
			}

			$term_id       = $term_data['term_id'];
			$term_language = '';

			if ( function_exists( 'pll_current_language' ) ) {
				$term_language = \pll_current_language();
			}

			if ( ! empty( $term_data['polylang']['language'] ) ) {
				$term_language = $term_data['polylang']['language'];
				\pll_set_term_language( $term_id, $term_language );
			}

			if ( ! empty( $term_data['polylang']['translations'] ) ) {
				$translations = $term_data['polylang']['translations'];
				$translations[ $term_language ] = $term_id;
				uksort( $translations, array( $this, 'sort_by_language' ) );
				\pll_save_term_translations( $translations );
			}

		}

	}

	/**
	 * Get object translations.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     string    $description    Object translations serialized.
	 * @return    array                     Object translations unserialized.
	 */
	private function get_translations( $description ) {
		return unserialize( $description );
	}

	/**
	 * Set object translations.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     array    $translations    Object translations unserialized.
	 * @return    string                    Object translations serialized.
	 */
	private function set_translations( $translations ) {
		return serialize( $translations );
	}

	/**
	 * Comparison function for array sorting by language.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     string    $lang            Language slug.
	 * @param     string    $current_lang    Current language slug.
	 * @return    int                        Integer less than, equal to, or greater than zero
	 *                                       if the first argument is considered to be respectively
	 *                                       less than, equal to, or greater than the second.
	 */
	private function sort_by_language( $lang, $current_lang ) {

		if ( empty( $current_lang ) && function_exists( 'pll_current_language' ) ) {
			$current_lang = \pll_current_language();
		}

		return strcasecmp( $lang, $current_lang );
	}

}

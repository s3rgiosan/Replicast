<?php

/**
 * Add ACF support
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
 * Add ACF support.
 *
 * @since      1.0.0
 * @package    Replicast
 * @subpackage Replicast/lib
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class ACF {

	/**
	 * Identifies the meta variable that is sent to the remote site and
	 * that contains information regarding the remote object ACF meta.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const REPLICAST_ACF_INFO = '_replicast_acf_info';

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
	 * Adds REST persistence to removed object relationships.
	 *
	 * When a relationship between one or more objects is removed, this change is not synced via REST API.
	 * Leaving these same relationships remotely unchanged. To address this, we are sending the objects
	 * that were removed in a private meta variable which is then processed.
	 *
	 * @since     1.0.0
	 * @param     mixed    $value        The value of the field.
	 * @param     int      $object_id    The object ID.
	 * @param     array    $field        The field object.
	 * @return    mixed                  Possibly-modified value of the field.
	 */
	public function relationship_persistence( $value, $object_id, $field ) {

		// Bail out if not admin and bypass REST API requests
		if ( ! \is_admin() ) {
			return $value;
		}

		// If post is an autosave, return
		if ( \wp_is_post_autosave( $object_id ) ) {
			return $value;
		}

		// If post is a revision, return
		if ( \wp_is_post_revision( $object_id ) ) {
			return $value;
		}

		if ( ! $field ) {
			return $value;
		}

		$field_name     = $field['name'];
		$prev_selection = \get_field( $field_name, $object_id );
		$next_selection = ! empty( $value ) ? $value : array(); // This only contains object ID's
		$ids_to_remove  = array();

		if ( $prev_selection ) {
			foreach ( $prev_selection as $key => $prev_selected_object ) {
				if ( ! in_array( $prev_selected_object->ID, $next_selection ) ) {
					$ids_to_remove[] = $prev_selected_object->ID;
				}
			}
		}

		// Get meta
		$meta = \get_post_meta( $object_id, static::REPLICAST_ACF_INFO, true );

		if ( ! $meta ) {
			$meta = array();
		}

		// Add meta persistence
		\update_post_meta(
			$object_id,
			static::REPLICAST_ACF_INFO,
			array_merge( $meta, array(
				$field_name => $ids_to_remove
			) ),
			$meta
		);

		return $value;
	}

	/**
	 * Expose \Replicast\ACF protected meta keys.
	 *
	 * @since     1.0.0
	 * @return    array    Exposed meta keys.
	 */
	public function expose_protected_meta() {
		return array( static::REPLICAST_ACF_INFO );
	}

	/**
	 * Retrieve post ACF meta.
	 *
	 * @since     1.0.0
	 * @param     array     $values       Object meta.
	 * @param     string    $meta_type    The object meta type.
	 * @param     int       $object_id    The object ID.
	 * @return    array                   Possibly-modified object meta.
	 */
	public function get_post_meta( $values, $meta_type, $object_id ) {

		$prepared_meta = array();

		foreach ( $values as $meta_key => $meta_value ) {

			/**
			 * If it's an ACF field, add more information regarding the field.
			 *
			 * The raw/rendered keys are used just to identify what's an ACF meta value ahead in the
			 * replication process.
			 *
			 * FIXME: I don't know if it's a good idea use the raw/rendered keys like the core uses
			 *        with posts and pages. Maybe we should use some key that relates to ACF?
			 */
			if ( $field = \get_field_object( $meta_key, $object_id ) ) {
				$prepared_meta[ $meta_key ] = array(
					'raw'      => $field,
					'rendered' => $meta_value,
				);
			} else {
				$prepared_meta[ $meta_key ] = $meta_value;
			}

		}

		return $prepared_meta;
	}

	/**
	 * Prepare post ACF meta.
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared post data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified post data.
	 */
	public function prepare_post_meta( $data, $site ) {

		if ( empty( $data['replicast'] ) ) {
			return $data;
		}

		if ( empty( $data['replicast']['meta'] ) ) {
			return $data;
		}

		foreach ( $data['replicast']['meta'] as $key => $meta ) {

			if ( empty( $meta['raw'] ) ) {
				continue;
			}

			$field_type  = \acf_extract_var( $meta['raw'], 'type' );
			$field_value = \acf_extract_var( $meta['raw'], 'value' );
			$meta_value  = '';

			if ( $field_type === 'text' ) {
				$meta_value = $field_value;
			}

			if ( $field_type === 'relationship' ) {

				if ( empty( $field_value ) ) {
					$field_value = array();
				}

				foreach ( $field_value as $related_object ) {

					if ( is_numeric( $related_object ) ) {
						$related_object = \get_post( $related_object );
					}

					// Get replicast info
					$replicast_info = API::get_replicast_info( $related_object );

					// Update object ID
					if ( ! empty( $replicast_info ) ) {
						$meta_value[] = $replicast_info[ $site->get_id() ]['id'];
					}

				}

				if ( ! empty( $meta_value ) && is_array( $meta_value ) ) {
					$meta_value = maybe_serialize( $meta_value );
				}

			}

			unset( $data['replicast']['meta'][ $key ] );

			$data['replicast']['meta'][ $key ][] = $meta_value;
		}

		return $data;
	}

	/**
	 * Suppress \Replicast\ACF meta keys from update.
	 *
	 * @since     1.0.0
	 * @return    array     Suppressed meta keys.
	 */
	public function suppress_meta_from_update() {
		return array( static::REPLICAST_ACF_INFO );
	}

	/**
	 * Update post ACF meta.
	 *
	 * This function is used primarily to remove previous relationships
	 * based on the information saved in \Replicast\ACF\REPLICAST_ACF_INFO.
	 *
	 * @see    \Replicast\ACF\relationship_persistence
	 *
	 * @since    1.0.0
	 * @param    array     $values       The values of the field.
	 * @param    string    $meta_type    The object meta type.
	 * @param    int       $object_id    The post id.
	 */
	public function update_post_meta( $values, $meta_type, $object_id ) {

	}

}

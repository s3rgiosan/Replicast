<?php

/**
 * Handles ´post´ content type replication
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib/Handler
 */

namespace Replicast\Handler;

use Replicast\API;
use Replicast\Handler;
use Replicast\Plugin;

/**
 * Handles ´post´ content type replication.
 *
 * @since      1.0.0
 * @package    Replicast
 * @subpackage Replicast/lib/Handler
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class PostHandler extends Handler {

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    \WP_Post    $post    Post object.
	 */
	public function __construct( \WP_Post $post ) {

		$obj = \get_post_type_object( $post->post_type );

		$this->rest_base   = ! empty( $obj->rest_base ) ? $obj->rest_base : $obj->name;
		$this->object      = $post;
		$this->object_type = $post->post_type;
		$this->data        = $this->get_object_data();

	}

	/**
	 * Prepare page for create, update or delete.
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared page data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified page data.
	 */
	public function prepare_page( $data, $site ) {

		// Remove page template if empty
		if ( empty( $data['template'] ) ) {
			unset( $data['template'] );
		}

		return $data;
	}

	/**
	 * Prepare attachment for create, update or delete.
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared attachment data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified attachment data.
	 */
	public function prepare_attachment( $data, $site ) {

		// Force attachment status to be 'publish'
		// FIXME: review this later on
		if ( ! empty( $data['status'] ) && $data['status'] === 'inherit' ) {
			$data['status'] = 'publish';
		}

		// Update the "uploaded to" post ID with the associated remote post ID, if exists
		if ( $data['type'] !== 'attachment' && ! empty( $data['post'] ) ) {

			// Update object ID
			$data['post'] = '';
			if ( ! empty( $replicast_info = API::get_remote_info( \get_post( $data['post'] ) ) ) ) {
				$data['post'] = $replicast_info[ $site->get_id() ]['id'];
			}

		}

		return $data;
	}

	/**
	 * Prepare meta.
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared post data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified post data.
	 */
	public function prepare_meta( $data, $site ) {

		// Add remote object info
		$data['replicast']['meta'][ Plugin::REPLICAST_SOURCE_INFO ] = array( \maybe_serialize( array(
			'object_id' => $this->object->ID,
			'edit_link' => \get_edit_post_link( $this->object->ID ),
		) ) );

		return $data;
	}

	/**
	 * Prepare terms.
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared post data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified post data.
	 */
	public function prepare_terms( $data, $site ) {

		// Remove default taxonomies data structures
		foreach ( \get_post_taxonomies( $this->object->ID ) as $taxonomy ) {
			unset( $data[ $taxonomy ] );
		}

		if ( empty( $data['replicast']['term'] ) ) {
			return $data;
		}

		foreach ( $data['replicast']['term'] as $term_id => $term ) {

			// Update object ID
			$term->term_id = '';
			if ( ! empty( $replicast_info = API::get_remote_info( $term ) ) ) {
				$term->term_id = $replicast_info[ $site->get_id() ]['id'];
			}

			$data['replicast']['term'][ $term_id ] = $term;

			// Add remote object info
			$data['replicast']['term'][ $term_id ]->meta = array(
				Plugin::REPLICAST_SOURCE_INFO => \maybe_serialize( array(
					'object_id' => $term_id,
					'edit_link' => \get_edit_term_link( $term_id, $term->taxonomy ),
				) )
			);

			// Check if term has children
			if ( empty( $term->children ) ) {
				continue;
			}

			$this->prepare_child_terms( $term->term_id, $data['replicast']['term'][ $term_id ]->children, $site );

		}

		return $data;
	}

	/**
	 * Prepare child terms.
	 *
	 * @since     1.0.0
	 * @param     int                  $parent_id    The parent term ID.
	 * @param     array                $terms        The term data.
	 * @param     \Replicast\Client    $site         Site object.
	 * @return    array                              Possibly-modified child terms.
	 */
	private function prepare_child_terms( $parent_id, &$terms, $site ) {

		foreach ( $terms as $term_id => $term ) {

			// Update object ID's
			$term->term_id = '';
			$term->parent  = '';

			if ( ! empty( $replicast_info = API::get_remote_info( $term ) ) ) {
				$term->term_id = $replicast_info[ $site->get_id() ]['id'];
				$term->parent  = $parent_id;
			}

			$terms[ $term_id ] = $term;

			// Add remote object info
			$terms[ $term_id ]->meta = array(
				Plugin::REPLICAST_SOURCE_INFO => \maybe_serialize( array(
					'object_id' => $term_id,
					'edit_link' => \get_edit_term_link( $term_id, $term->taxonomy ),
				) )
			);

			// Check if term has children
			if ( empty( $term->children ) ) {
				continue;
			}

			$this->prepare_child_terms( $term->term_id, $terms[ $term_id ]->children, $site );

		}

	}

	/**
	 * Prepare featured media.
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared post data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified post data.
	 */
	public function prepare_featured_media( $data, $site ) {

		// Update object ID
		$data['featured_media'] = '';
		if ( ! empty( $replicast_info = API::get_remote_info( \get_post( $data['featured_media'] ) ) ) ) {
			$data['featured_media'] = $replicast_info[ $site->get_id() ]['id'];
		}

		return $data;
	}

	/**
	 * Update object with remote ID.
	 *
	 * @since     1.0.0
	 * @param     int       $site_id    Site ID.
	 * @param     object    $data       Object data.
	 */
	public function handle_object( $site_id, $data = null ) {
		API::update_remote_info( $this->object, $site_id, $data );
	}

	/**
	 * Update terms with remote IDs.
	 *
	 * @since     1.0.0
	 * @param     int       $site_id    Site ID.
	 * @param     object    $data       Object data.
	 */
	public function handle_terms( $site_id, $data = null ) {

		if ( empty( $data->replicast->term ) ) {
			return;
		}

		foreach ( $data->replicast->term as $term_id => $term_data ) {

			// Get term object
			if ( ! $term = \get_term_by( 'id', $term_id, $term_data->taxonomy ) ) {
				return;
			}

			// Update replicast info
			API::update_remote_info( $term, $site_id, $term_data );

		}

	}

	/**
	 * Update media with remote IDs.
	 *
	 * @since     1.0.0
	 * @param     int       $site_id    Site ID.
	 * @param     object    $data       Object data.
	 */
	public function handle_media( $site_id, $data = null ) {

		// FIXME: this should be an independent action

		if ( empty( $data->replicast->media ) ) {
			return;
		}

		foreach ( $data->replicast->media as $media_id => $media_data ) {

			// Get media object
			if ( ! $media = \get_post( $media_id ) ) {
				return;
			}

			// Update replicast info
			API::update_remote_info( $media, $site_id, $media_data );

		}

	}

}

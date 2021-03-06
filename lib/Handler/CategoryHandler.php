<?php
/**
 * Handles ´category´ terms replication
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib/Handler
 */

namespace Replicast\Handler;

use Replicast\Handler;

/**
 * Handles ´category´ terms replication.
 *
 * @since      1.0.0
 * @package    Replicast
 * @subpackage Replicast/lib/Handler
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class CategoryHandler extends Handler {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param \WP_Term $term Term object.
	 */
	public function __construct( \WP_Term $term ) {
		parent::__construct();

		$this->rest_base   = 'categories';
		$this->object      = $term;
		$this->object_type = $term->taxonomy;
		$this->data        = $this->get_object_data();
		$this->attributes  = array( 'context' => 'embed' );
	}
}

<?php
/**
 * Handles object replication
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib
 */

namespace Replicast;

use Replicast\API;
use Replicast\Admin;
use Replicast\Client;
use Replicast\Plugin;

/**
 * Handles object replication.
 *
 * @since      1.0.0
 * @package    Replicast
 * @subpackage Replicast/lib
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
abstract class Handler {

	/**
	 * Alias for GET method.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const READABLE = 'GET';

	/**
	 * Alias for POST method.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const CREATABLE = 'POST';

	/**
	 * Alias for PUT method.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const EDITABLE = 'PUT';

	/**
	 * Alias for DELETE method.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const DELETABLE = 'DELETE';

	/**
	 * The logger's instance.
	 *
	 * @since  1.2.0
	 * @access protected
	 * @var    \Monolog\Logger
	 */
	protected $logger;

	/**
	 * Object type.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $object_type;

	/**
	 * Object data.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    object
	 */
	protected $object;

	/**
	 * Object data in a REST API compliant schema.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $data = array();

	/**
	 * Request method.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $method = 'GET';

	/**
	 * The namespace of the request route.
	 *
	 * @var string
	 */
	protected $namespace = 'wp/v2';

	/**
	 * The base of the request route.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $rest_base = 'posts';

	/**
	 * Attributes for the request.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $attributes = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->logger = new Logger( Handler::class );
	}

	/**
	 * Create object on a site.
	 *
	 * @since  1.0.0
	 * @param  \Replicast\Client $site Site object.
	 * @param  array             $args Query string parameters.
	 * @return \GuzzleHttp\Promise
	 */
	public function post( $site, $args = array() ) {
		return $this->do_request( Handler::CREATABLE, $site, $args );
	}

	/**
	 * Update object on a site.
	 *
	 * @since  1.0.0
	 * @param  \Replicast\Client $site Site object.
	 * @param  array             $args Query string parameters.
	 * @return \GuzzleHttp\Promise
	 */
	public function put( $site, $args = array() ) {
		return $this->do_request( Handler::EDITABLE, $site, $args );
	}

	/**
	 * Delete object from a site.
	 *
	 * @since  1.0.0
	 * @param  \Replicast\Client $site Site object.
	 * @param  array             $args Query string parameters.
	 * @return \GuzzleHttp\Promise
	 */
	public function delete( $site, $args = array() ) {
		return $this->do_request( Handler::DELETABLE, $site, $args );
	}

	/**
	 * Create/update object handler.
	 *
	 * @since  1.0.0
	 * @param  \Replicast\Client $site           Site object.
	 * @param  array             $replicast_info The remote object info.
	 * @return \GuzzleHttp\Promise
	 */
	public function handle_save( $site, $replicast_info = array() ) {

		if ( array_key_exists( $site->get_id(), $replicast_info ) ) {
			return $this->put( $site );
		}

		return $this->post( $site );
	}

	/**
	 * Delete object handler.
	 *
	 * @since  1.0.0
	 * @param  \Replicast\Client $site  Site object.
	 * @param  bool              $force Flag for bypass trash or force deletion.
	 * @return \GuzzleHttp\Promise
	 */
	public function handle_delete( $site, $force = false ) {
		return $this->delete( $site, array(
			'force' => $force,
		) );
	}

	/**
	 * Get object ID.
	 *
	 * @since  1.1.0
	 * @return int Object ID.
	 */
	public function get_object_id() {
		return $this->object->ID;
	}

	/**
	 * Get admin notice unique ID.
	 *
	 * @since  1.1.0 Added site ID.
	 * @since  1.0.0
	 *
	 * @param  int    $site_id Site ID.
	 * @param  int    $user_id User ID.
	 * @param  string $suffix  Notice unique ID suffix.
	 * @return string          Admin notices unique ID.
	 */
	public function get_notice_unique_id( $site_id = 0, $user_id = false, $suffix = 'exception' ) {

		if ( ! $user_id ) {
			$user_id = \wp_get_current_user()->ID;
		}

		return sprintf(
			'replicast_notices_site_%s_user_%s_%s_ID_%s_%s',
			$site_id,
			$user_id,
			$this->object_type,
			$this->object->ID,
			$suffix
		);
	}

	/**
	 * Prepares a object for a given method.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @param  string            $method Request method.
	 * @param  \Replicast\Client $site   Site object.
	 * @return array                     Prepared object data.
	 */
	protected function prepare_body( $method, $site ) {
		$data = array();

		switch ( $method ) {
			case static::CREATABLE:
				$data = $this->prepare_body_for_create( $site );
				break;

			case static::EDITABLE:
			case static::DELETABLE:
				$data = $this->prepare_body_for_update( $site );
				break;
		}

		/**
		 * Filter for suppressing REST API default data structures.
		 *
		 * @since  1.0.0
		 * @param  array Name(s) of the suppressed data structures.
		 * @return array Possibly-modified name(s) of the suppressed data structures.
		 */
		$structures = \apply_filters( 'replicast_suppress_rest_api_structures', array(
			'categories',
			'tags',
			'_links',
			'_embedded',
		) );

		foreach ( $structures as $structure ) {
			unset( $data[ $structure ] );
		}

		return $data;
	}

	/**
	 * Prepares an object for creation.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @param  \Replicast\Client $site Site object.
	 * @return array                   Prepared object data.
	 */
	protected function prepare_body_for_create( $site ) {

		// Get object data.
		$data = $this->data;

		if ( \is_wp_error( $data ) ) {
			return array();
		}

		// Remove object ID.
		if ( ! empty( $data['id'] ) ) {
			unset( $data['id'] );
		}

		// Remove author.
		if ( ! empty( $data['author'] ) ) {
			unset( $data['author'] );
		}

		// Check for date_gmt presence.
		// Note: date_gmt is necessary for post update and it's zeroed upon deletion.
		if ( empty( $data['date_gmt'] ) && ! empty( $data['date'] ) ) {
			$data['date_gmt'] = \mysql_to_rfc3339( $data['date'] );
		}

		// Generate post slug for draft.
		if ( empty( $data['slug'] ) &&
			! empty( $this->object->post_title ) &&
			$this->object->post_status === 'draft' ) {
			$data['slug'] = \sanitize_title( $this->object->post_title );
		}

		// Update featured media ID.
		if ( ! empty( $data['featured_media'] ) ) {
			$data = $this->prepare_featured_media( $data, $site );
		}

		// Prepare meta.
		$data = $this->prepare_meta( $data, $site );

		// Prepare terms.
		$data = $this->prepare_terms( $data, $site );

		// Prepare data by object type.
		switch ( $this->object_type ) {
			case 'page':
				$data = $this->prepare_page( $data, $site );
				break;
			case 'attachment':
				$data = $this->prepare_attachment( $data, $site );
				break;
		}

		/**
		 * Extend data for creation by object type.
		 *
		 * @since  1.0.0
		 * @param  array             Prepared object data.
		 * @param  \Replicast\Client Site object.
		 * @return array             Possibly-modified object data.
		 */
		$data = \apply_filters( "replicast_prepare_{$this->object_type}_for_create", $data, $site );

		/**
		 * Extend data for creation.
		 *
		 * @since  1.0.0
		 * @param  array             Prepared object data.
		 * @param  \Replicast\Client Site object.
		 * @return array             Possibly-modified object data.
		 */
		$data = \apply_filters( 'replicast_prepare_object_for_create', $data, $site );

		return $this->prepare_media( $data, $site );
	}

	/**
	 * Prepares an object for update or deletion.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @param  \Replicast\Client $site Site object.
	 * @return array                   Prepared object data.
	 */
	protected function prepare_body_for_update( $site ) {

		// Get object data.
		$data = $this->data;

		if ( \is_wp_error( $data ) ) {
			return array();
		}

		// Get replicast info.
		$replicast_info = API::get_remote_info( $this->object );

		if ( empty( $replicast_info ) ) {
			return array();
		}

		// Update object ID.
		$data['id'] = $replicast_info[ $site->get_id() ]['id'];

		// Remove author.
		if ( ! empty( $data['author'] ) ) {
			unset( $data['author'] );
		}

		// Check for date_gmt presence.
		// Note: date_gmt is necessary for post update and it's zeroed upon deletion.
		if ( empty( $data['date_gmt'] ) && ! empty( $data['date'] ) ) {
			$data['date_gmt'] = \mysql_to_rfc3339( $data['date'] );
		}

		// Update featured media ID.
		if ( ! empty( $data['featured_media'] ) ) {
			$data = $this->prepare_featured_media( $data, $site );
		}

		// Prepare meta.
		$data = $this->prepare_meta( $data, $site );

		// Prepare terms.
		$data = $this->prepare_terms( $data, $site );

		// Prepare data by object type.
		switch ( $this->object_type ) {
			case 'page':
				$data = $this->prepare_page( $data, $site );
				break;
			case 'attachment':
				$data = $this->prepare_attachment( $data, $site );
				break;
		}

		/**
		 * Extend data for update by object type.
		 *
		 * @since  1.0.0
		 * @param  array             Prepared object data.
		 * @param  \Replicast\Client Site object.
		 * @return array             Possibly-modified object data.
		 */
		$data = \apply_filters( "replicast_prepare_{$this->object_type}_for_update", $data, $site );

		/**
		 * Extend data for update.
		 *
		 * @since  1.0.0
		 * @param  array             Prepared object data.
		 * @param  \Replicast\Client Site object.
		 * @return array             Possibly-modified object data.
		 */
		$data = \apply_filters( 'replicast_prepare_object_for_update', $data, $site );

		return $this->prepare_media( $data, $site );
	}

	/**
	 * Wrap an object in a REST API compliant schema.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return array The object data.
	 */
	protected function get_object_data() {
		return $this->_do_request();
	}

	/**
	 * Do a REST request.
	 *
	 * @since  1.3.0 Custom header for replicast requests.
	 * @since  1.0.0
	 * @access protected
	 * @param  string            $method Request method.
	 * @param  \Replicast\Client $site   Site object.
	 * @param  array             $args   Query string parameters.
	 * @return \GuzzleHttp\Promise
	 */
	protected function do_request( $method, $site, $args ) {

		// Bail out if the site is invalid.
		if ( ! $site->is_valid() ) {
			throw new \Exception( sprintf(
				\__( 'The site with ID %s is not valid. Check if all the required fields are filled.', 'replicast' ),
				$site->get_id()
			) );
		}

		// Prepare post for replication.
		$data = $this->prepare_body( $method, $site );

		// Bail out if the object ID doesn't exist.
		if ( $method !== static::CREATABLE && empty( $data['id'] ) ) {
			throw new \Exception( sprintf(
				\__( 'The %s request cannot be made for a content type without an ID.', 'replicast' ),
				$method
			) );
		}

		// Generate an API timestamp.
		// This timestamp is also used to generate the request signature.
		$timestamp = time();

		// Get site config.
		$config = $site->get_config();

		// Add request path to endpoint.
		$config['api_url'] = $config['api_url'] . \trailingslashit( $this->rest_base );

		// Build endpoint for GET, PUT and DELETE.
		// FIXME: this has to be more bulletproof!
		if ( $method !== static::CREATABLE ) {
			$config['api_url'] = $config['api_url'] . \trailingslashit( $data['id'] );
		}

		$headers = array();
		$body    = array();

		// Asynchronous request.
		if ( $method === static::CREATABLE && $this->object_type === 'attachment' ) {

			$file_path = \get_attached_file( API::get_id( $this->object ) );
			$file_name = basename( $file_path );

			$headers['Content-Type']        = $data['mime_type'];
			$headers['Content-Disposition'] = sprintf( 'attachment; filename=%s', $file_name );
			$headers['Content-MD5']         = md5_file( $file_path );

			$body['body'] = file_get_contents( $file_path );

		} else {
			$body['json'] = $data;
		}

		// The WP REST API doesn't expect a PUT.
		if ( $method === static::EDITABLE ) {
			$method = 'POST';
		}

		// Generate request signature.
		$signature = $this->generate_signature( $method, $config, $timestamp, $args );

		// Set auth headers.
		$headers['X-API-KEY']       = $config['apy_key'];
		$headers['X-API-TIMESTAMP'] = $timestamp;
		$headers['X-API-SIGNATURE'] = $signature;

		// Set custom header.
		$headers[ Plugin::REPLICAST_REQUEST_HEADER ] = true;

		if ( REPLICAST_DEBUG ) {
			$this->logger->log()->debug(
				'Doing a request',
				array(
					'method'   => $method,
					'endpoint' => $config['api_url'],
					'headers'  => $headers,
					'data'     => $data,
				)
			);
		}

		return $site->get_client()->request(
			$method,
			$config['api_url'],
			array_merge(
				array(
					'headers' => $headers,
					'query'   => $args,
				),
				$body
			)
		);
	}

	/**
	 * Do an internal REST request.
	 *
	 * @global \WP_REST_Server $wp_rest_server ResponseHandler instance (usually \WP_REST_Server).
	 *
	 * @since  1.3.0 Custom header for replicast internal requests.
	 * @since  1.0.0
	 * @access private
	 * @return \WP_REST_Response Response object.
	 */
	private function _do_request() {

		global $wp_rest_server;

		if ( empty( $wp_rest_server ) ) {

			/**
			 * Filter the REST Server Class.
			 *
			 * @since 1.0.0
			 * @param string The name of the server class. Default '\WP_REST_Server'.
			 */
			$wp_rest_server_class = \apply_filters( 'replicast_rest_server_class', '\WP_REST_Server' );
			$wp_rest_server       = new $wp_rest_server_class;

			/**
			 * Fires when preparing to serve an API request.
			 *
			 * @since 1.0.0
			 * @param \WP_REST_Server $wp_rest_server Server object.
			 */
			\do_action( 'rest_api_init', $wp_rest_server );

		}

		// Request attributes.
		$attributes = array_merge(
			array(
				'context' => 'edit',
				'_embed'  => true,
			),
			$this->attributes
		);

		// Build request.
		$request = new \WP_REST_Request(
			$this->method,
			sprintf(
				'/%s/%s',
				$this->namespace,
				\trailingslashit( $this->rest_base ) . API::get_id( $this->object )
			)
		);

		foreach ( $attributes as $k => $v ) {
			$request->set_param( $k, $v );
		}

		// Set custom header.
		$request->set_header( Plugin::REPLICAST_REQUEST_HEADER, true );

		// Make request.
		$result = $wp_rest_server->dispatch( $request );

		if ( $result->is_error() ) {
			return $result->as_error();
		}

		// Force the return of embeddable data like featured image, terms, etc.
		return $wp_rest_server->response_to_data( $result, ! empty( $attributes['_embed'] ) );
	}

	/**
	 * Generate a hash signature.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $method    Request method.
	 * @param  array  $config    Request config.
	 * @param  int    $timestamp Request timestamp.
	 * @param  array  $args      Query string parameters.
	 * @return string            Return hash of the secret.
	 */
	private function generate_signature( $method = 'GET', $config, $timestamp, $args ) {

		$request_uri = $config['api_url'];
		if ( ! empty( $args ) ) {
			$request_uri = \add_query_arg( $args, $request_uri );
		}

		/**
		 * Arguments used for generating the signature.
		 *
		 * They should be in the following order:
		 * 'api_key', 'ip', 'request_method', 'request_post', 'request_uri', 'timestamp'
		 */
		$args = array(
			'api_key'        => $config['apy_key'],
			'request_method' => $method,
			'request_post'   => array(),
			'request_uri'    => $request_uri,
			'timestamp'      => $timestamp,
		);

		/**
		 * Filter the name of the selected hashing algorithm (e.g. "md5", "sha256", "haval160,4", etc..).
		 *
		 * @since 1.0.0
		 * @param string Name of the selected hashing algorithm.
		 */
		$algo = \apply_filters( 'replicast_key_auth_signature_algo', 'sha256' );

		return hash( $algo, \wp_json_encode( $args ) . $config['api_secret'] );
	}
}

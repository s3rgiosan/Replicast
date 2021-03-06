<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the dashboard.
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib
 */

namespace Replicast;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, dashboard-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Replicast
 * @subpackage Replicast/lib
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class Plugin {

	/**
	 * The remote site taxonomy identifier.
	 *
	 * @since 1.0.0
	 * @var   \Replicast\Admin\SiteAdmin
	 */
	const TAXONOMY_SITE = 'remote_site';

	/**
	 * Request custom header.
	 *
	 * @since 1.3.0
	 * @var   string
	 */
	const REPLICAST_REQUEST_HEADER = 'X-WP-Replicast';

	/**
	 * Identifies the meta variable that saves the information
	 * regarding the "to where" the central object was replicated.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const REPLICAST_REMOTE_INFO = '_replicast_remote_info';

	/**
	 * Identifies the meta variable that is sent to the remote site and
	 * that contains information regarding the source object.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const REPLICAST_SOURCE_INFO = '_replicast_source_info';

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $name;

	/**
	 * The current version of the plugin.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * @since 1.0.0
	 * @param string $name    The plugin name.
	 * @param string $version The plugin version.
	 */
	public function __construct( $name, $version ) {
		$this->name    = $name;
		$this->version = $version;
	}

	/**
	 * Load the dependencies, define the locale, and set the hooks for the Dashboard and
	 * the public-facing side of the site.
	 *
	 * @since 1.0.0
	 */
	public function run() {
		$this->set_locale();

		$this->define_api_hooks();
		$this->define_admin_hooks();
		$this->define_admin_post_hooks();
		$this->define_admin_site_hooks();

		$this->define_acf_hooks();
		$this->define_polylang_hooks();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since  1.0.0
	 * @return string The name of the plugin.
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since  1.0.0
	 * @return string The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the I18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function set_locale() {
		$i18n = new I18n();
		$i18n->set_domain( $this->get_name() );
		$i18n->load_plugin_textdomain();
	}

	/**
	 * Register all of the hooks related to the dashboard functionality.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function define_admin_hooks() {
		$admin = new Admin( $this );
		\add_action( 'init', array( $admin, 'register' ), 90 );
	}

	/**
	 * Register all of the hooks related to the \Admin\Post functionality.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function define_admin_post_hooks() {
		$post_admin = new Admin\PostAdmin( $this );
		\add_action( 'init', array( $post_admin, 'register' ), 90 );
	}

	/**
	 * Register all of the hooks related to the \Admin\Site functionality.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function define_admin_site_hooks() {
		$site_admin = new Admin\SiteAdmin( $this, static::TAXONOMY_SITE );
		\add_action( 'init', array( $site_admin, 'register' ), 90 );
	}

	/**
	 * Register all of the hooks related to the API functionality.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function define_api_hooks() {
		$api = new API( $this );
		\add_action( 'rest_api_init', array( $api, 'register' ), 90 );
	}

	/**
	 * Register all of the hooks related to the ACF functionality.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function define_acf_hooks() {

		if ( ! class_exists( 'acf' ) ) {
			return;
		}

		$acf = new Module\ACF( $this );
		\add_action( 'init', array( $acf, 'register' ), 90 );
	}

	/**
	 * Register all of the hooks related to the Polylang functionality.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function define_polylang_hooks() {

		if ( ! class_exists( 'Polylang' ) ) {
			return;
		}

		$polylang = new Module\Polylang( $this );
		\add_action( 'init', array( $polylang, 'register' ), 90 );
	}
}

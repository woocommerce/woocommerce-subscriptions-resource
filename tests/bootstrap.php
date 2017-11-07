<?php
/**
 * WCS_Unit_Tests_Bootstrap
 *
 * @since 2.0
 */
class WCSR_Unit_Tests_Bootstrap {

	/** @var \WCSR_Unit_Tests_Bootstrap instance */
	protected static $instance = null;

	/** @var string plugin directory */
	protected $plugin_dir;

	/**
	 * Setup the unit testing environment
	 *
	 * @since 2.0
	 */
	function __construct() {

		ini_set( 'display_errors','on' );
		error_reporting( E_ALL );

		// Make sure ABSPATH is defined so files load
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '' );
		}

		// Make sure the DAY_IN_SECONDS constant is defined so we can use it without rely on WP's definition of it
		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			define( 'DAY_IN_SECONDS', 60 * 60 * 24 );
		}

		$this->plugin_dir = dirname( dirname( __FILE__ ) );

		// Define a mock WC_Data object rather than requiring WooCommerce (we shouldn't be relying on or testing any WC_Data methods anyway)
		require_once( 'mocks/class-wc-data.php' );

		require_once( $this->plugin_dir . '/includes/class-wcsr-resource.php' );
	}

	/**
	 * Get the single class instance
	 *
	 * @since 2.0
	 * @return WCS_Unit_Tests_Bootstrap
	 */
	public static function instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
WCSR_Unit_Tests_Bootstrap::instance();
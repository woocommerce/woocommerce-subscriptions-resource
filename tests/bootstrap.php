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

		if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
			define( 'WEEK_IN_SECONDS', 60 * 60 * 24 * 7 );
		}

		if ( ! defined( 'MONTH_IN_SECONDS' ) ) {
			define( 'MONTH_IN_SECONDS', 60 * 60 * 24 * 30 );
		}

		if ( ! defined( 'YEAR_IN_SECONDS' ) ) {
			define( 'YEAR_IN_SECONDS', 60 * 60 * 24 * 365 );
		}

		$this->tests_dir   = dirname( __FILE__ );
		$this->plugin_dir  = dirname( $this->tests_dir );
		$this->modules_dir = dirname( dirname( $this->tests_dir ) );

		// Define a mock WC_Data object rather than requiring WooCommerce (we shouldn't be relying on or testing any WC_Data methods anyway)
		require_once( 'mocks/class-wc-data.php' );

		require_once( $this->plugin_dir . '/includes/class-wcsr-resource.php' );
		require_once( $this->modules_dir . '/woocommerce-subscriptions/includes/wcs-time-functions.php' );

		// Load relevant class aliases for PHPUnit 6 (ran on PHP v7.0+ in Travis)
		if ( class_exists( 'PHPUnit\Runner\Version' ) && version_compare( PHPUnit\Runner\Version::id(), '6.0', '>=' ) ) {
			class_alias( 'PHPUnit\Framework\TestCase', 'PHPUnit_Framework_TestCase' );
			// class_alias( 'PHPUnit\Framework\Exception', 'PHPUnit_Framework_Exception' );
			// class_alias( 'PHPUnit\Framework\ExpectationFailedException', 'PHPUnit_Framework_ExpectationFailedException' );
			// class_alias( 'PHPUnit\Framework\Error\Notice', 'PHPUnit_Framework_Error_Notice' );
			// class_alias( 'PHPUnit\Framework\Test', 'PHPUnit_Framework_Test' );
			// class_alias( 'PHPUnit\Framework\Warning', 'PHPUnit_Framework_Warning' );
			// class_alias( 'PHPUnit\Framework\AssertionFailedError', 'PHPUnit_Framework_AssertionFailedError' );
			// class_alias( 'PHPUnit\Framework\TestSuite', 'PHPUnit_Framework_TestSuite' );
			// class_alias( 'PHPUnit\Framework\TestListener', 'PHPUnit_Framework_TestListener' );
			// class_alias( 'PHPUnit\Util\GlobalState', 'PHPUnit_Util_GlobalState' );
			// class_alias( 'PHPUnit\Util\Getopt', 'PHPUnit_Util_Getopt' );
		}

		require_once( 'framework/class-wcsr-unit-testcase.php' );
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

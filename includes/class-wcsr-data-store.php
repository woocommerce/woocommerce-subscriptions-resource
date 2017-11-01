<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC Resource Data Store.
 *
 * @since    1.0.0
 * @version  1.0.0
 * @category Class
 * @author   Prospress
 */
class WCSR_Data_Store {

	/**
	 * Attach callbacks to setup the resource data store
	 */
	public static function init() {

		add_action( 'init', __CLASS__ . '::init_store' );

		add_filter( 'woocommerce_data_stores', __CLASS__ . '::add_data_store' );

		add_action( 'init', __CLASS__ . '::register_statuses' );
	}

	/**
	 * Setup the custom post types for the chosen data store
	 *
	 * @return null
	 */
	public static function init_store() {
		self::store()->init();
	}

	/**
	 * Register the resource data store with WooCommerce
	 *
	 * @param array
	 * @return array
	 */
	public static function add_data_store( $data_stores ) {

		$data_stores[ self::get_object_type() ] = apply_filters( 'wcsr_resource_data_store_class', 'WCSR_Resource_Data_Store_CPT' );

		return $data_stores;
	}

	/**
	 * Wrapper for getting an instance of the resource data store
	 *
	 * @return WCSR_Resource_Data_Store_CPT
	 */
	public static function store() {
		return WC_Data_Store::load( self::get_object_type() );
	}

	/**
	 * Get the object type used to identify the resource data store
	 *
	 * @return string
	 */
	protected static function get_object_type() {
		return 'subscription-resource';
	}

	/**
	 * Set up ended and unended statuses for resources
	 *
	 * @since 1.0
	 */
	public static function register_statuses() {
		foreach ( array( 'wcsr-ended', 'wcsr-unended' ) as $status ) {
			register_post_status( $status, array(
				'public'                 => false,
				'exclude_from_search'    => false,
				'show_in_admin_all_list' => false,
			) );
		}
	}
}
WCSR_Data_Store::init();

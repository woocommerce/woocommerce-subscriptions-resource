<?php
/**
 * Store resource details in the WordPress posts table as a custom post type
 *
 * @package		WooCommerce Subscriptions Resource
 * @subpackage	WCSR_Resource_Data_Store_CPT
 * @category	Class
 * @author		Prospress
 * @since		1.0.0
 */

class WCSR_Resource_Data_Store_CPT extends WC_Data_Store_WP implements WC_Object_Data_Store_Interface {

	protected $post_type = 'wcsr_resource';

	/**
	 * Data stored in meta keys, but not considered "meta" for a resource
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected $internal_meta_keys = array(
		'external_id',
		'subscription_id',
		'activation_timestamps',
		'deactivation_timestamps',
	);

	/**
	 * Attach callbacks to create the post types used by this data store
	 *
	 * @since 1.0.0
	 */
	public function init() {

		if ( ! is_blog_installed() || post_type_exists( $this->post_type ) ) {
			return;
		}

		do_action( 'wcsr_register_post_types' );

		register_post_type( $this->post_type,
			apply_filters( 'woocommerce_register_post_type_' . $this->post_type,
				array(
					'labels'              => array(
							'name'                  => __( 'Resources', 'woocommerce-subscriptions-resource' ),
							'singular_name'         => __( 'Resource', 'woocommerce-subscriptions-resource' ),
							'all_items'             => __( 'All Resources', 'woocommerce-subscriptions-resource' ),
							'menu_name'             => _x( 'Resources', 'Admin menu name', 'woocommerce-subscriptions-resource' ),
							'add_new'               => __( 'Add New', 'woocommerce-subscriptions-resource' ),
							'add_new_item'          => __( 'Add new product', 'woocommerce-subscriptions-resource' ),
							'edit'                  => __( 'Edit', 'woocommerce-subscriptions-resource' ),
							'edit_item'             => __( 'Edit resource', 'woocommerce-subscriptions-resource' ),
							'new_item'              => __( 'New resource', 'woocommerce-subscriptions-resource' ),
							'view'                  => __( 'View resource', 'woocommerce-subscriptions-resource' ),
							'view_item'             => __( 'View resource', 'woocommerce-subscriptions-resource' ),
							'search_items'          => __( 'Search resources', 'woocommerce-subscriptions-resource' ),
							'not_found'             => __( 'No resources found', 'woocommerce-subscriptions-resource' ),
							'not_found_in_trash'    => __( 'No resources found in trash', 'woocommerce-subscriptions-resource' ),
							'parent'                => __( 'Parent resource', 'woocommerce-subscriptions-resource' ),
							'featured_image'        => __( 'Resource image', 'woocommerce-subscriptions-resource' ),
							'set_featured_image'    => __( 'Set resource image', 'woocommerce-subscriptions-resource' ),
							'remove_featured_image' => __( 'Remove resource image', 'woocommerce-subscriptions-resource' ),
							'use_featured_image'    => __( 'Use as resource image', 'woocommerce-subscriptions-resource' ),
							'insert_into_item'      => __( 'Insert into resource', 'woocommerce-subscriptions-resource' ),
							'uploaded_to_this_item' => __( 'Uploaded to this resource', 'woocommerce-subscriptions-resource' ),
							'filter_items_list'     => __( 'Filter resources', 'woocommerce-subscriptions-resource' ),
							'items_list_navigation' => __( 'Resources navigation', 'woocommerce-subscriptions-resource' ),
							'items_list'            => __( 'Resources list', 'woocommerce-subscriptions-resource' ),
						),
					'description'         => __( 'This is where you can add new resources to your store.', 'woocommerce-subscriptions-resource' ),
					'public'              => false,
					'show_ui'             => false,
					'capability_type'     => 'shop_order',
					'map_meta_cap'        => true,
					'publicly_queryable'  => false,
					'exclude_from_search' => true,
					'hierarchical'        => false, // Hierarchical causes memory issues - WP loads all records!
					'show_in_nav_menus'   => false,
					'rewrite'             => false,
					'query_var'           => false,
					'supports'            => array( 'title', 'custom-fields' ),
					'has_archive'         => false,
					'show_in_rest'        => true,
					'can_export'          => true,
					'ep_mask'             => EP_NONE,
				)
			)
		);

		foreach ( array( 'wcsr-ended', 'wcsr-unended' ) as $status ) {
			register_post_status( $status, array(
				'public'                 => false,
				'exclude_from_search'    => false,
				'show_in_admin_all_list' => false,
			) );
		}
	}

	/**
	 * Method to create a new record of a WC_Data based object.
	 *
	 * @param WCSR_Resource &$resource
	 */
	public function create( &$resource ) {

		if ( null === $resource->get_date_created( 'edit' ) ) {
			$resource->set_date_created( gmdate( 'U' ) );
		}

		$resource_id = wp_insert_post( apply_filters( 'wcsr_new_resouce_data', array(
			'post_type'     => $this->post_type,
			'post_status'   => 'wcsr-unended',
			'post_author'   => 1, // Matches how Abstract_WC_Order_Data_Store_CPT works, using the default WP user
			'post_date'     => gmdate( 'Y-m-d H:i:s', $resource->get_date_created()->getOffsetTimestamp() ),
			'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $resource->get_date_created()->getTimestamp() ),
			'post_title'    => $this->get_post_title( $resource ),
			'post_parent'   => $resource->get_subscription_id( 'edit' ),
			'post_excerpt'  => '',
			'post_content'  => '',
			'post_password' => uniqid( 'resource_' ),
		) ), true );

		if ( $resource_id ) {
			$resource->set_id( $resource_id );
			$this->update_post_meta( $resource );
			$resource->save_meta_data();
			$resource->apply_changes();
			do_action( 'wcsr_new_resource', $resource_id );
		}
	}

	/**
	 * Method to read a record. Creates a new WC_Data based object.
	 *
	 * @param WCSR_Resource &$resource
	 */
	public function read( &$resource ) {
		$resource->set_defaults();

		if ( ! $resource->get_id() || ! ( $post_object = get_post( $resource->get_id() ) ) || $this->post_type !== $post_object->post_type ) {
			throw new Exception( __( 'Invalid resource.', 'woocommerce-subscriptions-resource' ) );
		}

		$resource_id = $resource->get_id();

		$resource->set_props( array(
			'date_created'            => 0 < $post_object->post_date_gmt ? wc_string_to_timestamp( $post_object->post_date_gmt ) : null,
			'status'                  => $post_object->post_status,
			'external_id'             => get_post_meta( $resource_id, 'external_id', true ),
			'subscription_id'         => get_post_meta( $resource_id, 'subscription_id', true ),

			'is_pre_paid'             => wc_string_to_bool( get_post_meta( $resource_id, 'is_pre_paid', true ) ),
			'is_prorated'             => wc_string_to_bool( get_post_meta( $resource_id, 'is_prorated', true ) ),

			'activation_timestamps'   => array_filter( (array) get_post_meta( $resource_id, 'activation_timestamps', true ) ),
			'deactivation_timestamps' => array_filter( (array) get_post_meta( $resource_id, 'deactivation_timestamps', true ) ),
		) );

		$resource->read_meta_data();

		$resource->set_object_read( true );

		do_action( 'wcsr_resource_loaded', $resource );
	}

	/**
	 * Updates a record in the database.
	 *
	 * @param WCSR_Resource &$resource
	 */
	public function update( &$resource ) {

		$resource->save_meta_data();
		$changes = $resource->get_changes();

		if ( array_intersect( array( 'date_created', 'status', 'subscription_id' ), array_keys( $changes ) ) ) {

			$post_data = array(
				'post_date'     => gmdate( 'Y-m-d H:i:s', $resource->get_date_created( 'edit' )->getOffsetTimestamp() ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $resource->get_date_created( 'edit' )->getTimestamp() ),
				'post_parent'   => $resource->get_subscription_id( 'edit' ),
				'post_status'   => $resource->get_status( 'edit' ) ? $resource->get_status( 'edit' ) : apply_filters( 'wcsr_default_resource_status', 'wcsr-unended' ),
			);

			/**
			 * When updating this object, to prevent infinite loops, use $wpdb
			 * to update data, since wp_update_post spawns more calls to the
			 * save_post action.
			 *
			 * This ensures hooks are fired by either WP itself (admin screen save),
			 * or an update purely from CRUD.
			 */
			if ( doing_action( 'save_post' ) ) {
				$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, $post_data, array( 'ID' => $resource->get_id() ) );
				clean_post_cache( $resource->get_id() );
			} else {
				wp_update_post( array_merge( array( 'ID' => $resource->get_id() ), $post_data ) );
			}
			$resource->read_meta_data( true ); // Refresh internal meta data, in case things were hooked into `save_post` or another WP hook.
		}
		$this->update_post_meta( $resource );
		$resource->apply_changes();

		do_action( 'wcsr_resouce_updated', $resource->get_id() );
	}

	/**
	 * Deletes a record from the database.
	 *
	 * @param  WCSR_Resource &$resource
	 * @param  array $args Array of args determine whether to trash or permanently delete the record.
	 * @return bool result
	 */
	public function delete( &$resource, $args = array() ) {
		$args = wp_parse_args( $args, array(
			'force_delete' => false,
		) );

		$id = $resource->get_id();

		if ( ! $id ) {
			return;
		}

		if ( $args['force_delete'] ) {
			wp_delete_post( $id );
			$resource->set_id( 0 );
			do_action( 'wcsr_resouce_deleted', $id );
		} else {
			wp_trash_post( $id );
			do_action( 'wcsr_resouce_trashed', $id );
		}
	}

	/**
	 * Get the IDs of all resources from the database for a given subscription/order
	 *
	 * @param int $order_id
	 * @param string $status
	 * @return array
	 */
	public function get_resource_ids_for_subscription( $subscription_id, $status = 'any' ) {
		$status = ( empty( $status ) || ! in_array( $status, wcsr_get_valid_statuses() ) ) ? 'any' : $status;

		$resource_post_ids = get_posts( array(
			'posts_per_page' => -1,
			'post_type'      => $this->post_type,
			'post_status'    => $status,
			'post_parent'    => $subscription_id,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		) );

		return $resource_post_ids;
	}

	/**
	 * Get the post ID of the resource from the database for a given resouce (using the ID of the resource in
	 * the 3rd party system, not post ID for it)
	 *
	 * @param int    $external_id
	 * @param string $status
	 * @return int
	 */
	public function get_resource_id_by_external_id( $external_id, $status = 'wcsr-unended' ) {
		$status = ( empty( $status ) || ! in_array( $status, wcsr_get_valid_statuses() ) ) ? 'any' : $status;

		$resource_post_ids = get_posts( array(
			'posts_per_page' => 1,
			'post_type'      => $this->post_type,
			'post_status'    => $status,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query' => array(
				array(
					'key'   => 'external_id',
					'value' => $external_id,
				),
			),
		) );

		$resource_post_id = empty( $resource_post_ids ) ? false : array_pop( $resource_post_ids );

		return $resource_post_id;
	}

	/**
	 * Helper method that updates all the post meta for a resource based on its settings in the WCSR_Resource class.
	 *
	 * @param WCSR_Resource &$resource
	 * @since 1.0.0
	 */
	private function update_post_meta( &$resource ) {

		$updated_props     = array();
		$meta_key_to_props = array(
			'external_id'             => 'external_id',
			'is_pre_paid'             => 'is_pre_paid',
			'is_prorated'             => 'is_prorated',
			'activation_timestamps'   => 'activation_timestamps',
			'deactivation_timestamps' => 'deactivation_timestamps',
		);

		$props_to_update = $this->get_props_to_update( $resource, $meta_key_to_props );

		foreach ( $props_to_update as $meta_key => $prop ) {

			$value = $resource->{"get_$prop"}( 'edit' );

			switch ( $prop ) {

				case 'is_pre_paid' :
				case 'is_prorated' :
					$updated = update_post_meta( $resource->get_id(), $meta_key, wc_bool_to_string( $value ) );
					break;

				// For now, we can keep this as a single row in the DB as it's much easier to manage changes, we may need to separate it later if it grows too long or is found to be corrupted too easily
				case 'activation_timestamps' :
				case 'deactivation_timestamps' :
					$updated = update_post_meta( $resource->get_id(), $meta_key, array_filter( array_map( 'intval', $value ) ) );
					break;

				default :
					$updated = update_post_meta( $resource->get_id(), $meta_key, $value );
					break;
			}

			if ( $updated ) {
				$updated_props[] = $prop;
			}
		}

		do_action( 'wcsr_resource_object_updated_props', $resource, $updated_props );
	}

	/**
	 * Get a title for the new post type.
	 *
	 * @param WCSR_Resource &$resource
	 * @since 1.0.0
	 * @return string
	 */
	protected function get_post_title( &$resource ) {
		/* translators: %s: Order date */
		return sprintf( __( 'Resource &ndash; %s', 'woocommerce-subscriptions-resource' ), strftime( _x( '%b %d, %Y @ %I:%M %p', 'Resource date parsed by strftime', 'woocommerce-subscriptions-resource' ), $resource->get_date_created()->getTimestamp() ) );
	}
}

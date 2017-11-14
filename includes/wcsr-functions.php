<?php

/**
 * Resource API functions
 *
 * @package		WooCommerce Subscriptions Resource
 * @author		Prospress
 * @since		1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Get all valid statuses for this resource type
 *
 * @since 1.1.0
 * @return array Internal status keys e.g. 'wcsr-unended'
 */
public static function wcsr_get_valid_statuses() {
	return apply_filters( 'wcsr_valid_statuses', array(
		'wcsr-unended',
		'wcsr-ended',
	) );
}

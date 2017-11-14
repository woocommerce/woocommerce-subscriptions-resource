<?php
/*
 * Plugin Name: WooCommerce Subscriptions Resource
 * Plugin URI: https://github.com/Prospress/woocommerce-subscriptions-resource/
 * Description: A library to track and prorate payments for a subscription using WooCommerce Subscriptions based on the status of an external resource.
 * Author: Prospress Inc.
 * Author URI: https://prospress.com/
 * License: GPLv3
 * Version: 1.0.0
 * Requires at least: 4.0
 * Tested up to: 4.8
 *
 * GitHub Plugin URI: Prospress/woocommerce-subscriptions-resource
 * GitHub Branch: master
 *
 * Copyright 2017 Prospress, Inc.  (email : freedoms@prospress.com)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package	WooCommerce Subscriptions Resource
 * @author	Prospress Inc.
 * @since	1.0.0
 */

/**
 * Loads library if we know WooCommerce is at a valid version and the library hasn't already been loaded.
 *
 * @since 1.0.0
 */
function wcsr_init() {
	if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '3.0.0', '>=' ) && ! class_exists( 'WCSR_Resource' ) ) {
		require_once( 'includes/wcsr-functions.php' );
		require_once( 'includes/class-wcsr-resource.php' );
		require_once( 'includes/class-wcsr-data-store.php' );
		require_once( 'includes/class-wcsr-resource-data-store-cpt.php' );
		require_once( 'includes/class-wcsr-resource-manager.php' );
	}
}
add_action( 'plugins_loaded', 'wcsr_init' );

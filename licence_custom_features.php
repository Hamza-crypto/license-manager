<?php
/**
 * Plugin Name:     Licence Custom Features
 * Description:     It enhances the features of license manager plugin
 * Author:          Hamza Siddique
 * Text Domain:     licence_custom_features
 * Version:         1.0.0
 */

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

require_once __DIR__ . "/vendor/autoload.php";


require_once 'login/authentication.php';
require_once 'woocommerce/email.php';


// Hook to filter WooCommerce template paths
add_filter('woocommerce_locate_template', 'custom_woocommerce_template', 10, 3);

function custom_woocommerce_template($template, $template_name, $template_path) {
    // Define the plugin path to the templates
    $plugin_path = plugin_dir_path(__FILE__) . 'woocommerce/';

    // Check if the template file exists in the plugin directory
    if (file_exists($plugin_path . $template_name)) {
        return $plugin_path . $template_name;
    }

    // Return the original template if not found in the plugin directory
    return $template;
}
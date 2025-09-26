<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://povly.ru
 * @since             1.0.0
 * @package           P_My_Sklad
 *
 * @wordpress-plugin
 * Plugin Name:       Мой Склад интеграция (Woo)
 * Plugin URI:        https://povly.ru
 * Description:       Интеграция Мой Склад с Woo
 * Version:           1.0.0
 * Author:            Porshnyov Anatoly
 * Author URI:        https://povly.ru/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       p_my_sklad
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'P_MY_SKLAD_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-p_my_sklad-activator.php
 */
function activate_p_my_sklad() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-p_my_sklad-activator.php';
	P_My_Sklad_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-p_my_sklad-deactivator.php
 */
function deactivate_p_my_sklad() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-p_my_sklad-deactivator.php';
	P_My_Sklad_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_p_my_sklad' );
register_deactivation_hook( __FILE__, 'deactivate_p_my_sklad' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-p_my_sklad.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_p_my_sklad() {

	$plugin = new P_My_Sklad();
	$plugin->run();

}
run_p_my_sklad();

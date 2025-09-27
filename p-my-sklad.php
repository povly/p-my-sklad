<?php

use P_My_Sklad\Activator;
use P_My_Sklad\Deactivator;

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
 * Version:           1.3.2
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
 */
define('P_MY_SKLAD_VERSION', '1.3.2' );
define('P_MY_SKLAD_FILE', __FILE__);
define('P_MY_SKLAD_DIR', plugin_dir_path(P_MY_SKLAD_FILE));
define('P_MY_SKLAD_BASE', plugin_basename(P_MY_SKLAD_FILE));

function activate() {
	require_once P_MY_SKLAD_DIR . 'Classes/Activator.php';
	Activator::activate();
}


function deactivate() {
	require_once P_MY_SKLAD_DIR . 'Classes/Deactivator.php';
	Deactivator::deactivate();
}

register_activation_hook(P_MY_SKLAD_FILE, 'activate' );
register_deactivation_hook(P_MY_SKLAD_FILE, 'deactivate' );

require P_MY_SKLAD_DIR . 'Classes/P_My_Sklad.php';

/**
 * @since    1.0.0
 */
function run() {

	$plugin = new P_My_Sklad();
	$plugin->run();

}
run();

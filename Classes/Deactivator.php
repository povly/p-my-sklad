<?php

/**
 * Class Deactivator
 *
 * Handles actions that should be performed when the plugin is deactivated.
 * This method is called via `register_deactivation_hook()` in the main plugin file.
 *
 * Note: Persistent data (options, tables) are usually preserved on deactivation.
 *
 * @since      1.0.0
 * @package    P_My_Sklad
 * @link       https://developer.wordpress.org/plugins/the-basics/activation-deactivation-hooks/#deactivation
 */

namespace P_My_Sklad;

class Deactivator
{

	/**
	 * Fired during plugin deactivation.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function deactivate()
	{

	}
}

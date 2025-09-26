<?php

/**
 * Class Activator
 *
 * Handles actions that should be performed when the plugin is activated.
 * This class is typically hooked to `register_activation_hook()` in the main plugin file.
 *
 * @since      1.0.0
 * @package    P_My_Sklad
 * @link       https://developer.wordpress.org/plugins/the-basics/activation-deactivation-hooks/#activation
 */

namespace P_My_Sklad;

class Activator
{

	/**
	 * Fired when the plugin is activated.
	 *
	 * This method should be lightweight and avoid long-running processes.
	 * Use `admin_init` or a redirect for first-time setup wizards.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function activate()
	{

	}
}

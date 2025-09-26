<?php

/**
 * Class I18n
 *
 * Handles the internationalization (i18n) of the plugin.
 * Loads the translation files so that the plugin can be translated into other languages.
 * The text domain 'p_my_sklad' is used for all translations.

 * It should be instantiated and hooked to the `plugins_loaded` action.
 *
 * @since      1.0.0
 * @package    P_My_Sklad
 * @link       https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/
 */

namespace P_My_Sklad;

class I18n
{

	/**
	 * Load the plugin text domain for translation.
	 *
	 * This method is hooked to `plugins_loaded` to ensure translations are available early.
	 * Looks for `.mo` and `.po` files in `/languages/p_my_sklad-xx_XX.mo`.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function load_plugin_textdomain()
	{
		load_plugin_textdomain(
			'p_my_sklad',           // Text domain
			false,                  // Deprecated parameter (set to false)
			P_MY_SKLAD_BASE . '/languages/' // Path to languages folder
		);
	}
}

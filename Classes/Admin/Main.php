<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://povly.ru
 * @since      1.0.0
 * @package    P_My_Sklad
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    P_My_Sklad
 * @subpackage P_My_Sklad/admin
 * @author     Porshnyov Anatoly <povly19995@gmail.com>
 */

namespace P_My_Sklad\Admin;

class Main {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

	}

	public function add_plugin_meta($meta, $file){

		if ($file == P_MY_SKLAD_BASE) {
			$settings_link = sprintf(
				'<a href="%s">%s</a>',
				esc_url(admin_url('admin.php?page=p-my-sklad')),
				esc_html__('Настройки', 'p_my_sklad')
			);

			$meta[] = $settings_link;
		}
		return $meta;
	}

	public function add_cron_intervals($schedules)
	{
		$schedules['every_six_hours'] = [
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => __('Каждые 6 часов', 'p_my_sklad'),
		];

		return $schedules;
	}
}

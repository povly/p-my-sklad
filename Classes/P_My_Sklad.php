<?php

use P_My_Sklad\Admin\Main;
use P_My_Sklad\Admin\Api\Menu;
use P_My_Sklad\Admin\Controllers\Menu_Controller;
use P_My_Sklad\I18n;
use P_My_Sklad\Loader;

/**
 *
 * @since      1.0.0
 */

class P_My_Sklad {

	/**
	 * @since    1.0.0
	 * @access   protected
	 * @var      Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'P_MY_SKLAD_VERSION' ) ) {
			$this->version = P_MY_SKLAD_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'p_my_sklad';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
	}

	/**
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		$classes = [
			'Classes/Loader.php',
			'Classes/I18n.php',

			'Classes/Admin/Main.php',

			'Classes/Admin/Controllers/Base_Controller.php',
			'Classes/Admin/Controllers/Menu_Controller.php',
			'Classes/Admin/Api/Menu.php',
			'Classes/Admin/Api/WC_Logger.php'
		];

		foreach ($classes as $class) {
			require_once P_MY_SKLAD_DIR . $class;
		}

		$this->loader = new Loader();

	}

	/**
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$i18n = new I18n();

		$this->loader->add_action( 'plugins_loaded', $i18n, 'load_plugin_textdomain' );

	}

	/**
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$admin = new Main( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_filter('plugin_row_meta', $admin, 'add_plugin_meta', 10, 2);
		$this->loader->add_filter('cron_schedules', $admin, 'add_cron_intervals');

		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_scripts' );

		$admin_menu = new Menu();
		$admin_menu_controller = new Menu_Controller();

		$admin_menu->add_page([
			'title' => 'Мой Склад',
			'menu_title' => 'Мой Склад',
			'capability' => 'manage_options',
			'menu_slug' => 'p-my-sklad',
			'icon_url' => 'dashicons-cart',
			'position' => 58
		], [$admin_menu_controller, 'render_page_main']);

		$this->loader->add_action('admin_init', $admin_menu_controller, 'handle_page_main' );
		$this->loader->add_action('admin_init', $admin_menu_controller, 'handle_page_main_settings' );

		$this->loader->add_action( 'admin_menu', $admin_menu, 'register' );
	}


	/**
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * @since     1.0.0
	 * @return    Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}

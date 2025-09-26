<?php
/**
 *
 * @since      1.0.0
 * @package    P_My_Sklad
 * @subpackage P_My_Sklad/includes
 * @author     Porshnyov Anatoly <povly19995@gmail.com>
 */
class P_My_Sklad {

	/**
	 * @since    1.0.0
	 * @access   protected
	 * @var      P_My_Sklad_Loader    $loader    Maintains and registers all hooks for the plugin.
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
			'includes/p_my_sklad-loader.php',
			'includes/p_my_sklad-i18n.php',

			'admin/p_my_sklad-admin.php',

			'admin/includes/controllers/p_my_sklad-admin-menu-controller.php',
			'admin/p_my_sklad-admin-menu.php',
		];

		foreach ($classes as $class) {
			require_once plugin_dir_path(dirname(__FILE__)) . $class;
		}

		$this->loader = new P_My_Sklad_Loader();

	}

	/**
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new P_My_Sklad_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new P_My_Sklad_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		$plugin_admin_menu = new P_My_Sklad_Admin_Menu();
		$plugin_admin_menu_controller = new P_My_Sklad_Admin_Menu_Controller();

		$plugin_admin_menu->add_page([
			'title' => 'Мой Склад',
			'menu_title' => 'Мой Склад',
			'capability' => 'manage_options',
			'menu_slug' => 'p-my-sklad',
			'icon_url' => 'dashicons-cart',
		], [$plugin_admin_menu_controller, 'render_page_main']);
		$this->loader->add_action('admin_init', $plugin_admin_menu_controller, 'handle_page_main' );

		$this->loader->add_action( 'admin_menu', $plugin_admin_menu, 'register' );



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
	 * @return    P_My_Sklad_Loader    Orchestrates the hooks of the plugin.
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

<?php

/**
 * Class Menu
 *
 * Handles the registration of admin menu pages and submenu pages in the WordPress admin area.
 * This class provides a structured way to define and register custom admin menus and submenus
 * by collecting page data and registering them via WordPress hooks.
 *
 * @package P_My_Sklad
 * @since 1.0.0
 * @link https://developer.wordpress.org/reference/functions/add_menu_page/ WordPress add_menu_page()
 * @link https://developer.wordpress.org/reference/functions/add_submenu_page/ WordPress add_submenu_page()
 */

namespace P_My_Sklad\Admin\Api;

class Menu
{
  /**
   * Array to store top-level menu pages data.
   *
   * Each item is an associative array containing:
   * - title: The page title (appears in the browser tab).
   * - menu_title: The text displayed in the admin sidebar.
   * - capability: Required user capability to access the page.
   * - menu_slug: Unique identifier for the menu page.
   * - callback: Callable function that outputs the page content.
   * - icon_url: Optional URL or dashicon CSS class for the menu icon.
   * - position: Optional numeric position in the admin menu order.
   *
   * @var array
   * @since 1.0.0
   */
  public $menu_pages = [];

  /**
   * Array to store submenu pages data.
   *
   * Each item is an associative array containing:
   * - parent_slug: Slug of the parent menu (e.g., 'edit.php' or custom slug).
   * - page_title: The page title (browser tab title).
   * - menu_title: The text shown in the submenu.
   * - capability: Required capability to view the submenu.
   * - menu_slug: Unique slug for this submenu page.
   * - callback: Function called to render the page content.
   * - position: Optional position within the submenu list.
   *
   * @var array
   * @since 1.0.0
   */
  public $submenu_pages = [];

  /**
   * Adds a new top-level admin menu page.
   *
   * Collects menu page configuration into the `$menu_pages` array.
   * Registration happens later via the `register()` method.
   *
   * @param array $data {
   *     Configuration data for the menu page.
   *
   *     @type string $title           Page title (used in `<title>` tag).
   *     @type string $menu_title     Text displayed in the admin sidebar.
   *     @type string $capability     Required capability (e.g., 'manage_options').
   *     @type string $menu_slug      Unique slug identifying the page.
   *     @type string $icon_url       [optional] URL to the icon or Dashicon class (e.g., 'dashicons-admin-generic').
   *     @type int    $position       [optional] Position in the admin menu (default: null — added at the end).
   * }
   * @param callable $callback        The function or method to display the page content when accessed.
   *
   * @since 1.0.0
   * @return void
   */
  public function add_page($data, $callback)
  {
    $this->menu_pages[] = [
      'title'        => $data['title'],
      'menu_title'   => $data['menu_title'],
      'capability'   => $data['capability'],
      'menu_slug'    => $data['menu_slug'],
      'callback'     => $callback,
      'icon_url'     => $data['icon_url'] ?? '',
      'position'     => $data['position'] ?? null,
    ];
  }

  /**
   * Adds a new submenu page under a specific parent menu.
   *
   * Stores submenu configuration to be registered later.
   *
   * @param array $data {
   *     Configuration data for the submenu page.
   *
   *     @type string $parent_slug    Slug of the parent menu (e.g., 'p-my-sklad', 'tools.php').
   *     @type string $page_title    Page title (shown in `<title>` tag).
   *     @type string $menu_title    Text displayed in the submenu.
   *     @type string $capability    Required capability to access the page.
   *     @type string $menu_slug     Unique slug for this submenu page.
   *     @type int    $position      [optional] Order position within the submenu (default: null).
   * }
   * @param callable $callback        Function or method to output the page content.
   *
   * @since 1.0.0
   * @return void
   */
  public function add_submenu_page($data, $callback)
  {
    $this->submenu_pages[] = [
      'parent_slug' => $data['parent_slug'],
      'page_title'  => $data['page_title'],
      'menu_title'  => $data['menu_title'],
      'capability'  => $data['capability'],
      'menu_slug'   => $data['menu_slug'],
      'callback'    => $callback,
      'position'    => $data['position'] ?? null,
    ];
  }

  /**
   * Registers all collected menu and submenu pages with WordPress.
   *
   * This method should be called on the `admin_menu` action hook.
   * It iterates over stored menu and submenu configurations and registers them
   * using `add_menu_page()` and `add_submenu_page()`.
   *
   * @since 1.0.0
   * @uses add_menu_page()
   * @uses add_submenu_page()
   * @return void
   */
  public function register()
  {
    foreach ($this->menu_pages as $menu_page) {
      add_menu_page(
        $menu_page['title'],
        $menu_page['menu_title'],
        $menu_page['capability'],
        $menu_page['menu_slug'],
        $menu_page['callback'],
        $menu_page['icon_url'],
        $menu_page['position']
      );
    }

    foreach ($this->submenu_pages as $submenu_page) {
      add_submenu_page(
        $submenu_page['parent_slug'],
        $submenu_page['page_title'],
        $submenu_page['menu_title'],
        $submenu_page['capability'],
        $submenu_page['menu_slug'],
        $submenu_page['callback'],
        $submenu_page['position']
      );
    }
  }
}

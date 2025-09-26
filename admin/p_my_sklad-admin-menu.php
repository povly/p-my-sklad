<?php

/**
 * @package    P_My_Sklad
 * @subpackage P_My_Sklad/admin_menu
 * @author     Porshnyov Anatoly <povly19995@gmail.com>
 */
class P_My_Sklad_Admin_Menu
{
  public $menu_pages = [];

  public $submenu_pages = [];


  public function add_page($data, $callback) {
    $this->menu_pages[] = [
      'title' => $data['title'],
      'menu_title' => $data['menu_title'],
      'capability' => $data['capability'],
      'menu_slug' => $data['menu_slug'],
      'callback' => $callback,
      'icon_url' => $data['icon_url'] ?? '',
      'position' => $data['position'] ?? null
    ];
  }

  public function add_submenu_page($data, $callback) {
    $this->submenu_pages[] = [
      'parent_slug' => $data['parent_slug'],
      'page_title' => $data['page_title'],
      'menu_title' => $data['menu_title'],
      'capability' => $data['capability'],
      'menu_slug' => $data['menu_slug'],
      'callback' => $callback,
      'position' => $data['position'] ?? null
    ];
  }

  public function register() {
    foreach($this->menu_pages as $menu_page) {
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

    foreach($this->submenu_pages as $submenu_page) {
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

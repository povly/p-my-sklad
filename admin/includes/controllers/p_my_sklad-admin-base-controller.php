<?php

/**
 * @package    P_My_Sklad
 * @subpackage P_My_Sklad/admin_base_controller
 * @author     Porshnyov Anatoly <povly19995@gmail.com>
 */
class P_My_Sklad_Admin_Base_Controller
{
  protected function render_template($template_name, $data = [])
  {
    $template_path = P_MY_SKLAD_DIR . "/admin/templates/{$template_name}";

    if (!file_exists($template_path)) {
      wp_die(sprintf(__('Шаблон не найден: %s', 'p-my-sklad'), esc_html($template_name)));
    }

    // Изолируем переменные
    extract($data);

    // Подключаем шаблон (он имеет доступ к $data через extract)
    include $template_path;
  }
}

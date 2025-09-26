<?php

/**
 * @package    P_My_Sklad
 */

namespace P_My_Sklad\Admin\Controllers;

class Base_Controller
{
  protected function render_template($template_name, $data = [])
  {
    $template_path = P_MY_SKLAD_DIR . "/templates/admin/{$template_name}";

    if (!file_exists($template_path)) {
      wp_die(sprintf(__('Шаблон не найден: %s', 'p-my-sklad'), esc_html($template_name)));
    }

    // Изолируем переменные
    extract($data);

    // Подключаем шаблон (он имеет доступ к $data через extract)
    include $template_path;
  }
}

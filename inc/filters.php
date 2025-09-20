<?php

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Добавляет ссылку "Настройки" на странице плагинов.
 *
 * @param array $links Существующие ссылки.
 * @return array Обновлённый массив ссылок.
 */
function add_custom_plugin_link($links)
{
  $settings_link = sprintf(
    '<a href="%s">%s</a>',
    esc_url(admin_url('options-general.php?page=' . P_MY_SKLAD_NAME)),
    esc_html__('Настройки', 'p-my-sklad')
  );

  array_unshift($links, $settings_link);
  return $links;
}

add_filter("plugin_action_links_" . P_MY_SKLAD_SLUG, 'add_custom_plugin_link');

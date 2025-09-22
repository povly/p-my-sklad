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

add_filter('cron_schedules', 'p_my_sklad_add_cron_intervals');

function p_my_sklad_add_cron_intervals($schedules)
{
  $schedules['every_six_hours'] = [
    'interval' => 6 * HOUR_IN_SECONDS,
    'display'  => __('Каждые 6 часов', 'p-my-sklad'),
  ];

  return $schedules;
}

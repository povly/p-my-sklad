<?php

add_action('wp_ajax_p_my_sklad_products_start_sync', 'p_my_sklad_products_start_sync_f');

function p_my_sklad_products_start_sync_f()
{
  if (!wp_verify_nonce($_POST['nonce'] ?? '', 'p_my_sklad_products_sync_nonce')) {
    wp_send_json_error(['message' => __('Недопустимый запрос.', 'p-my-sklad')]);
  }

  if (!current_user_can('manage_options')) {
    wp_send_json_error(['message' => __('Недостаточно прав.', 'p-my-sklad')]);
  }

  // Очищаем предыдущие задачи
  wp_clear_scheduled_hook('p_my_sklad_run_sync_batch');

  $settings = get_option('p_my_sklad_settings_products', []);
  $batch_size = !empty($settings['products_limit']) ? (int)$settings['products_limit'] : 200;

  $initial_state = [
    'status'     => 'in_progress',
    'processed'  => 0,
    'total'      => 0,
    'nextHref'   => null,
    'batch_size' => $batch_size,
    'message'    => 'Запуск синхронизации...',
  ];

  update_option('p_my_sklad_products_sync_progress', $initial_state);

  // Планируем первый шаг
  wp_schedule_single_event(time() + 1, 'p_my_sklad_run_sync_batch');

  wp_send_json_success([
    'status'   => 'in_progress',
    'message'  => 'Синхронизация запущена.',
    'progress' => 0
  ]);
}

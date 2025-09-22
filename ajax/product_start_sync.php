<?php

add_action('wp_ajax_p_my_sklad_products_start_sync', 'p_my_sklad_products_start_sync_f');

function p_my_sklad_products_start_sync_f()
{
  if (!wp_verify_nonce($_POST['nonce'] ?? '', 'p_my_sklad_products_sync_nonce')) {
    wp_send_json_error(['message' => __('Недопустимый запрос. Попробуйте снова.', 'p-my-sklad')]);
  }

  if (!current_user_can('manage_options')) {
    wp_send_json_error(['message' => __('Недостаточно прав.', 'p-my-sklad')]);
  }

  // Очищаем предыдущий прогресс
  delete_option('p_my_sklad_products_sync_progress');
  update_option('p_my_sklad_products_sync_progress', [
    'status'    => 'starting',
    'message'   => 'Запуск фоновой синхронизации...',
    'total'     => 0,
    'processed' => 0
  ]);


  $sync_process = new P_My_Sklad_Products_Sync_Process();


  $sync_process->push_to_queue([
    'nextHref' => null,
    'total'    => 0,
    'processed' => 0
  ]);

  // Сохраняем и запускаем очередь
  $sync_process->save()->dispatch();

  wp_send_json_success([
    'status'  => 'starting',
    'message' => 'Синхронизация запущена в фоновом режиме.',
    'progress' => 0
  ]);
}

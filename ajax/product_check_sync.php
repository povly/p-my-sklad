<?php

add_action('wp_ajax_p_my_sklad_products_check_sync_status', 'p_my_sklad_products_check_sync_status_f');

function p_my_sklad_products_check_sync_status_f()
{
  if (!wp_verify_nonce($_POST['nonce'] ?? '', 'p_my_sklad_products_check_sync_nonce')) {
    wp_send_json_error(['message' => __('Недопустимый запрос. Попробуйте снова.', 'p-my-sklad')]);
  }

  if (!current_user_can('manage_options')) {
    wp_send_json_error(['message' => __('Недостаточно прав.', 'p-my-sklad')]);
  }

  $progress = get_option('p_my_sklad_products_sync_progress', [
    'status'    => 'idle',
    'message'   => 'Синхронизация не запущена.',
    'total'     => 0,
    'processed' => 0
  ]);

  $percentage = 0;
  if ($progress['total'] > 0) {
    $percentage = intval(($progress['processed'] / $progress['total']) * 100);
  }

  wp_send_json_success([
    'status'  => $progress['status'],
    'message' => $progress['message'],
    'progress' => $percentage
  ]);
}

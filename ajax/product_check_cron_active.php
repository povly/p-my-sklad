<?php

add_action('wp_ajax_p_my_sklad_product_check_cron_active', 'p_my_sklad_product_check_cron_active_f');

function p_my_sklad_product_check_cron_active_f()
{
  if (!wp_verify_nonce($_POST['nonce'] ?? '', 'p_my_sklad_products_check_sync_nonce')) {
    wp_send_json_error(['message' => 'Ошибка безопасности.']);
  }

  if (!current_user_can('manage_options')) {
    wp_send_json_error(['message' => 'Нет прав.']);
  }

  $is_scheduled = (bool) wp_next_scheduled('p_my_sklad_run_sync_batch');

  wp_send_json_success([
    'is_scheduled' => $is_scheduled
  ]);
}

<?php

class P_My_Sklad_Products_Sync_Process extends WP_Background_Process
{

  protected $prefix = 'p_my_sklad';
  protected $action = 'products_sync';


  protected function task($item)
  {
    $settings = get_option('p_my_sklad_settings_products');

    // Получаем токен
    $token = get_option('p_my_sklad_access_token');
    if (!$token) {
      update_option('p_my_sklad_sync_progress', [
        'total'     => $item['total'] ?? 0,
        'processed' => $item['processed'],
        'status'    => 'error',
        'message'   => 'P-My-Sklad Background Sync: Токен не найден.'
      ]);

      return false;
    }

    // URL для запроса
    $url = !empty($item['nextHref'])
      ? $item['nextHref']
      : 'https://api.moysklad.ru/api/remap/1.2/entity/assortment?limit=' . $settings['products_limit'];

    // Задержка 3 секунды
    sleep(3);

    // Запрос к API МойСклад
    $response = wp_remote_get($url, [
      'headers' => [
        'Authorization'   => "Bearer {$token}",
        'Accept-Encoding' => 'gzip',
      ],
      'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
      update_option('p_my_sklad_sync_progress', [
        'total'     => $item['total'] ?? 0,
        'processed' => $item['processed'],
        'status'    => 'error',
        'message'   => 'P-My-Sklad Background Sync: Ошибка API: ' . $response->get_error_message()
      ]);
      return $item; // Возвращаем элемент для повторной попытки
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (empty($data) || !isset($data['rows'])) {

      update_option('p_my_sklad_sync_progress', [
        'total'     => $item['total'] ?? 0,
        'processed' => $item['processed'],
        'status'    => 'error',
        'message'   => 'P-My-Sklad Background Sync: Неверный ответ от API.'
      ]);
      return false; // Завершаем
    }

    // Если это первый запрос, инициализируем total
    if (!isset($item['total']) && isset($data['meta']['size'])) {
      $item['total'] = intval($data['meta']['size']);
      $item['processed'] = 0;
    }

    // Импортируем товары
    foreach ($data['rows'] as $product) {
      // p_my_sklad_import_single_product($product);
      error_log($product['code'] . '; ');
      $item['processed']++;
    }

    // Обновляем состояние прогресса
    update_option('p_my_sklad_sync_progress', [
      'total'     => $item['total'] ?? 0,
      'processed' => $item['processed'],
      'status'    => 'in_progress',
      'chain_id' => $this->get_chain_id(),
      'message'   => "Обработано {$item['processed']} из " . ($item['total'] ?? '...') . " товаров."
    ]);

    // // Проверяем, есть ли следующая страница
    // if (!empty($data['meta']['nextHref'])) {
    //   // Возвращаем обновленный элемент для следующей итерации
    //   $item['nextHref'] = $data['meta']['nextHref'];
    //   return $item;
    // } else {
    //   // Завершаем синхронизацию
    //   update_option('p_my_sklad_sync_progress', [
    //     'total'     => $item['total'] ?? 0,
    //     'processed' => $item['processed'],
    //     'status'    => 'completed',
    //     'message'   => 'Синхронизация успешно завершена!'
    //   ]);
    //   return false; // false = задача завершена
    // }

    if ($data['meta']['offset'] < 3) {
      // Возвращаем обновленный элемент для следующей итерации
      $item['nextHref'] = $data['meta']['nextHref'];
      return $item;
    } else {
      // Завершаем синхронизацию
      update_option('p_my_sklad_sync_progress', [
        'total'     => $item['total'] ?? 0,
        'processed' => $item['processed'],
        'status'    => 'completed',
        'message'   => 'Синхронизация успешно завершена!'
      ]);
      return false; // false = задача завершена
    }

    return false;
  }

  protected function complete()
  {
    parent::complete();

  }
}

<?php

if (!defined('ABSPATH')) {
  exit;
}


/**
 * Загружает изображение по URL и возвращает ID медиафайла.
 *
 * @param string $url URL изображения
 * @param int    $parent_post_id ID поста, к которому будет привязано изображение
 *
 * @return int|WP_Error
 */
function p_my_sklad_sideload_image($url, $parent_post_id)
{
  require_once ABSPATH . 'wp-admin/includes/file.php';
  require_once ABSPATH . 'wp-admin/includes/media.php';
  require_once ABSPATH . 'wp-admin/includes/image.php';

  // Загрузка файла в tmp‑директорию
  $tmp = download_url($url);
  if (is_wp_error($tmp)) {
    return $tmp;
  }

  // Получаем имя и тип MIME
  $file_array = [
    'name'     => basename($url),
    'tmp_name' => $tmp,
  ];

  // Добавляем в медиатеку
  $id = media_handle_sideload($file_array, $parent_post_id);

  // Если произошла ошибка – удаляем временный файл
  if (is_wp_error($id)) {
    @unlink($tmp);
    return $id;
  }

  return $id;
}

function p_my_sklad_dd($data)
{
  echo '<pre>';
  echo print_r($data, true);
  echo '</pre>';
}

function p_my_sklad_get_assortments()
{
  $token = get_option('p_my_sklad_access_token');
  if (!$token) {
    add_settings_error(
      P_MY_SKLAD_NAME . '_product',
      'token_failed',
      __('Токен не найден', 'p-my-sklad'),
      'error'
    );
    return;
  }

  $response = wp_remote_get(
    'https://api.moysklad.ru/api/remap/1.2/entity/assortment?limit=5&offset=0',
    [
      'headers' => [
        'Authorization'   => "Bearer {$token}",
        'Accept-Encoding' => 'gzip',
      ],
      'timeout' => 15,
    ]
  );

  if (is_wp_error($response)) {
    add_settings_error(
      P_MY_SKLAD_NAME . '_product',
      'token_failed',
      __('p-my-sklad API error: ' . $response->get_error_message(), 'p-my-sklad'),
      'error'
    );
    return null;
  }

  $body = wp_remote_retrieve_body($response);
  $data = json_decode($body, true);

  return isset($data['rows']) ? $data['rows'] : [];
}

/**
 * Импортирует или обновляет товар WooCommerce на основе данных из МойСклад
 */
function p_my_sklad_import_single_product($ms_product)
{
  // === 1. Проверяем фильтр категории ===
  $settings = get_option('p_my_sklad_settings_products', []);
  $filter_path = $settings['categories_filters'] ?? '';

  $product_path = $ms_product['pathName'] ?? '';

  if (!empty($filter_path) && strpos($product_path, $filter_path) !== 0) {
    return; // Пропускаем, если не совпадает путь
  }

  // === 2. Получаем код товара из МойСклад ===
  $ms_code = $ms_product['code'] ?? '';
  if (empty($ms_code)) {
    error_log("MySklad: Пропущен товар без code: " . ($ms_product['name'] ?? 'N/A'));
    return;
  }

  // === 3. Ищем товар в WooCommerce по мета-полю ===
  $args = [
    'post_type'  => 'product',
    'meta_query' => [
      [
        'key'   => 'p_my_sklad_code',
        'value' => $ms_code,
      ],
    ],
    'posts_per_page' => 1,
  ];

  $query = new WP_Query($args);
  $product_id = 0;

  if ($query->have_posts()) {
    $query->the_post();
    $product_id = get_the_ID();
    wc_setup_product_data($product_id); // Подготавливаем для WC_Product
  }

  // === 4. Создаём или получаем объект товара ===
  $product = $product_id ? wc_get_product($product_id) : new WC_Product();

  // === 5. Заполняем основные поля ===
  $name = $ms_product['name'] ?? 'Без названия';
  $description = $ms_product['description'] ?? '';
  $quantity = isset($ms_product['quantity']) ? (float) $ms_product['quantity'] : 0;

  $product->set_name($name);
  $product->set_description($description);
  $product->set_short_description($description); // Можно отдельно, если нужно
  $product->set_stock_quantity($quantity);
  $product->set_manage_stock(true);
  $product->set_stock_status($quantity > 0 ? 'instock' : 'outofstock');

  // === 6. Устанавливаем цену из salePrices ===
  $price = 0;
  if (!empty($ms_product['salePrices'])) {
    foreach ($ms_product['salePrices'] as $sale_price) {
      // Ищем цену с типом "Цена продажи" или просто первую
      if (isset($sale_price['value'])) {
        $price = (float) $sale_price['value'] / 100; // ← ДЕЛИМ НА 100!
        break;
      }
    }
  }

  $product->set_regular_price($price);
  $product->set_price($price); // Устанавливаем текущую цену

  // === 7. Сохраняем товар и мета-поле с кодом МойСклад ===
  $product_id = $product->save();

  if ($product_id) {
    update_post_meta($product_id, 'p_my_sklad_code', $ms_code);
    update_post_meta($product_id, 'p_my_sklad_updated_at', current_time('mysql'));

    error_log("MySklad: Товар '{$name}' (код: {$ms_code}) успешно " . ($product_id == $product->get_id() ? 'обновлён' : 'создан') . ". ID: {$product_id}");
  } else {
    error_log("MySklad: Ошибка сохранения товара '{$name}' (код: {$ms_code})");
  }
}

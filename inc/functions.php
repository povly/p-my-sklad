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
  $filter_path = trim($settings['categories_filters'] ?? '');
  $product_path = $ms_product['pathName'] ?? '';

  if (!empty($filter_path)) {
    if (strpos($product_path, $filter_path) !== 0) {
      return; // Пропускаем, если не начинается с указанного пути
    }
  }

  // === 2. Получаем код товара из МойСклад ===
  $ms_code = $ms_product['code'] ?? '';
  if (empty($ms_code)) {
    // error_log("MySklad: Пропущен товар без code: " . ($ms_product['name'] ?? 'N/A'));
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
    wc_setup_product_data($product_id);
  }

  // === 4. Создаём или получаем объект товара ===
  $product = $product_id ? wc_get_product($product_id) : new WC_Product();

  // === 5. Заполняем основные поля ===
  $name = $ms_product['name'] ?? 'Без названия';
  $description = $ms_product['description'] ?? '';
  $quantity = isset($ms_product['quantity']) ? (float) $ms_product['quantity'] : 0;

  $product->set_name($name);
  $product->set_description($description);
  $product->set_short_description($description);
  $product->set_stock_quantity($quantity);
  $product->set_manage_stock(true);
  $product->set_stock_status($quantity > 0 ? 'instock' : 'outofstock');

  // === 6. Устанавливаем цены из salePrices ===
  $regular_price = 0;
  $sale_price = 0;

  if (!empty($ms_product['salePrices'])) {
    foreach ($ms_product['salePrices'] as $sale_price_item) {
      if (!isset($sale_price_item['value']) || !isset($sale_price_item['priceType']['name'])) {
        continue;
      }

      $value = (float) $sale_price_item['value'] / 100; // Делим на 100 → рубли
      $price_type_name = $sale_price_item['priceType']['name'];

      if ($price_type_name === 'Цена продажи') {
        $regular_price = $value;
      } elseif ($price_type_name === 'Цена со скидкой' && $value > 0) {
        $sale_price = $value;
      }
    }
  }

  $product->set_regular_price($regular_price);

  if ($sale_price > 0 && $sale_price < $regular_price) {
    $product->set_sale_price($sale_price);
  } else {
    $product->set_sale_price('');
  }

  $product->set_price($sale_price > 0 ? $sale_price : $regular_price);


  $product_id = $product->save();

  // === 6.5. Проверка категории товара и ACF-фильтра p_my_sklad_categories (с fallback на имя категории) ===
  static $cached_categories = null;       // Кеш: [category_id => full_name]
  static $cached_acf_categories = null;   // Кеш: [category_id => массив разрешённых подкатегорий]

  // Инициализируем кеш ACF, если не создан
  if ($cached_acf_categories === null) {
      $cached_acf_categories = [];
  }

  // Получаем путь категории товара из МойСклад
  if (empty($product_path)) {
    // error_log("MySklad: Товар '{$ms_product['name'] ?? 'N/A'}' (код: {$ms_code}) не имеет pathName — пропуск категории.");
    $product->set_status('draft');
    $product->save();
    return;
  }

  // Разбиваем путь: например "Cайт/Сладкое" → ['Cайт', 'Сладкое']
  $path_parts = explode('/', $product_path);
  $category_name = trim(end($path_parts)); // "Сладкое"
  $full_category_path = $product_path;     // "Cайт/Сладкое"

  // Инициализируем кеш категорий, если не загружен
  if ($cached_categories === null) {
      $all_categories = get_terms([
          'taxonomy'   => 'product_cat',
          'hide_empty' => false,
          'fields'     => 'id=>name', // [ID => 'Cайт/Сладкое']
      ]);

      if (!is_wp_error($all_categories)) {
          $cached_categories = $all_categories;
          error_log("MySklad: Кеш категорий инициализирован, загружено " . count($cached_categories) . " категорий.");
      } else {
          $cached_categories = [];
          error_log("MySklad: Ошибка загрузки категорий: " . $all_categories->get_error_message());
      }
  }

  // Ищем ID категории WooCommerce по полному пути
  $category_id = array_search($full_category_path, $cached_categories);

  if ($category_id === false) {
      error_log("MySklad: Категория '{$full_category_path}' не найдена в WooCommerce для товара ID: {$product_id}. Товар скрыт.");
      $product->set_status('draft');
      $product->save();
      return;
  }

  // Проверяем, есть ли уже закешированное значение ACF для этой категории
  if (!isset($cached_acf_categories[$category_id])) {
      $allowed_subcats_raw = get_field('p_my_sklad_categories', 'product_cat_' . $category_id);

      if (empty($allowed_subcats_raw)) {
          // Кешируем как null или пустой массив — fallback будет использован
          $cached_acf_categories[$category_id] = [];
          error_log("MySklad: У категории '{$full_category_path}' (ID: {$category_id}) не заполнено поле p_my_sklad_categories — будет использован fallback.");
      } else {
          // Парсим и кешируем
          $cached_acf_categories[$category_id] = array_map('trim', explode(';', $allowed_subcats_raw));
          error_log("MySklad: Закешированы разрешённые подкатегории для категории ID {$category_id}: " . implode(', ', $cached_acf_categories[$category_id]));
      }
  }

  $allowed_subcats = $cached_acf_categories[$category_id];

  $is_allowed = false;

  // 1. Проверяем ACF-фильтр, если он не пустой
  if (!empty($allowed_subcats)) {
      if (in_array($category_name, $allowed_subcats)) {
          $is_allowed = true;
          error_log("MySklad: Товар ID: {$product_id} разрешён через ACF-фильтр — '{$category_name}' найдена в списке.");
      } else {
          error_log("MySklad: Товар ID: {$product_id} — '{$category_name}' не найдена в ACF-списке: " . implode(', ', $allowed_subcats));
      }
  }

  // 2. Если не разрешено через ACF — применяем fallback: совпадает ли $category_name с именем категории (последняя часть)
  if (!$is_allowed) {
      // Имя категории в WooCommerce — "Cайт/Сладкое" → последняя часть: "Сладкое"
      $category_display_name = trim(end(explode('/', $full_category_path)));

      if ($category_name === $category_display_name) {
          $is_allowed = true;
          error_log("MySklad: Товар ID: {$product_id} разрешён через fallback — совпадение с именем категории '{$category_display_name}'.");
      } else {
          error_log("MySklad: Fallback не сработал — '{$category_name}' не совпадает с именем категории '{$category_display_name}'.");
      }
  }

  // Применяем результат
  if ($is_allowed) {
      wp_set_object_terms($product_id, (int)$category_id, 'product_cat');
      $product->set_status('publish');
      error_log("MySklad: Товар ID: {$product_id} опубликован.");
  } else {
      $product->set_status('draft');
      error_log("MySklad: Товар ID: {$product_id} скрыт — не прошёл ни ACF-фильтр, ни fallback.");
  }


  // === 7. Загружаем изображения (если есть) ===
  $token = get_option('p_my_sklad_access_token');
  $attachment_ids = [];

  if ($token && !empty($ms_product['images']['meta']['href'])) {
    $image_rows = p_my_sklad_fetch_product_images($ms_product['meta']['href'], $token);

    foreach ($image_rows as $image) {
      if (!empty($image['meta']['downloadHref'])) {
        $download_url = trim($image['meta']['downloadHref']);
        $filename = $image['filename'] ?? 'image.jpg';
        $attachment_id = p_my_sklad_download_and_attach_image($download_url, $filename, $product_id, $token);

        if ($attachment_id) {
          $attachment_ids[] = $attachment_id;
        }
      }
    }

    if (!empty($attachment_ids)) {
      set_post_thumbnail($product_id, $attachment_ids[0]);
      $product->set_gallery_image_ids(array_slice($attachment_ids, 1));
    }
  }

  // === 8. Устанавливаем единицу измерения ===
  if (!empty($ms_product['uom']['meta']['href'])) {
    $token = get_option('p_my_sklad_access_token');
    if ($token) {
      $uom_name = p_my_sklad_fetch_uom_name($ms_product['uom']['meta']['href'], $token);
      if (!empty($uom_name)) {
        update_post_meta($product_id, '_oh_product_unit_name', $uom_name);
        // error_log("MySklad: Установлена единица измерения: {$uom_name} для товара ID: {$product_id}");
      }
    }
  }

  // === 9. Сохраняем товар и мета-поле с кодом МойСклад ===

  if ($product_id) {
    update_post_meta($product_id, 'p_my_sklad_code', $ms_code);
    update_post_meta($product_id, 'p_my_sklad_updated_at', current_time('mysql'));

    // error_log("MySklad: Товар '{$name}' (код: {$ms_code}) успешно " . ($product_id == $product->get_id() ? 'обновлён' : 'создан') . ". ID: {$product_id}");
  } else {
    // error_log("MySklad: Ошибка сохранения товара '{$name}' (код: {$ms_code})");
  }
}

/**
 * Получает список изображений товара из API МойСклад
 */
function p_my_sklad_fetch_product_images($product_href, $token)
{
  $images_url = $product_href . '/images';

  $response = wp_remote_get($images_url, [
    'headers' => [
      'Authorization'   => "Bearer {$token}",
      'Accept-Encoding' => 'gzip',
    ],
    'timeout' => 30,
  ]);

  if (is_wp_error($response)) {
    // error_log("MySklad: Ошибка получения изображений для {$product_href}: " . $response->get_error_message());
    return [];
  }

  $body = wp_remote_retrieve_body($response);
  $data = json_decode($body, true);

  return $data['rows'] ?? [];
}


/**
 * Ищет вложение по сохранённому URL из МойСклад
 */
function p_my_sklad_find_attachment_by_source_url($source_url)
{
  global $wpdb;

  $query = $wpdb->prepare(
    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
    '_p_my_sklad_image_source_url',
    $source_url
  );

  $attachment_id = $wpdb->get_var($query);
  return $attachment_id ? (int) $attachment_id : false;
}

/**
 * Скачивает изображение через временную ссылку (Location) и загружает его в медиатеку WordPress.
 * Возвращает ID вложения или 0.
 */
function p_my_sklad_download_and_attach_image($download_url, $filename, $product_id, $token)
{

  if (!function_exists('media_handle_sideload')) {
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
  }

  // Проверяем, не загружали ли мы уже это изображение
  $existing_attachment = p_my_sklad_find_attachment_by_source_url($download_url);
  if ($existing_attachment) {
    // error_log("MySklad: Изображение уже загружено: {$download_url} (ID: {$existing_attachment})");
    return $existing_attachment;
  }

  // === ШАГ 1: Получаем временную ссылку через редирект ===
  $response = wp_remote_get($download_url, [
    'headers' => [
      'Authorization' => "Bearer {$token}",
      'Accept-Encoding' => 'gzip',
    ],
    'timeout'   => 30,
    'redirection' => 0, // ← Отключаем автоматический редирект, чтобы получить Location вручную
  ]);

  if (is_wp_error($response)) {
    // error_log("MySklad: Ошибка получения временной ссылки для {$download_url}: " . $response->get_error_message());
    return 0;
  }

  $code = wp_remote_retrieve_response_code($response);
  if ($code !== 302) {
    // error_log("MySklad: Ожидался редирект 302, получен код {$code} для {$download_url}");
    return 0;
  }

  $headers = wp_remote_retrieve_headers($response);
  if (empty($headers['location'])) {
    // error_log("MySklad: Заголовок Location отсутствует для {$download_url}");
    return 0;
  }

  $temporary_url = $headers['location'];
  // error_log("MySklad: Получена временная ссылка: {$temporary_url}");

  // === ШАГ 2: Скачиваем файл по временной ссылке (без авторизации) ===
  $tmp_file = download_url($temporary_url);
  if (is_wp_error($tmp_file)) {
    // error_log("MySklad: Ошибка скачивания изображения по временной ссылке {$temporary_url}: " . $tmp_file->get_error_message());
    return 0;
  }

  // === ШАГ 3: Загружаем в медиатеку ===
  $file_array = [
    'name'     => sanitize_file_name($filename),
    'tmp_name' => $tmp_file,
  ];

  $attachment_id = media_handle_sideload($file_array, $product_id);
  if (is_wp_error($attachment_id)) {
    // error_log("MySklad: Ошибка загрузки изображения в медиатеку: " . $attachment_id->get_error_message());
    @unlink($tmp_file);
    return 0;
  }

  // Сохраняем исходный URL (downloadHref), чтобы не скачивать повторно
  update_post_meta($attachment_id, '_p_my_sklad_image_source_url', $download_url);

  // error_log("MySklad: Изображение загружено через временную ссылку: {$download_url} (ID: {$attachment_id})");
  return $attachment_id;
}


/**
 * Получает название единицы измерения по ссылке из API МойСклад
 */
function p_my_sklad_fetch_uom_name($uom_href, $token)
{
  if (empty($uom_href) || empty($token)) {
    return '';
  }

  // Обрезаем пробелы — они есть в твоих данных!
  $uom_href = trim($uom_href);

  $response = wp_remote_get($uom_href, [
    'headers' => [
      'Authorization'   => "Bearer {$token}",
      'Accept-Encoding' => 'gzip',
    ],
    'timeout' => 30,
  ]);

  if (is_wp_error($response)) {
    // error_log("MySklad: Ошибка получения единицы измерения: " . $response->get_error_message());
    return '';
  }

  $code = wp_remote_retrieve_response_code($response);
  if ($code !== 200) {
    // error_log("MySklad: Ошибка HTTP {$code} при получении единицы измерения: {$uom_href}");
    return '';
  }

  $body = wp_remote_retrieve_body($response);
  $data = json_decode($body, true);

  return $data['name'] ?? '';
}

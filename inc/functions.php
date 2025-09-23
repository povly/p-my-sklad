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

  // Сохраняем товар, чтобы получить ID, если создавали новый
  $product_id = $product->save();
  error_log('Товар: ' . $product->get_name() . ' ID: ' . $product_id);

  // === 6.5. Проверка категории товара через ACF-фильтр p_my_sklad_categories или fallback по имени ===
  static $cached_categories = null;         // [category_id => full_name]
  static $cached_acf_categories = null;     // [category_id => ['raw' => [...], 'normalized' => [...]] ]

  // Инициализируем кеши, если не созданы
  if ($cached_categories === null) {
    $all_categories = get_terms([
      'taxonomy'   => 'product_cat',
      'hide_empty' => false,
    ]);

    if (!is_wp_error($all_categories)) {
      $cached_categories = [];
      $cached_acf_categories = [];

      foreach ($all_categories as $term) {
        $term_id = $term->term_id;
        $full_name = $term->name;

        $cached_categories[$term_id] = $full_name;

        // Загружаем ACF-поле для категории
        $allowed_subcats_raw = '';
        if (function_exists('get_field')) {
          $allowed_subcats_raw = get_field('p_my_sklad_categories', 'product_cat_' . $term_id);
        }

        if (empty($allowed_subcats_raw)) {
          $cached_acf_categories[$term_id] = [
            'raw' => [],
            'normalized' => []
          ];
          // error_log("MySklad: У категории '{$full_name}' (ID: {$term_id}) не заполнено поле p_my_sklad_categories — будет использован fallback по совпадению имени.");
        } else {
          $raw_list = array_map('trim', explode(';', $allowed_subcats_raw));
          $normalized_list = array_map(function ($item) {
            return mb_strtolower($item);
          }, $raw_list);

          $cached_acf_categories[$term_id] = [
            'raw' => $raw_list,
            'normalized' => $normalized_list
          ];
          // error_log("MySklad: Закешированы разрешённые подкатегории для категории ID {$term_id}: " . implode(', ', $raw_list));
        }
      }

      // error_log("MySklad: Кеш категорий и ACF-полей инициализирован, загружено " . count($cached_categories) . " категорий.");
    } else {
      $cached_categories = [];
      $cached_acf_categories = [];
      // error_log("MySklad: Ошибка загрузки категорий: " . $all_categories->get_error_message());
    }
  }

  // // ============ ОТЛАДКА: ПОЛНЫЙ СПИСОК КАТЕГОРИЙ И ИХ ACF-ЗНАЧЕНИЙ ============
  // // error_log("=== ОТЛАДКА: ПОЛНЫЙ СПИСОК КАТЕГОРИЙ WOOCOMMERCE ===");
  // foreach ($cached_categories as $id => $name) {
  //   $acf_data = $cached_acf_categories[$id] ?? ['raw' => [], 'normalized' => []];
  //   $raw_values = implode("', '", $acf_data['raw'] ?? []);
  //   $normalized_values = implode("', '", $acf_data['normalized'] ?? []);

  //   // error_log("ID: {$id} | Название: '{$name}' | ACF (сырые): ['{$raw_values}'] | ACF (нормализованные): ['{$normalized_values}']");
  // }
  // // error_log("=== КОНЕЦ СПИСКА КАТЕГОРИЙ ===");
  // // ============ КОНЕЦ ОТЛАДКИ ============

  // Получаем путь категории товара из МойСклад
  if (empty($product_path)) {
    $product_name = isset($ms_product['name']) ? $ms_product['name'] : 'N/A';
    // error_log("MySklad: Товар '{$product_name}' (код: {$ms_code}) не имеет pathName — пропуск категории.");
    $product->set_status('draft');
    $product->save();
    // Продолжаем — сохраняем мета и изображения, даже если черновик
  }

  // ← ИСПРАВЛЕНИЕ: Берём только последнюю часть пути и нормализуем
  $parts = explode('/', $product_path);
  $extracted_subcat_name = trim(end($parts)); // ← ВАЖНО: новая переменная, чтобы не перезаписывать!
  $normalized_subcat = mb_strtolower($extracted_subcat_name);

  // error_log("DEBUG: Исходный путь: '{$product_path}' → Извлечённая подкатегория: '{$extracted_subcat_name}' (нормализовано: '{$normalized_subcat}')");

  $category_id = false;

  // Этап 1: Пробуем найти категорию WooCommerce с точно таким же именем (fallback)
  if (!empty($extracted_subcat_name)) {
    foreach ($cached_categories as $id => $full_name) {
      if (mb_strtolower(trim($full_name)) === $normalized_subcat) {
        $category_id = $id;
        // error_log("M: Найдено точное совпадение категории по имени: '{$extracted_subcat_name}' → ID: {$category_id}. Fallback применён.");
        break;
      }
    }

    // Этап 2: Если не нашли — ищем категорию, в ACF которой разрешена эта подкатегория
    if ($category_id === false) {
      foreach ($cached_acf_categories as $id => $data) {
        $normalized_list = $data['normalized'] ?? [];
        if (in_array($normalized_subcat, $normalized_list)) {
          $category_id = $id;
          // error_log("MySklad: Подкатегория '{$extracted_subcat_name}' (нормализовано: '{$normalized_subcat}') разрешена в категории ID {$id} ('{$cached_categories[$id]}') через ACF-фильтр.");
          break;
        }
      }
    }
  }

  // Применяем результат
  if ($category_id !== false) {
    $result = wp_set_object_terms($product_id, (int)$category_id, 'product_cat');
    if (is_wp_error($result)) {
      // error_log("MySklad: Ошибка при назначении категории: " . $result->get_error_message());
    }
    $product->set_status('publish');
    $product->save(); // ← Сохраняем статус и связи
    // error_log("MySklad: Товар ID: {$product_id} опубликован в категории ID: {$category_id} ('{$cached_categories[$category_id]}').");
  } else {
    $product->set_status('draft');
    $product->save(); // ← И здесь тоже
    // error_log("MySklad: Не найдена ни одна категория WooCommerce, разрешающая подкатегорию '{$extracted_subcat_name}' для товара ID: {$product_id}. Товар скрыт.");
  }

  // error_log("DEBUG: category_id = " . var_export($category_id, true) . ", type = " . gettype($category_id));

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
      }
    }
  }

  // === 9. Сохраняем мета-поля с кодом МойСклад и временем обновления ===
  update_post_meta($product_id, 'p_my_sklad_code', $ms_code);
  update_post_meta($product_id, 'p_my_sklad_updated_at', current_time('mysql'));

  // Финальное сохранение товара (на случай, если менялся статус или галерея)
  $product->save();

  // Раскомментируй, если нужно логировать успешное завершение
  // error_log("MySklad: Товар '{$name}' (код: {$ms_code}) успешно " . ($product_id == $product->get_id() ? 'обновлён' : 'создан') . ". ID: {$product_id}");
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

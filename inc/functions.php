<?php

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Получение товаров из MoySklad
 */
function p_my_sklad_get_products()
{
  $token = get_option('p_my_sklad_access_token');
  if (!$token) {
    error_log('p-my-sklad: Токен не найден');
    return null;
  }

  $response = wp_remote_get(
    'https://api.moysklad.ru/api/remap/1.2/entity/product',
    [
      'headers' => [
        'Authorization'   => "Bearer {$token}",
        'Accept-Encoding' => 'gzip',
      ],
      'timeout' => 15,
    ]
  );

  if (is_wp_error($response)) {
    error_log('p-my-sklad API error: ' . $response->get_error_message());
    return null;
  }

  $body = wp_remote_retrieve_body($response);
  $data = json_decode($body, true);

  // Получаем директорию для сохранения
  $upload_dir = p_my_sklad_get_upload_dir();
  if ($upload_dir) {
    // Сохраняем сырые данные в файл с временной меткой
    $save_file = $upload_dir . '/ms-products-' . date('Y-m-d_H-i-s') . '.json';
    file_put_contents(
      $save_file,
      json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
  }

  return isset($data['rows']) ? $data['rows'] : [];
}


/**
 * Добавляет или обновляет товар WooCommerce.
 *
 * @param array $ms_product Данные товара из MoySklad.
 */
function p_my_sklad_import_single_product($ms_product)
{
  // Идентификатор товара в MoySklad – будем использовать как SKU
  $sku = $ms_product['code'] ?? $ms_product['id'];

  // Проверяем, есть ли уже такой товар (по SKU)
  $existing_id = wc_get_product_id_by_sku($sku);

  if ($existing_id) {
    $product = wc_get_product($existing_id);
  } else {
    $product = new WC_Product_Simple();
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
  }

  /* --- Основные поля --- */
  $product->set_name(wp_strip_all_tags($ms_product['name']));
  $product->set_slug(sanitize_title($ms_product['name']));

  // Описание (можно взять из «description» или «fullDescription»)
  $desc = !empty($ms_product['fullDescription'])
    ? $ms_product['fullDescription']
    : $ms_product['description'];
  $product->set_description(wp_kses_post($desc));
  $product->set_short_description(wp_trim_words($desc, 30));

  // SKU
  $product->set_sku($sku);

  /* --- Цена --- */
  if (isset($ms_product['salePrices'][0]['value'])) {
    $price = floatval($ms_product['salePrices'][0]['value']);
  } elseif (isset($ms_product['buyPrice']['value'])) {
    $price = floatval($ms_product['buyPrice']['value']);
  } else {
    $price = 0;
  }
  $product->set_regular_price($price);
  $product->set_sale_price(''); // можно добавить логику скидок

  /* --- Изображения --- */
  if (!empty($ms_product['images'])) {
    foreach ($ms_product['images'] as $image) {
      $url = $image['meta']['href']; // ссылка на картинку
      // Загружаем изображение в медиатеку и присваиваем как главное/альтернативные
      $media_id = p_my_sklad_sideload_image($url, $product->get_id());
      if ($media_id && !$product->has_image()) {
        $product->set_image_id($media_id); // основное изображение
      } else {
        $product->add_gallery_image($media_id); // галерея
      }
    }
  }

  /* --- Категории --- */
  if (!empty($ms_product['group']['meta']['href'])) {
    $category_name = $ms_product['group']['name'] ?? 'MoySklad';
    // Проверяем, существует ли такая категория WooCommerce
    $term = term_exists($category_name, 'product_cat');
    if (!$term) {
      // Создаём новую категорию
      wp_insert_term(
        $category_name,
        'product_cat',
        [
          'slug' => sanitize_title($category_name),
        ]
      );
      $term = term_exists($category_name, 'product_cat');
    }
    if ($term && !is_wp_error($term)) {
      wp_set_object_terms($product->get_id(), intval($term['term_id']), 'product_cat', false);
    }
  }

  /* --- Атрибуты (если нужны) --- */
  // Пример: добавляем атрибут «Вес»
  if (!empty($ms_product['weight'])) {
    $attribute = new WC_Product_Attribute();
    $attribute->set_name('Weight');
    $attribute->set_options([floatval($ms_product['weight']) . ' kg']);
    $attribute->set_position(0);
    $attribute->set_visible(true);
    $attribute->set_variation(false);

    $product->add_attribute($attribute);
  }

  /* --- Сохраняем продукт --- */
  $product_id = $product->save();

  // Запоминаем, что этот товар уже импортирован (опция)
  update_post_meta($product_id, '_ms_product_id', $ms_product['id']);
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

// $products = p_my_sklad_get_products();


?>


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
		'name' => basename($url),
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
				'Authorization' => "Bearer {$token}",
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
 *
 * @param array $ms_product Данные товара из API МойСклад
 * @return bool|WP_Error true при успехе, WP_Error при ошибке
 */
function p_my_sklad_import_single_product($ms_product)
{
	try {
		$ms_code = $ms_product['code'] ?? '';

		$name = $ms_product['name'] ?? 'Без названия';

		$externalCode = $ms_product['externalCode'] ?? '';

		$exrernalId = $ms_product['id'] ?? '';

		$token = get_option('p_my_sklad_access_token');

		// if (empty($ms_code)) {
		//   p_my_sklad_log()->debug('Пропущен товар без кода', ['name' => $name]);
		//   return true; // не ошибка — просто пропуск
		// }

		p_my_sklad_log()->debug("Импорт товара начат", [
			'ms_code' => $ms_code,
			'name' => $name,
			'product_id' => $ms_product['id'] ?? 'unknown',
		]);

		// === Фильтр категории ===
		$settings = get_option('p_my_sklad_settings_products', []);
		$filter_path = trim($settings['categories_filters'] ?? '');
		$product_path = $ms_product['pathName'] ?? '';

		if (!empty($filter_path)) {
			if (strpos($product_path, $filter_path) !== 0) {
				p_my_sklad_log()->debug('Товар не прошёл фильтр категории', [
					'filter_path' => $filter_path,
					'product_path' => $product_path,
					'action' => 'skipped'
				]);
				return true;
			} else {
				p_my_sklad_log()->debug('Товар прошёл фильтр категории', [
					'filter_path' => $filter_path,
					'product_path' => $product_path
				]);
			}
		}

		// Поиск товара в приоритетном порядке:

		// 1. Если не найден, поиск по SKU (externalCode)
		if (!empty($externalCode)) {
			$args = [
				'post_type' => 'product',
				'post_status' => ['publish', 'draft'],
				'meta_query' => [
					[
						'key' => '_sku',  // Предполагаю стандартное поле SKU в WooCommerce; замените на свой мета-ключ, если отличается
						'value' => $externalCode,
					]
				],
				'posts_per_page' => -1,  // Получаем все товары для обработки дубликатов
			];
			$query = new WP_Query($args);

			$products = $query->have_posts() ? $query->posts : [];
			$product_id = $query->have_posts() ? $query->posts[0]->ID : 0;
		}

		// 2. Сначала по $externalId (предполагаю, что это $exrernalId в вашем коде) для метаполя p_my_sklad_id
		$args = [
			'post_type' => 'product',
			'post_status' => ['publish', 'draft'],
			'meta_query' => [
				[
					'key' => 'p_my_sklad_id',
					'value' => $exrernalId,
				]
			],
			'posts_per_page' => -1,  // Получаем все товары для обработки дубликатов
		];

		$query = new WP_Query($args);

		$products = array_merge($products, $query->have_posts() ? $query->posts : []);  // Добавляем к общему массиву

		// 3. Если не найден, поиск по p_my_sklad_code
		if (!empty($ms_code)) {
			$args = [
				'post_type' => 'product',
				'post_status' => ['publish', 'draft'],
				'meta_query' => [
					[
						'key' => 'p_my_sklad_code',
						'value' => $ms_code,
					]
				],
				'posts_per_page' => -1,  // Получаем все товары для обработки дубликатов
			];
			$query = new WP_Query($args);
			$products = array_merge($products, $query->have_posts() ? $query->posts : []);  // Добавляем к общему массиву
		}

		// 4. Если не найден, поиск по названию (name)
		if (!empty($name)) {
			$args = [
				'post_type' => 'product',
				'post_status' => ['publish', 'draft'],
				'title' => $name,
				'posts_per_page' => -1,  // Получаем все товары для обработки дубликатов
			];
			$query = new WP_Query($args);
			$products = array_merge($products, $query->have_posts() ? $query->posts : []);  // Добавляем к общему массиву
		}

		// Удаление дубликатов по ID товара
		$unique_products = [];
		$seen_ids = [];
		foreach ($products as $product) {
			if (!in_array($product->ID, $seen_ids)) {
				$seen_ids[] = $product->ID;
				$unique_products[] = $product;
			}
		}


		$products = $unique_products;  // Теперь $products содержит уникальные товары
		// Если найдено более одного уникального товара, удаляем все дубликаты, оставляя только один для обработки
		if (count($products) > 1) {

			$keep_id = $product_id ?: $products[0]->ID;  // Оставляем первый уникальный товар (можно изменить на $product_id, если он отличается)

			$product_id = $keep_id;  // Устанавливаем ID товара для обработки

			foreach ($products as $product) {
				if ($product->ID != $keep_id) {
					// Используем wp_delete_post для удаления товара (WooCommerce обрабатывает связанные данные автоматически через хуки)
					wp_delete_post($product->ID);  // true для принудительного удаления, без отправки в корзину
				}
			}
		} elseif (count($products) == 1) {
			$product_id = $products[0]->ID;  // Если один товар, устанавливаем его для обработки
		} else {
			$product_id = 0;
		}

		// $product_id теперь содержит ID оставшегося товара (если он был найден), иначе 0

		// Определение действия
		$action = $product_id ? 'update' : 'create';

		p_my_sklad_log()->debug("Определён тип операции с товаром", [
			'action' => $action,
			'product_id' => $product_id,
			'ms_code' => $ms_code
		]);

		$packs = $ms_product['packs'] ?? [];

		if ($product_id) {
			$product = wc_get_product($product_id);
		} else {
			if (isset($packs) && is_array($packs) && count($packs) > 0) {
				$product = new WC_Product_Variable();
			} else {
				$product = new WC_Product_Simple();
			}
		}

		// === Единица измерения ===
		if (!empty($ms_product['uom']['meta']['href'])) {
			$uom_name = p_my_sklad_fetch_uom_name($ms_product['uom']['meta']['href'], $token);
			if (is_wp_error($uom_name)) {
				p_my_sklad_log()->error('Ошибка при получении единицы измерения', $uom_name);
			} elseif (!empty($uom_name)) {
				update_post_meta($product_id, '_oh_product_unit_name', $uom_name);
				p_my_sklad_log()->debug('Единица измерения установлена', [
					'uom' => $uom_name
				]);
			}
		}


		if (isset($packs) && is_array($packs) && count($packs) > 0) {
			// wp_remove_object_terms($product->get_id(), 'simple', 'product_type');
			// wp_set_object_terms($product->get_id(), 'variable', 'product_type', true);

			// Get the correct product classname from the new product type
			$product_classname = WC_Product_Factory::get_product_classname($product_id, 'variable');

			// Get the new product object from the correct classname
			$new_product = new $product_classname($product_id);

			// Save product to database and sync caches
			$new_product->save();

			$attributes = [];
			$options = [];

			foreach ($packs as $pack) {
				$options[] = $pack['quantity'];
			}


			$attribute = new WC_Product_Attribute();
			$attribute->set_name('Вес');
			$attribute->set_options($options);
			$attribute->set_visible(true);
			$attribute->set_variation(true);
			$attributes[] = $attribute;

			$product->set_attributes($attributes);
			$product->save();


			foreach ($options as $option) {
				$variation_1 = new WC_Product_Variation();
				$variation_1->set_parent_id($product->get_id());
				$variation_1->set_attributes(['Вес' => $option]);
				$variation_1->save();
			}
		}

		// === Основные поля ===
		$description = $ms_product['description'] ?? '';
		$quantity = isset($ms_product['quantity']) ? (float) $ms_product['quantity'] : 0;

		$action === 'create' && $product->set_name($name);
		// $product->set_description($description);
		// $product->set_short_description($description);

		$product->set_stock_quantity($quantity);
		$product->set_manage_stock(true);
		$product->set_stock_status($quantity > 0 ? 'instock' : 'outofstock');


		// Внешний код
		if (!empty($externalCode)) {
			$product->set_sku($externalCode);
		}

		p_my_sklad_log()->debug('Основные данные установлены', [
			'name' => $name,
			'description_length' => strlen($description),
			'stock_quantity' => $quantity,
			'stock_status' => $quantity > 0 ? 'instock' : 'outofstock'
		]);

		// // === Добавление описания в ACF repeater product_texts ===
		// if ($product_id) {
		//   $key_text_1 = "product_texts_0_text_1";
		//   $key_text_2 = "product_texts_0_text_2";
		//   $field_name = 'product_texts';

		//   // Пытаемся использовать ACF, если доступно
		//   if (function_exists('update_field')) {
		//     $existing_rows = get_field('product_texts', $product_id) ?: [];
		//     $existing_rows = [
		//       'text_1' => '',
		//       'text_2' => '',
		//     ];
		//     update_field('product_texts', $existing_rows, $product_id);
		//   } else {
		//     // Fallback: через update_post_meta
		//     update_post_meta($product_id, $key_text_1, '');
		//     update_post_meta($product_id, $key_text_2, '');
		//     update_post_meta($product_id, $field_name, 1);

		//     p_my_sklad_log()->debug('Описание добавлено в ACF repeater через update_post_meta', [
		//       'product_id' => $product_id,
		//       'row_index' => 1,
		//     ]);
		//   }
		// }

		// === Цены ===
		$regular_price = 0;
		$sale_price = 0;

		if (!empty($ms_product['salePrices'])) {
			foreach ($ms_product['salePrices'] as $sale_price_item) {
				if (!isset($sale_price_item['value']) || !isset($sale_price_item['priceType']['name'])) {
					continue;
				}

				$value = (float) $sale_price_item['value'] / 100;
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

		p_my_sklad_log()->debug('Цены установлены', [
			'regular_price' => $regular_price,
			'sale_price' => $sale_price
		]);

		$saved_product_id = $product->save();

		update_post_meta($saved_product_id, 'p_my_sklad_product_path', $product_path);
		update_post_meta($saved_product_id, 'p_my_sklad_id', $externalCode);

		if (!$saved_product_id) {
			throw new Exception('Не удалось сохранить товар в WooCommerce');
		}

		// Обновляем $product_id после первого сохранения (если создавали)
		$product_id = $saved_product_id;

		p_my_sklad_log()->debug('Товар сохранён в базе', [
			'wc_product_id' => $product_id,
			'action' => $action
		]);

		// === Категории ===
		static $cached_categories = null;
		static $cached_acf_categories = null;

		if ($cached_categories === null) {
			$all_categories = get_terms([
				'taxonomy' => 'product_cat',
				'hide_empty' => false,
				'parent' => 0
			]);

			if (is_wp_error($all_categories)) {
				throw new Exception('Ошибка загрузки категорий: ' . $all_categories->get_error_message());
			}

			$cached_categories = [];
			$cached_acf_categories = [];

			foreach ($all_categories as $term) {
				$term_id = $term->term_id;
				$full_name = $term->name;

				$cached_categories[$term_id] = $full_name;

				$allowed_subcats_raw = '';
				if (function_exists('get_field')) {
					$allowed_subcats_raw = get_field('p_my_sklad_categories', 'product_cat_' . $term_id);
				}

				if (empty($allowed_subcats_raw)) {
					$cached_acf_categories[$term_id] = ['raw' => [], 'normalized' => []];
				} else {
					$raw_list = array_map('trim', explode(';', $allowed_subcats_raw));
					$normalized_list = array_map('mb_strtolower', $raw_list);
					$cached_acf_categories[$term_id] = ['raw' => $raw_list, 'normalized' => $normalized_list];
				}
			}

			p_my_sklad_log()->debug('Кеш категорий инициализирован', [
				'total_categories' => count($cached_categories)
			]);
		}

		$matching_category_ids = [];

		if (!empty($product_path)) {
			$parts = explode('/', $product_path);
			$extracted_subcat_name = trim(end($parts));

			// Поиск по точному имени — ВСЕ совпадения
			foreach ($cached_categories as $id => $full_name) {

				if (trim($full_name) === $extracted_subcat_name) {
					$matching_category_ids[] = (int) $id;

					p_my_sklad_log()->debug('Найдена категория по совпадению имени', [
						'matched_category' => $full_name,
						'category_id' => $id,
						'source' => 'fallback_by_name'
					]);
				}
			}

			// Поиск через ACF — ВСЕ совпадения (избегаем дублей)
			foreach ($cached_acf_categories as $id => $data) {
				if (in_array($extracted_subcat_name, $data['raw'])) {
					$int_id = (int) $id;
					if (!in_array($int_id, $matching_category_ids)) {
						$matching_category_ids[] = $int_id;
						p_my_sklad_log()->debug('Найдена категория через ACF-фильтр', [
							'matched_category' => $cached_categories[$id],
							'category_id' => $id,
							'source' => 'acf_filter',
							'allowed_values' => $data['raw']
						]);
					}
				}
			}

			if (count($matching_category_ids) > 0) {
				// Добавляем ВСЕ найденные категории, не удаляя старые
				wp_set_object_terms($product_id, $matching_category_ids, 'product_cat');
				$product->set_status('publish');
				$product->save();
				p_my_sklad_log()->debug('Найдено и добавлено категорий: ' . count($matching_category_ids), [
					'category_ids' => $matching_category_ids,
					'category_names' => array_intersect_key($cached_categories, array_flip($matching_category_ids))
				]);
			} else {
				// Ни одна категория не найдена
				$product->set_status('draft');
				$product->save();
				p_my_sklad_log()->debug('Категория не найдена — товар переведён в черновик', [
					'missing_subcat' => $extracted_subcat_name,
					'product_path' => $product_path
				]);
			}
		} else {
			p_my_sklad_log()->debug('У товара нет pathName — пропуск категорий', ['ms_code' => $ms_code]);
		}

		// === Изображения ===
		$attachment_ids = [];

		// if ($token && !empty($ms_product['images']['meta']['href'])) {
		//   $image_rows = p_my_sklad_fetch_product_images($ms_product['meta']['href'], $token);

		//   if (is_wp_error($image_rows)) {
		//     p_my_sklad_log()->error('Ошибка при получении изображений товара', $image_rows);
		//   } else {
		//     foreach ($image_rows as $image) {
		//       if (!empty($image['meta']['downloadHref'])) {
		//         $download_url = trim($image['meta']['downloadHref']);
		//         $filename = $image['filename'] ?? 'image.jpg';
		//         $attachment_id = p_my_sklad_download_and_attach_image($download_url, $filename, $product_id, $token);

		//         if (is_wp_error($attachment_id)) {
		//           p_my_sklad_log()->error('Ошибка при загрузке изображения', $attachment_id);
		//         } elseif ($attachment_id) {
		//           $attachment_ids[] = $attachment_id;
		//           p_my_sklad_log()->debug('Изображение загружено и привязано', [
		//             'url' => $download_url,
		//             'attachment_id' => $attachment_id
		//           ]);
		//         }
		//       }
		//     }

		//     if (!empty($attachment_ids)) {
		//       set_post_thumbnail($product_id, $attachment_ids[0]);
		//       $product->set_gallery_image_ids(array_slice($attachment_ids, 1));
		//       $product->save(); // Сохраняем изменения галереи
		//       p_my_sklad_log()->debug('Галерея изображений установлена', [
		//         'featured_image' => $attachment_ids[0],
		//         'gallery_count' => count($attachment_ids) - 1
		//       ]);
		//     }
		//   }
		// }

		// === Мета-поля ===
		update_post_meta($product_id, 'p_my_sklad_code', $ms_code);
		update_post_meta($product_id, 'p_my_sklad_updated_at', current_time('mysql'));

		$product->save(); // Финальное сохранение

		p_my_sklad_log()->info('Товар успешно импортирован', [
			'action' => $action,
			'ms_code' => $ms_code,
			'wc_product_id' => $product_id,
			'name' => $name
		]);

		return true;
	} catch (Exception $e) {
		$error = new WP_Error('import_failed', $e->getMessage(), [
			'ms_code' => $ms_product['code'] ?? 'unknown',
			'name' => $ms_product['name'] ?? 'unknown',
			'trace' => $e->getTraceAsString(),
		]);

		p_my_sklad_log()->error('Критическая ошибка при импорте товара', $error);
		return $error;
	}
}

function p_my_sklad_verify_products()
{
	// Убедитесь, что это выполняется в контексте WordPress (например, в functions.php или через WP Admin)
	if (!defined('ABSPATH')) {
		exit; // Защита от прямого доступа
	}

	// Настройки
	$posts_per_page = 200; // Количество товаров за запрос
	$offset = 0; // Начальный оффсет

	// Цикл для загрузки товаров пагинированно
	while (true) {
		// Аргументы для WP_Query
		$args = array(
			'post_type' => 'product',          // Тип поста: товары WooCommerce
			'post_status' => ['publish', 'draft'],          // Только опубликованные товары (можно изменить на 'any' для всех)
			'posts_per_page' => $posts_per_page,    // 200 товаров за запрос
			'offset' => $offset,            // Оффсет для пагинации
			'orderby' => 'ID',               // Сортировка по ID (можно изменить)
			'order' => 'ASC',              // Возрастающий порядок
		);

		// Выполняем запрос
		$query = new WP_Query($args);

		// Если товаров больше нет, выходим из цикла
		if (!$query->have_posts()) {
			break;
		}

		// Обрабатываем загруженные товары
		while ($query->have_posts()) {
			$query->the_post();
			$product_id = get_the_ID();

			// Ваша логика обработки товара
			// Например, получаем данные и что-то делаем
			$product = wc_get_product($product_id);



			// Пример: выводим в лог или обрабатываем
			error_log("Обработан товар: ID $product_id, Название: $title, Цена: $price");

			// Здесь вставьте ваш код (например, экспорт, обновление, анализ)

			$product_path = get_post_meta($product_id, "p_my_sklad_product_path", true);

			if (!empty($product_path)) {
				if ($cached_categories === null) {
					$all_categories = get_terms([
						'taxonomy' => 'product_cat',
						'hide_empty' => false,
						'parent' => 0
					]);

					if (is_wp_error($all_categories)) {
						throw new Exception('Ошибка загрузки категорий: ' . $all_categories->get_error_message());
					}

					$cached_categories = [];
					$cached_acf_categories = [];

					foreach ($all_categories as $term) {
						$term_id = $term->term_id;
						$full_name = $term->name;

						$cached_categories[$term_id] = $full_name;

						$allowed_subcats_raw = '';
						if (function_exists('get_field')) {
							$allowed_subcats_raw = get_field('p_my_sklad_categories', 'product_cat_' . $term_id);
						}

						if (empty($allowed_subcats_raw)) {
							$cached_acf_categories[$term_id] = ['raw' => [], 'normalized' => []];
						} else {
							$raw_list = array_map('trim', explode(';', $allowed_subcats_raw));
							$normalized_list = array_map('mb_strtolower', $raw_list);
							$cached_acf_categories[$term_id] = ['raw' => $raw_list, 'normalized' => $normalized_list];
						}
					}

					p_my_sklad_log()->debug('Кеш категорий инициализирован', [
						'total_categories' => count($cached_categories)
					]);
				}

			} else {
				$product->set_status('draft');
			}
		}

		// Восстанавливаем глобальный пост
		wp_reset_postdata();

		// Увеличиваем оффсет для следующей итерации
		$offset += $posts_per_page;
	}

	// Завершение
	error_log("Обработка товаров завершена!");

}

/**
 * Получает список изображений товара из API МойСклад
 */
function p_my_sklad_fetch_product_images($product_href, $token)
{
	$images_url = $product_href . '/images';

	$response = wp_remote_get($images_url, [
		'headers' => [
			'Authorization' => "Bearer {$token}",
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
		'timeout' => 30,
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
		'name' => sanitize_file_name($filename),
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
			'Authorization' => "Bearer {$token}",
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

function p_my_sklad_log()
{
	return P_MySklad_WC_Logger::instance();
}

function p_my_sklad_create_variable_product()
{
	// Создаём новый переменный продукт
	$product = new WC_Product_Variable();
	$product->set_name('Футболка с вариациями'); // Название товара
	$product->set_description('Описание товара'); // Описание
	$product->set_regular_price(0); // Основная цена (для переменных товаров обычно 0)
	$product->set_category_ids([1]); // ID категорий (пример)
	$product->set_image_id(123); // ID основной картинки (пример)

	// Добавляем атрибуты (например, Цвет и Размер)
	$attributes = array();

	// Атрибут 1: Цвет
	$color_attribute = new WC_Product_Attribute();
	$color_attribute->set_name('Цвет');
	$color_attribute->set_options(['Красный', 'Синий', 'Зелёный']); // Варианты атрибута
	$color_attribute->set_visible(true); // Видимый на фронтенде
	$color_attribute->set_variation(true); // Для вариаций
	$attributes[] = $color_attribute;

	$product->set_attributes($attributes);

	// Сохраняем продукт
	$product_id = $product->save();

	// Теперь создаём вариации на основе комбинаций атрибутов
	$variation_1 = new WC_Product_Variation();
	$variation_1->set_parent_id($product_id);
	$variation_1->set_attributes(['Цвет' => 'Красный', 'Размер' => 'M']); // Комбинация атрибутов
	$variation_1->set_regular_price(150); // Цена вариации
	$variation_1->set_stock_quantity(10); // Количество на складе
	$variation_1->set_manage_stock(true);
	$variation_1->save();
}
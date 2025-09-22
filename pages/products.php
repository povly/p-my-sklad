<?php

/**
 * Регистрация страницы настроек в меню "Настройки".
 */
add_action('admin_menu', function () {
  // Добавляем дочернюю страницу
  add_submenu_page(
    P_MY_SKLAD_NAME, // slug родительской страницы
    __('Синхронизация продуктов', 'p-my-sklad'), // Заголовок страницы (в <title>)
    __('Синхронизация продуктов', 'p-my-sklad'), // Название пункта меню
    'manage_options',                               // Минимальные права доступа
    P_MY_SKLAD_NAME . '_product',                 // Уникальный slug дочерней страницы
    'p_my_sklad_render_subpage_product'              // Callback-функция для вывода контента
  );
});


/**
 * Обработка POST-запроса формы.
 */
add_action('admin_init', 'p_my_sklad_handle_product_form_submission');

function p_my_sklad_handle_product_form_submission()
{
  // Проверяем, что это наша форма
  if (!isset($_POST['p_my_sklad_save_product']) || !current_user_can('manage_options')) {
    return;
  }

  // Проверка nonce
  if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'p_my_sklad_token_nonce_product')) {
    add_settings_error(
      P_MY_SKLAD_NAME . '_product',
      'nonce_failed',
      __('Недопустимый запрос. Попробуйте снова.', 'p-my-sklad'),
      'error'
    );
    return;
  }
}

/**
 * Отображение страницы настроек.
 */
function p_my_sklad_render_subpage_product()
{
  if (!current_user_can('manage_options')) {
    wp_die(__('У вас нет доступа.', 'p-my-sklad'));
  }

  settings_errors(P_MY_SKLAD_NAME . '_product');
?>
  <div class="wrap">
    <h1><?php echo esc_html__('Синхронизация товаров', 'p-my-sklad'); ?></h1>

    <form id="p-my-sklad-products-sync" method="post" action="">
      <?php wp_nonce_field('p_my_sklad_token_nonce_product'); ?>
      <?php submit_button('Запустить синхронизацию', 'primary', 'start_sync'); ?>
    </form>

    <div class="sync-status-container" style="margin-top: 30px; padding: 20px; border: 1px solid #ccc; border-radius: 5px;">
      <h3>Статус синхронизации</h3>
      <div class="progress-wrapper" style="width: 100%; background: #eee; border-radius: 3px; margin: 10px 0;">
        <div class="progress-bar" style="height: 20px; background: #007cba; border-radius: 3px; width: 0%; transition: width 0.3s;"></div>
      </div>
      <div class="progress-text" style="font-weight: bold;">0%</div>
      <div class="status-message" style="color: #555;">Готово...</div>
      <div class="sync-log" style="margin-top: 15px; max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 14px; background: #f9f9f9; padding: 10px; border: 1px solid #ddd; border-radius: 3px; display: none;"></div>
    </div>
  </div>

  <script>
    jQuery(document).ready(function($) {
      const $form = $('#p-my-sklad-products-sync');
      const $submitButton = $form.find('button[type="submit"]');
      const $container = $('.sync-status-container');
      const $bar = $('.progress-bar');
      const $text = $('.progress-text');
      const $message = $('.status-message');
      const $log = $('.sync-log');

      // === ПРОВЕРКА ПРИ ЗАГРУЗКЕ СТРАНИЦЫ ===
      checkInitialSyncStatus();

      function checkInitialSyncStatus() {
        // Сначала получаем статус прогресса
        $.post(ajaxurl, {
          action: 'p_my_sklad_products_check_sync_status',
          nonce: '<?php echo wp_create_nonce("p_my_sklad_products_check_sync_nonce"); ?>'
        }, function(res) {
          if (res.success) {
            const data = res.data;

            // Показываем контейнер только если есть активность
            if (data.status === 'in_progress') {
              $container.show();
              $bar.css('width', data.progress + '%');
              $text.text(data.progress + '%');
              $message.text('Проверка активности фоновой задачи...');

              // 🔎 Проверяем, запланирована ли задача в cron
              $.post(ajaxurl, {
                action: 'p_my_sklad_check_cron_active',
                nonce: '<?php echo wp_create_nonce("p_my_sklad_products_check_sync_nonce"); ?>'
              }, function(cronRes) {
                if (cronRes.success && cronRes.data.is_scheduled) {
                  // ✅ Задача запланирована → продолжаем опрос
                  $message.text(data.message);
                  startPolling();
                } else {
                  // ❌ Задача НЕ запланирована → значит, остановлена или удалена
                  const errorMsg = 'Синхронизация была прервана или удалена из cron.';
                  $message.css('color', 'red').text(errorMsg);
                  $bar.css('background', '#f44336');
                  $submitButton.prop('disabled', false).val('Запустить снова');

                  // ⚠️ Опционально: автоматически сбросить option на сервере
                  $.post(ajaxurl, {
                    action: 'p_my_sklad_reset_broken_sync',
                    nonce: '<?php echo wp_create_nonce("p_my_sklad_products_check_sync_nonce"); ?>'
                  });
                }
              }).fail(function() {
                $message.css('color', 'orange').text('Не удалось проверить cron. Возможно, он был удалён.');
                $submitButton.prop('disabled', false).val('Перезапустить');
              });
            } else if (data.status === 'error') {
              $container.show();
              $bar.css('width', data.progress + '%');
              $text.text(data.progress + '%');
              $message.css('color', 'red').text(data.message);
              $bar.css('background', '#f44336');
              $submitButton.prop('disabled', false).val('Повторить');
            }
            // Если completed/idle — ничего не показываем
          }
        }).fail(function() {
          console.warn('Не удалось получить статус синхронизации.');
        });
      }

      // === ОПРОС СТАТУСА В ЦИКЛЕ ===
      function startPolling() {
        function poll() {
          $.post(ajaxurl, {
            action: 'p_my_sklad_products_check_sync_status',
            nonce: '<?php echo wp_create_nonce("p_my_sklad_products_check_sync_nonce"); ?>'
          }, function(res) {
            if (res.success) {
              const data = res.data;
              $bar.css('width', data.progress + '%');
              $text.text(data.progress + '%');
              $message.text(data.message);

              if (data.status === 'in_progress') {
                setTimeout(poll, 2000); // опрашиваем каждые 2 сек
              } else if (data.status === 'completed') {
                $message.css('color', '#4CAF50').text(data.message);
                $submitButton.prop('disabled', false).val('Запустить снова');
                $bar.css('background', '#4CAF50');
              } else if (data.status === 'error') {
                $message.css('color', 'red');
                $bar.css('background', '#f44336');
                $submitButton.prop('disabled', false).val('Попробовать снова');
              }
            }
          }).fail(function() {
            setTimeout(poll, 5000);
          });
        }
        poll(); // стартуем опрос
      }

      // === ОБРАБОТКА КНОПКИ "ЗАПУСТИТЬ" ===
      $form.on('submit', function(e) {
        e.preventDefault();

        $container.show();
        $bar.css('width', '0%').css('background', '#007cba');
        $text.text('0%');
        $message.text('Запуск...').css('color', '#555');
        $log.empty().hide();
        $submitButton.prop('disabled', true).val('Идёт синхронизация...');

        $.post(ajaxurl, {
          action: 'p_my_sklad_products_start_sync',
          nonce: '<?php echo wp_create_nonce("p_my_sklad_products_sync_nonce"); ?>'
        }, function(res) {
          if (res.success) {
            startPolling(); // начинаем опрашивать статус
          } else {
            showError(res.data.message || 'Ошибка запуска.');
          }
        }).fail(function() {
          showError('Ошибка сети при запуске.');
        });
      });

      function showError(msg) {
        $message.css('color', 'red').text('Ошибка: ' + msg);
        $bar.css('background', '#f44336');
        $submitButton.prop('disabled', false).val('Повторить');
      }
    });
  </script>
<?php
}


// === WP-CRON: Обработка одного батча ===
add_action('p_my_sklad_run_sync_batch', 'p_my_sklad_run_sync_batch');

function p_my_sklad_run_sync_batch()
{
  $progress = get_option('p_my_sklad_products_sync_progress', [
    'status'    => 'idle',
    'processed' => 0,
    'total'     => 0,
    'nextHref'  => null,
  ]);

  if ($progress['status'] !== 'in_progress') {
    return;
  }

  $token = get_option('p_my_sklad_access_token');
  if (!$token) {
    update_option('p_my_sklad_products_sync_progress', array_merge($progress, [
      'status'  => 'error',
      'message' => '❌ Токен не найден.'
    ]));
    return;
  }

  // ✅ Берём АКТУАЛЬНЫЙ лимит из настроек (не из прогресса!)
  $settings = get_option('p_my_sklad_settings_products', []);
  $batch_size = !empty($settings['products_limit']) ? (int) $settings['products_limit'] : 200;

  $url = $progress['nextHref'] ?: add_query_arg(
    ['limit' => $batch_size], // ← вот здесь используем актуальный batch_size
    'https://api.moysklad.ru/api/remap/1.2/entity/assortment'
  );

  $response = wp_remote_get($url, [
    'headers' => [
      'Authorization'   => "Bearer {$token}",
      'Accept-Encoding' => 'gzip',
    ],
    'timeout' => 30,
  ]);

  if (is_wp_error($response)) {
    update_option('p_my_sklad_products_sync_progress', array_merge($progress, [
      'status'  => 'error',
      'message' => '❌ Ошибка API: ' . $response->get_error_message()
    ]));
    return;
  }

  $body = wp_remote_retrieve_body($response);
  $data = json_decode($body, true);

  if (empty($data) || !isset($data['rows'])) {
    update_option('p_my_sklad_products_sync_progress', array_merge($progress, [
      'status'  => 'error',
      'message' => '❌ Неверный ответ от API.'
    ]));
    return;
  }

  // Установить общее количество при первом запросе
  if ($progress['total'] == 0 && isset($data['meta']['size'])) {
    $progress['total'] = (int) $data['meta']['size'];
  }

  foreach ($data['rows'] as $product) {
    // 🔽 Здесь вызывай свою функцию импорта
    // Пример: p_my_sklad_import_single_product($product);

    // Для теста — просто логируем
    error_log("Импортирован товар: " . ($product['name'] ?? 'N/A'));

    $progress['processed']++;
  }

  // Сохраняем прогресс (без batch_size!)
  $progress['message'] = "Обработано {$progress['processed']} из " . ($progress['total'] ?: '?') . "...";

  if (!empty($data['meta']['nextHref'])) {
    $progress['nextHref'] = $data['meta']['nextHref'];
    update_option('p_my_sklad_products_sync_progress', $progress);

    // 🔁 Запускаем следующий шаг
    if (!wp_next_scheduled('p_my_sklad_run_sync_batch')) {
      wp_schedule_single_event(time() + 1, 'p_my_sklad_run_sync_batch');
    }
  } else {
    // ✅ Завершение
    $progress['status'] = 'completed';
    $progress['message'] = "✅ Готово! Импортировано {$progress['processed']} товаров.";
    delete_option('p_my_sklad_products_sync_progress'); // или update_option с очисткой — как предпочитаешь
  }
}

/**
 * 3. Обработчик cron-события — запускает синхронизацию, если не запущена
 */
add_action('p_my_sklad_cron_sync_products', 'p_my_sklad_cron_sync_products_handler');

function p_my_sklad_cron_sync_products_handler()
{
  wp_clear_scheduled_hook('p_my_sklad_run_sync_batch');

  delete_option('p_my_sklad_products_sync_progress');

  // Инициализируем прогресс без batch_size
  $initial_state = [
    'status'    => 'in_progress',
    'processed' => 0,
    'total'     => 0,
    'nextHref'  => null, // ← ВАЖНО: начинаем с первой страницы!
    'message'   => 'Запущено cron-заданием (полная синхронизация)...',
  ];

  update_option('p_my_sklad_products_sync_progress', $initial_state);

  // Запускаем первый шаг синхронизации
  wp_schedule_single_event(time() + 1, 'p_my_sklad_run_sync_batch');

  error_log('MySklad: Запущена автоматическая синхронизация товаров по cron.');
}

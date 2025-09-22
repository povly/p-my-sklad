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
    wp_die(__('У вас нет доступа к этой странице.', 'p-my-sklad'));
  }

  $sync_progress = get_option('p_my_sklad_products_sync_progress', []);

  settings_errors(P_MY_SKLAD_NAME . '_product');
  // p_my_sklad_get_assortments();
?>
  <div class="wrap">

    <!-- <div class="spinner is-active"></div> -->

    <h1><?php echo esc_html__('Синхронизация товаров', 'p-my-sklad'); ?></h1>

    <form id="p-my-sklad-products-sync" method="post" action="<?php echo esc_url(admin_url('admin.php?page=' . P_MY_SKLAD_NAME . '_product')); ?>">
      <?php wp_nonce_field('p_my_sklad_token_nonce_product'); ?>

      <table class="form-table" role="presentation">

      </table>

      <?php submit_button(esc_html__('Синхронизация'), 'primary', 'p_my_sklad_save_product'); ?>
    </form>

    <div class="sync-status-container" style="margin-top: 30px; padding: 20px; border: 1px solid #ccc; border-radius: 5px; display: none;">
      <h3>Статус синхронизации</h3>
      <div class="progress-wrapper" style="width: 100%; background: #eee; border-radius: 3px; margin: 10px 0;">
        <div class="progress-bar" style="height: 20px; background: #007cba; border-radius: 3px; width: 0%; transition: width 0.3s ease;"></div>
      </div>
      <div class="progress-text" style="font-weight: bold; margin: 5px 0;">
        0%
      </div>
      <div class="status-message" style="color: #555;">
        Готово к запуску...
      </div>
      <div class="sync-log" style="margin-top: 15px; max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 14px; background: #f9f9f9; padding: 10px; border: 1px solid #ddd; border-radius: 3px;">
      </div>
    </div>

  </div>

  <script>
    jQuery(document).ready(function($) {
      const $form = $('#p-my-sklad-products-sync');
      const $submitButton = $form.find('button[type="submit"]');
      const $syncContainer = $('.sync-status-container');
      const $progressBar = $('.progress-bar');
      const $progressText = $('.progress-text');
      const $statusMessage = $('.status-message');
      const $syncLog = $('.sync-log');

      // Обработчик отправки формы
      $form.on('submit', function(e) {
        e.preventDefault();

        // Показываем контейнер прогресса
        $syncContainer.show();
        $progressBar.css('width', '0%');
        $progressText.text('0%');
        $statusMessage.text('Начинаем синхронизацию...');
        $syncLog.empty();

        // Отключаем кнопку
        $submitButton.prop('disabled', true).val('Синхронизация...');

        // Запускаем синхронизацию
        startSyncProcess();

        return false;
      });

      function startSyncProcess() {
        $.ajax({
          url: ajaxurl,
          type: 'POST',
          data: {
            action: 'p_my_sklad_products_start_sync',
            nonce: '<?php echo wp_create_nonce('p_my_sklad_products_sync_nonce'); ?>'
          },
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              updateUI(response.data);
              if (response.data.status !== 'completed') {
                // Запускаем опрос статуса
                checkSyncStatus();
              } else {
                finalizeSync();
              }
            } else {
              showError(response.data.message || 'Произошла ошибка.');
            }
          },
          error: function() {
            showError('Ошибка соединения с сервером.');
          }
        });
      }

      function checkSyncStatus() {
        $.ajax({
          url: ajaxurl,
          type: 'POST',
          data: {
            action: 'p_my_sklad_products_check_sync_status',
            nonce: '<?php echo wp_create_nonce('p_my_sklad_products_check_sync_nonce'); ?>'
          },
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              updateUI(response.data);
              if (response.data.status !== 'completed') {

                if (response.data.status === 'error') {
                  showError(response.data.message);
                } else {
                  // Продолжаем опрос каждые 2 секунды
                  setTimeout(checkSyncStatus, 2000);
                }

              } else {
                finalizeSync();
              }
            } else {
              showError(response.data.message || 'Произошла ошибка при проверке статуса.');
            }
          },
          error: function() {
            showError('Ошибка соединения с сервером при проверке статуса.');
          }
        });
      }

      function updateUI(data) {
        // Обновляем прогресс-бар
        if (data.progress !== undefined) {
          $progressBar.css('width', data.progress + '%');
          $progressText.text(data.progress + '%');
        }

        // Обновляем статусное сообщение
        if (data.message) {
          $statusMessage.text(data.message);
        }

        // Добавляем лог, если есть
        if (data.log && data.log.length > 0) {
          data.log.forEach(function(logEntry) {
            $syncLog.append($('<div>').text(logEntry).css('margin-bottom', '5px'));
          });
          // Прокручиваем лог вниз
          $syncLog.scrollTop($syncLog[0].scrollHeight);
        }
      }

      function finalizeSync() {
        $submitButton.prop('disabled', false).val('Начать новую синхронизацию');
        $statusMessage.text('Синхронизация завершена!');
        $progressBar.css('background', '#4CAF50'); // Зеленый цвет для завершения
      }

      function showError(message) {
        $statusMessage.text('Ошибка: ' + message).css('color', 'red');
        $submitButton.prop('disabled', false).val('Попробовать снова');
        $progressBar.css('background', '#f44336'); // Красный цвет для ошибки
      }
    });
  </script>
<?php
}

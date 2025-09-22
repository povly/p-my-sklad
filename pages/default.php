<?php

/**
 * Регистрация страницы настроек в меню "Настройки".
 */
add_action('admin_menu', function () {
  add_menu_page(
    __('МойСклад интеграция', 'p-my-sklad'),  // Заголовок в <title>
    __('МойСклад', 'p-my-sklad'),             // Текст пункта меню
    'manage_options',                         // Минимальные права доступа
    P_MY_SKLAD_NAME,                          // Уникальный slug (например: 'p_my_sklad')
    'p_my_sklad_render_settings_page',        // Callback-функция для главной страницы
    'dashicons-cart',                         // Иконка (подойдёт для магазина)
    58                                        // Позиция — между "Записи" и "Медиафайлы" (или где удобно)
  );
});

/**
 * Обработка POST-запроса формы.
 */
add_action('admin_init', 'p_my_sklad_handle_form_submission');

function p_my_sklad_handle_form_submission()
{
  // Проверяем, что это наша форма
  if (!isset($_POST['p_my_sklad_save']) || !current_user_can('manage_options')) {
    return;
  }

  // Проверка nonce
  if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'p_my_sklad_token_nonce')) {
    add_settings_error(
      P_MY_SKLAD_NAME,
      'nonce_failed',
      __('Недопустимый запрос. Попробуйте снова.', 'p-my-sklad'),
      'error'
    );
    return;
  }

  $login = sanitize_text_field($_POST['p_my_sklad_login'] ?? '');
  $pass  = $_POST['p_my_sklad_pass'] ?? '';

  if (empty($login) || empty($pass)) {
    add_settings_error(
      P_MY_SKLAD_NAME,
      'missing_fields',
      __('Логин и пароль обязательны.', 'p-my-sklad'),
      'error'
    );
    return;
  }

  $token = p_my_sklad_fetch_token($login, $pass);

  // Очищаем пароль из памяти
  $pass = null;

  if ($token) {
    update_option('p_my_sklad_access_token', $token, false); // не автозагружать
    add_settings_error(
      P_MY_SKLAD_NAME,
      'token_saved',
      __('Токен успешно сохранён.', 'p-my-sklad'),
      'updated'
    );
  } else {
    add_settings_error(
      P_MY_SKLAD_NAME,
      'token_failed',
      __('Не удалось получить токен. Проверьте логин и пароль.', 'p-my-sklad'),
      'error'
    );
  }
}

/**
 * Обработка POST-запроса формы.
 */
add_action('admin_init', 'p_my_sklad_handle_settings_form_submission');

function p_my_sklad_handle_settings_form_submission()
{
  // Проверяем, что это наша форма
  if (!isset($_POST['p_my_sklad_save_settings']) || !current_user_can('manage_options')) {
    return;
  }

  // Проверка nonce
  if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'p_my_sklad_token_nonce_settings')) {
    add_settings_error(
      P_MY_SKLAD_NAME,
      'nonce_failed',
      __('Недопустимый запрос. Попробуйте снова.', 'p-my-sklad'),
      'error'
    );
    return;
  }

  $settings = $_POST['p_my_sklad_settings_products'] ?? [];

  // Санитизация данных — ОБЯЗАТЕЛЬНО!
  $sanitized_settings = [
    'categories_filters' => sanitize_text_field($settings['categories_filters'] ?? ''),
    'products_limit'      => sanitize_text_field($settings['products_limit'] ?? ''),
    'product_interval'      => sanitize_text_field($settings['product_interval'] ?? ''),
  ];

  update_option('p_my_sklad_settings_products', $sanitized_settings);

  // === УПРАВЛЕНИЕ CRON ===
  $interval = $sanitized_settings['product_interval'];

  // Сначала удаляем все запланированные задачи
  wp_clear_scheduled_hook('p_my_sklad_cron_sync_products');

  // Если интервал выбран — планируем новую задачу
  if (!empty($interval)) {
    // Проверяем, существует ли такой интервал
    if (wp_get_schedule('p_my_sklad_cron_sync_products') !== $interval) {
      wp_schedule_event(time(), $interval, 'p_my_sklad_cron_sync_products');
    }
  }

  // Уведомление об успешном сохранении
  add_settings_error(
    P_MY_SKLAD_NAME,
    'settings_updated',
    __('Настройки синхронизации товаров успешно сохранены.', 'p-my-sklad'),
    'updated'
  );
}

/**
 * Отображение страницы настроек.
 */
function p_my_sklad_render_settings_page()
{
  if (!current_user_can('manage_options')) {
    wp_die(__('У вас нет доступа к этой странице.', 'p-my-sklad'));
  }

  $settings = get_option('p_my_sklad_settings_products', []);
  $login_value  = isset($_POST['p_my_sklad_login']) ? sanitize_text_field($_POST['p_my_sklad_login']) : '';
  settings_errors(P_MY_SKLAD_NAME);
?>
  <div class="wrap">
    <h1><?php echo esc_html__('Настройки МойСклад', 'p-my-sklad'); ?></h1>

    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=' . P_MY_SKLAD_NAME)); ?>">
      <?php wp_nonce_field('p_my_sklad_token_nonce'); ?>

      <table class="form-table" role="presentation">
        <tr>
          <th scope="row">
            <label for="p_my_sklad_login"><?php echo esc_html__('Логин', 'p-my-sklad'); ?></label>
          </th>
          <td>
            <input type="text" name="p_my_sklad_login" id="p_my_sklad_login"
              value="<?php echo esc_attr($login_value); ?>" class="regular-text" required>
          </td>
        </tr>
        <tr>
          <th scope="row">
            <label for="p_my_sklad_pass"><?php echo esc_html__('Пароль', 'p-my-sklad'); ?></label>
          </th>
          <td>
            <input type="password" name="p_my_sklad_pass" id="p_my_sklad_pass"
              class="regular-text" autocomplete="current-password" required>
          </td>
        </tr>
      </table>

      <?php submit_button(esc_html__('Сохранить'), 'primary', 'p_my_sklad_save'); ?>
    </form>

    <h2><?php echo esc_html__('Настройки синхронизации товаров', 'p-my-sklad'); ?></h2>

    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=' . P_MY_SKLAD_NAME)); ?>">
      <?php wp_nonce_field('p_my_sklad_token_nonce_settings'); ?>

      <table class="form-table" role="presentation">
        <tr>
          <th scope="row">
            <label for="p_my_sklad_categories_filters"><?php echo esc_html__('Фильтр товаров по категории', 'p-my-sklad'); ?></label>
          </th>
          <td>
            <input type="text" name="p_my_sklad_settings_products[categories_filters]" id="p_my_sklad_categories_filters"
              value="<?php echo esc_attr($settings['categories_filters'] ?? ''); ?>" class="regular-text">
          </td>
        </tr>

        <tr>
          <th scope="row">
            <label for="p_my_sklad_products_limit"><?php echo esc_html__('Количество товаров', 'p-my-sklad'); ?></label>
          </th>
          <td>
            <input type="text" name="p_my_sklad_settings_products[products_limit]" id="p_my_sklad_products_limit"
              value="<?php echo esc_attr($settings['products_limit'] ?? ''); ?>" class="regular-text">
          </td>
        </tr>

        <tr>
          <th scope="row"><?php _e('Интервал полной синхронизации', 'p-my-sklad'); ?></th>
          <td>
            <select name="p_my_sklad_settings_products[product_interval]">
              <option value=""><?php _e('— Не запускать автоматически —', 'p-my-sklad'); ?></option>
              <option value="hourly" <?php isset($settings) ? selected($settings['product_interval'], 'hourly') : ''; ?>>
                <?php _e('Каждый час', 'p-my-sklad'); ?>
              </option>
              <option value="every_six_hours" <?php isset($settings) ? selected($settings['product_interval'], 'every_six_hours') : ''; ?>>
                <?php _e('Каждые 6 часов', 'p-my-sklad'); ?>
              </option>
              <option value="daily" <?php isset($settings) ? selected($settings['product_interval'], 'daily') : ''; ?>>
                <?php _e('Раз в день', 'p-my-sklad'); ?>
              </option>
              <option value="weekly" <?php isset($settings) ? selected($settings['product_interval'], 'weekly') : ''; ?>>
                <?php _e('Раз в неделю', 'p-my-sklad'); ?>
              </option>
            </select>

          </td>
        </tr>
      </table>

      <?php submit_button(esc_html__('Сохранить'), 'primary', 'p_my_sklad_save_settings'); ?>
    </form>

  </div>
<?php
}

/**
 * Получение токена доступа от API МойСклад.
 *
 * @param string $login
 * @param string $password
 * @return false|string Access token on success, false on failure
 */
function p_my_sklad_fetch_token(string $login, string $password): bool|string
{
  $credentials = base64_encode("$login:$password");

  $response = wp_remote_post(
    'https://api.moysklad.ru/api/remap/1.2/security/token',
    [
      'headers' => [
        'Authorization'   => "Basic $credentials",
        'Accept-Encoding' => 'gzip',
      ],
      'timeout' => 30,
    ]
  );

  $code = wp_remote_retrieve_response_code($response);

  if ($code !== 201) {
    add_settings_error(
      P_MY_SKLAD_NAME,
      'nonce_failed',
      __('MySklad Token HTTP Error: ' . $code . ' - ' . wp_remote_retrieve_body($response), 'p-my-sklad'),
      'error'
    );
    return false;
  }

  $body = wp_remote_retrieve_body($response);
  $data = json_decode($body, true);

  if (isset($data['access_token']) && is_string($data['access_token'])) {
    return $data['access_token'];
  }

  add_settings_error(
    P_MY_SKLAD_NAME,
    'nonce_failed',
    __('MySklad Token Response Invalid: ' . $body, 'p-my-sklad'),
    'error'
  );
  return false;
}

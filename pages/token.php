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
 * Отображение страницы настроек.
 */
function p_my_sklad_render_settings_page()
{
  if (!current_user_can('manage_options')) {
    wp_die(__('У вас нет доступа к этой странице.', 'p-my-sklad'));
  }

  $stored_token = get_option('p_my_sklad_access_token', '');
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

    <!-- <h2 class="title"><?php echo esc_html__('Текущий токен', 'p-my-sklad'); ?></h2>
    <?php if ($stored_token): ?>
      <pre style="background: #f5f5f5; padding: 1em; border-radius: 4px; overflow-wrap: break-word;"><?php echo esc_html($stored_token); ?></pre>
    <?php else: ?>
      <p class="description"><?php echo esc_html__('Токен ещё не сохранён.', 'p-my-sklad'); ?></p>
    <?php endif; ?>
  </div> -->
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
    error_log('MySklad Token HTTP Error: ' . $code . ' - ' . wp_remote_retrieve_body($response));
    return false;
  }

  $body = wp_remote_retrieve_body($response);
  $data = json_decode($body, true);

  if (isset($data['access_token']) && is_string($data['access_token'])) {
    return $data['access_token'];
  }

  error_log('MySklad Token Response Invalid: ' . $body);
  return false;
}

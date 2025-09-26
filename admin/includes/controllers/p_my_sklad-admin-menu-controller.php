<?php

/**
 * @package    P_My_Sklad
 * @subpackage P_My_Sklad/admin_menu_controller
 * @author     Porshnyov Anatoly <povly19995@gmail.com>
 */
class P_My_Sklad_Admin_Menu_Controller
{
  private function render_template($template_name, $data = [])
  {
    $template_path = P_MY_SKLAD_DIR . "/admin/templates/{$template_name}";

    if (!file_exists($template_path)) {
      wp_die(sprintf(__('Шаблон не найден: %s', 'p-my-sklad'), esc_html($template_name)));
    }

    // Изолируем переменные
    extract($data);

    // Подключаем шаблон (он имеет доступ к $data через extract)
    include $template_path;
  }

  public function render_page_main()
  {
    $this->render_template('menu/settings-page.php', [
      'settings' => get_option('p_my_sklad_settings_products', []),
      'login_value' => isset($_POST['p_my_sklad_login']) ? sanitize_text_field($_POST['p_my_sklad_login']) : '',
      'slug' => 'p_my_sklad',
      'menu_slug' => 'p-my-sklad',
    ]);
  }

  public function handle_page_main()
  {
    // Проверяем, что это наша форма
    if (!isset($_POST['p_my_sklad_save']) || !current_user_can('manage_options')) {
      return;
    }

    // Проверка nonce
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'p_my_sklad_token_nonce')) {
      add_settings_error(
        'p_my_sklad',
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
        'p_my_sklad',
        'missing_fields',
        __('Логин и пароль обязательны.', 'p-my-sklad'),
        'error'
      );
      return;
    }

    $token = $this->p_my_sklad_fetch_token($login, $pass);

    // Очищаем пароль из памяти
    $pass = null;

    if ($token) {
      update_option('p_my_sklad_access_token', $token, false); // не автозагружать
      add_settings_error(
        'p_my_sklad',
        'token_saved',
        __('Токен успешно сохранён.', 'p-my-sklad'),
        'updated'
      );
    } else {
      add_settings_error(
        'p_my_sklad',
        'token_failed',
        __('Не удалось получить токен. Проверьте логин и пароль.', 'p-my-sklad'),
        'error'
      );
    }
  }

  private function p_my_sklad_fetch_token(string $login, string $password)
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
        'p_my_sklad',
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
      'p_my_sklad',
      'nonce_failed',
      __('MySklad Token Response Invalid: ' . $body, 'p-my-sklad'),
      'error'
    );
    return false;
  }
}

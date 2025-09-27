<?php

/**
 * @package    P_My_Sklad
 */

namespace P_My_Sklad\Admin\Controllers;

use PMySklad\Admin\Api\WC_Logger;

class Menu_Controller extends Base_Controller
{

  public function show_settings_page()
  {
    $this->render_template('page/settings-main.php', [
      'settings' => get_option('p_my_sklad_settings_products', []),
      'auth' => get_option('p_my_sklad_auth', []),
      'slug' => 'p_my_sklad',
      'menu_slug' => 'p-my-sklad',
    ]);
  }


  public function save_token_and_auth()
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

    $auth = $_POST['p_my_sklad_auth'] ?? '';

    $login = sanitize_text_field($auth['login'] ?? '');
    $pass  = $auth['pass'] ?? '';

    if (empty($login) || empty($pass)) {
      add_settings_error(
        'p_my_sklad',
        'missing_fields',
        __('Логин и пароль обязательны.', 'p-my-sklad'),
        'error'
      );
      return;
    }

    


    $token = $this->get_token($login, $pass);

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

      update_option('p_my_sklad_auth', $auth, false);

    } else {
      add_settings_error(
        'p_my_sklad',
        'token_failed',
        __('Не удалось получить токен. Проверьте логин и пароль.', 'p-my-sklad'),
        'error'
      );
    }
  }

  public function save_product_settings(){
    $logger = WC_Logger::getInstance();

    // Проверяем, что это наша форма
    if (!isset($_POST['p_my_sklad_save_settings']) || !current_user_can('manage_options')) {
      return;
    }

    $logger->info('Начата обработка формы настроек MySklad');

    // Проверка nonce
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'p_my_sklad_token_nonce_settings')) {
      add_settings_error(
        'p_my_sklad',
        'nonce_failed',
        __('Недопустимый запрос. Попробуйте снова.', 'p-my-sklad'),
        'error'
      );
      $logger->error('Проверка nonce не пройдена при сохранении настроек');
      return;
    }

    $settings = $_POST['p_my_sklad_settings_products'] ?? [];

    // Санитизация данных — ОБЯЗАТЕЛЬНО!
    $sanitized_settings = [
      'categories_filters' => sanitize_text_field($settings['categories_filters'] ?? ''),
      'products_limit'     => sanitize_text_field($settings['products_limit'] ?? ''),
      'product_interval'   => sanitize_text_field($settings['product_interval'] ?? ''),
    ];

    update_option('p_my_sklad_settings_products', $sanitized_settings);

    $logger->info('Настройки синхронизации товаров сохранены', [
      'categories_filter' => $sanitized_settings['categories_filters'],
      'products_limit'    => $sanitized_settings['products_limit'],
      'sync_interval'     => $sanitized_settings['product_interval']
    ]);

    // === УПРАВЛЕНИЕ CRON ===
    $interval = $sanitized_settings['product_interval'];

    // wp_clear_scheduled_hook('p_my_sklad_cron_sync_products');
    // $logger->info('Очищены запланированные события p_my_sklad_cron_sync_products');

    // if (!empty($interval)) {
    //   if (wp_get_schedule('p_my_sklad_cron_sync_products') !== $interval) {
    //     wp_schedule_event(time(), $interval, 'p_my_sklad_cron_sync_products');
    //     $logger->info("Запланирован cron-запуск синхронизации", ['interval' => $interval]);
    //   }
    // } else {
    //   $logger->info('Интервал синхронизации не задан — cron не планируется');
    // }

    // Уведомление об успешном сохранении
    add_settings_error(
      'p_my_sklad',
      'settings_updated',
      __('Настройки синхронизации товаров успешно сохранены.', 'p-my-sklad'),
      'updated'
    );

    $logger->info('Форма настроек успешно обработана и сохранена');
  }

  private function get_token(string $login, string $password)
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

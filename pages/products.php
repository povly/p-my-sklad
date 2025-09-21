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

  settings_errors(P_MY_SKLAD_NAME . '_product');
?>
  <div class="wrap">
    <!-- <div class="spinner"></div> -->
    <h1><?php echo esc_html__('Настройки cинхронизации товаров', 'p-my-sklad'); ?></h1>

    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=' . P_MY_SKLAD_NAME . '_product')); ?>">
      <?php wp_nonce_field('p_my_sklad_token_nonce_product'); ?>

      <table class="form-table" role="presentation">

      </table>

      <?php submit_button(esc_html__('Сохранить'), 'primary', 'p_my_sklad_save_product'); ?>
    </form>

  </div>
  <?php
}

// /**
//  * Получение токена доступа от API МойСклад.
//  *
//  * @param string $login
//  * @param string $password
//  * @return false|string Access token on success, false on failure
//  */
// function p_my_sklad_fetch_token(string $login, string $password): bool|string
// {
//   $credentials = base64_encode("$login:$password");

//   $response = wp_remote_post(
//     'https://api.moysklad.ru/api/remap/1.2/security/token',
//     [
//       'headers' => [
//         'Authorization'   => "Basic $credentials",
//         'Accept-Encoding' => 'gzip',
//       ],
//       'timeout' => 30,
//     ]
//   );

//   $code = wp_remote_retrieve_response_code($response);

//   if ($code !== 201) {
//     error_log('MySklad Token HTTP Error: ' . $code . ' - ' . wp_remote_retrieve_body($response));
//     return false;
//   }

//   $body = wp_remote_retrieve_body($response);
//   $data = json_decode($body, true);

//   if (isset($data['access_token']) && is_string($data['access_token'])) {
//     return $data['access_token'];
//   }

//   error_log('MySklad Token Response Invalid: ' . $body);
//   return false;
// }

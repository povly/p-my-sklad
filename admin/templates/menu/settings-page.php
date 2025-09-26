<?php

if (!current_user_can('manage_options')) {
  wp_die(__('У вас нет доступа к этой странице.', 'p-my-sklad'));
}
settings_errors($slug);
?>
<div class="wrap">
  <h1><?php echo esc_html__('Настройки МойСклад', 'p-my-sklad'); ?></h1>

  <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=' . $menu_slug)); ?>">
    <?php wp_nonce_field('p_my_sklad_token_nonce'); ?>

    <table class="form-table" role="presentation">
      <tr>
        <th scope="row">
          <label for="p_my_sklad_login"><?php echo esc_html__('Логин', 'p-my-sklad'); ?></label>
        </th>
        <td>
          <input type="text" name="p_my_sklad_auth[login]" id="p_my_sklad_login"
            value="<?php echo isset($auth['login']) ? esc_attr($auth['login']) : ''; ?>" class="regular-text" required>
        </td>
      </tr>
      <tr>
        <th scope="row">
          <label for="p_my_sklad_pass"><?php echo esc_html__('Пароль', 'p-my-sklad'); ?></label>
        </th>
        <td>
          <input type="password" name="p_my_sklad_auth[pass]" id="p_my_sklad_pass"
            class="regular-text" autocomplete="current-password" value="" required>
        </td>
      </tr>
    </table>

    <?php submit_button(esc_html__('Сохранить'), 'primary', 'p_my_sklad_save'); ?>
  </form>

  <h2><?php echo esc_html__('Настройки синхронизации товаров', 'p-my-sklad'); ?></h2>

  <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=' . $menu_slug)); ?>">
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
            <option value="hourly" <?php isset($settings) && isset($settings['product_interval']) ? selected($settings['product_interval'], 'hourly') : ''; ?>>
              <?php _e('Каждый час', 'p-my-sklad'); ?>
            </option>
            <option value="every_six_hours" <?php isset($settings) && isset($settings['product_interval']) ? selected($settings['product_interval'], 'every_six_hours') : ''; ?>>
              <?php _e('Каждые 6 часов', 'p-my-sklad'); ?>
            </option>
            <option value="daily" <?php isset($settings) && isset($settings['product_interval']) ? selected($settings['product_interval'], 'daily') : ''; ?>>
              <?php _e('Раз в день', 'p-my-sklad'); ?>
            </option>
            <option value="weekly" <?php isset($settings) && isset($settings['product_interval']) ? selected($settings['product_interval'], 'weekly') : ''; ?>>
              <?php _e('Раз в неделю', 'p-my-sklad'); ?>
            </option>
          </select>

        </td>
      </tr>
    </table>

    <?php submit_button(esc_html__('Сохранить'), 'primary', 'p_my_sklad_save_settings'); ?>
  </form>

</div>

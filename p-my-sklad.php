<?php

/**
 * Plugin Name: МойСклад интеграция
 * Description: Синхронизация магазина с МойСклад
 * Version: 0.4.1
 * Author: Anatoly Porshnyov
 * Text Domain: p-my-sklad
 *  */

defined('ABSPATH') || exit;

// Константы плагина
define('P_MY_SKLAD_VERSION', '0.4.1');
define('P_MY_SKLAD_NAME', 'p_my_sklad');
define('P_MY_SKLAD_SLUG', plugin_basename(__FILE__));
define('P_MY_SKLAD_PATH', plugin_dir_path(__FILE__));

// Подключаем фильтры
require_once P_MY_SKLAD_PATH . 'inc/filters.php';
require_once P_MY_SKLAD_PATH . 'inc/functions.php';

// Классы
require_once P_MY_SKLAD_PATH . 'class/Logger.php';

// ajax
require_once P_MY_SKLAD_PATH . 'ajax/product_start_sync.php';
require_once P_MY_SKLAD_PATH . 'ajax/product_check_sync.php';
require_once P_MY_SKLAD_PATH . 'ajax/product_check_cron_active.php';

// Страницы
require_once P_MY_SKLAD_PATH . 'pages/default.php';
require_once P_MY_SKLAD_PATH . 'pages/products.php';


<?php

class P_MySklad_WC_Logger
{
  private static $instance = null;
  private $logger;
  private $context;

  public static function instance()
  {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  private function __construct()
  {
    $this->logger = wc_get_logger();
    $this->context = ['source' => 'p-my-sklad'];
  }

  /**
   * Основной метод логирования
   *
   * @param string $level   Уровень: 'info', 'error', 'debug'
   * @param string $message Основное сообщение
   * @param mixed  $context Дополнительные данные (массив, объект, строка и т.д.)
   */
  public function log($level, $message, $context = [])
  {
    // Приводим $context к массиву
    if (!is_array($context)) {
      if ($context instanceof WP_Error) {
        // Распаковываем WP_Error
        $context = [
          'wp_error_code'    => $context->get_error_code(),
          'wp_error_message' => $context->get_error_message(),
          'wp_error_data'    => $this->formatForLog($context->get_error_data()),
          'wp_error_object'  => $this->formatForLog($context),
        ];
      } else {
        // Любое другое значение оборачиваем в ['data' => ...]
        $context = ['data' => $this->formatForLog($context)];
      }
    } else {
      // Если массив — рекурсивно форматируем значения
      $context = array_map([$this, 'formatForLog'], $context);
    }

    // Объединяем с базовым контекстом и логируем
    $final_context = array_merge($this->context, $context);
    $this->logger->log($level, $message, $final_context);
  }

  /**
   * Форматирует любые данные для логирования (объекты → строки, массивы → JSON и т.д.)
   */
  private function formatForLog($data)
  {
    if (is_array($data)) {
      return array_map([$this, 'formatForLog'], $data);
    }

    if (is_object($data)) {
      // Пробуем JSON
      $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
      if (json_last_error() === JSON_ERROR_NONE) {
        return $json;
      }
      // Если не получилось — print_r
      return print_r($data, true);
    }

    // Скалярные типы — возвращаем как есть
    return $data;
  }

  // --- Публичные методы ---

  public function info($message, $context = [])
  {
    $this->log('info', $message, $context);
  }

  public function error($message, $context = [])
  {
    $this->log('error', $message, $context);
  }

  public function debug($message, $context = [])
  {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      $this->log('debug', $message, $context);
    }
  }
}

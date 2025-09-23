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
   * Универсальный метод логирования
   *
   * @param string $level   Уровень лога: info, error, debug и т.д.
   * @param string $message Основное сообщение
   * @param mixed  $context Дополнительные данные: массив, объект, WP_Error и т.д.
   */
  public function log($level, $message, $context = [])
  {
    // Если передан WP_Error — преобразуем в понятный массив
    if ($context instanceof WP_Error) {
      $context = [
        'wp_error_code'    => $context->get_error_code(),
        'wp_error_message' => $context->get_error_message(),
        'wp_error_data'    => $this->sanitizeLogData($context->get_error_data()),
        'wp_error_all'     => $this->sanitizeLogData($context),
      ];
    } else {
      // Обычный контекст — просто санируем
      $context = $this->sanitizeLogData($context);
    }

    // Передаем в WC логгер
    $this->logger->log($level, $message, array_merge($this->context, $context));
  }

  /**
   * Санирует данные для логирования: превращает объекты и массивы в строки
   *
   * @param mixed $data
   * @return mixed
   */
  private function sanitizeLogData($data)
  {
    if (is_array($data) || is_object($data)) {
      // Пытаемся использовать json_encode для читаемости
      $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
      if (json_last_error() === JSON_ERROR_NONE) {
        return $json;
      }
      // Если JSON не получился — fallback на print_r
      return print_r($data, true);
    }

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

<?php

namespace PMySklad\Admin\Api;

/**
 * Class WC_Logger
 *
 * A singleton logger class that integrates with WooCommerce's logging system (`wc_get_logger()`).
 * Provides structured logging with context and supports different log levels:
 * - info
 * - error
 * - debug (only when WP_DEBUG is enabled)
 *
 * Automatically formats complex data (arrays, objects, WP_Error) for readable logs.
 * All entries are tagged with a source context `'p-my-sklad'` for easy filtering.
 *
 * Usage:
 * ```
 * WC_Logger::getInstance()->info('Order synced', ['order_id' => 123]);
 * WC_Logger::getInstance()->error('Sync failed', $wp_error);
 * WC_Logger::getInstance()->debug('Debug data', $some_variable);
 * ```
 *
 * @since      1.0.0
 * @package    PMySklad
 * @subpackage PMySklad\Admin\Api
 * @link       https://woocommerce.github.io/code-reference/classes/WC-Logger.html
 */
class WC_Logger
{
  /**
   * The singleton instance.
   *
   * @var self|null
   */
  private static $instance = null;

  /**
   * WooCommerce logger instance.
   *
   * @var \WC_Logger_Interface
   */
  private $logger;

  /**
   * Default context added to every log entry.
   *
   * @var array
   */
  private $context;

  /**
   * Returns the singleton instance of this class.
   *
   * @since  1.0.0
   * @return self
   */
  public static function getInstance()
  {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Private constructor to prevent external instantiation.
   *
   * Initializes the WooCommerce logger and sets default context.
   *
   * @since  1.0.0
   */
  private function __construct()
  {
    $this->logger  = wc_get_logger();
    $this->context = ['source' => 'p-my-sklad'];
  }

  /**
   * Prevent cloning of the instance.
   *
   * @return void
   */
  private function __clone() {}

  /**
   * Prevent unserializing of the instance.
   *
   * @return void
   */
  public function __wakeup()
  {
    throw new \Exception('Cannot unserialize singleton');
  }

  /**
   * Logs a message with the specified level and optional context.
   *
   * Supports automatic formatting of arrays, objects, and WP_Error instances.
   * Merges provided context with default context (e.g., source).
   *
   * @since  1.0.0
   * @param  string $level   Log level: 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'
   * @param  string $message Human-readable message to log
   * @param  mixed  $context Optional. Additional data (array, object, string, WP_Error, etc.)
   * @return void
   */
  public function log($level, $message, $context = [])
  {
    // Normalize context into an array
    if (!is_array($context)) {
      if ($context instanceof \WP_Error) {
        $context = [
          'wp_error_code'    => $context->get_error_code(),
          'wp_error_message' => $context->get_error_message(),
          'wp_error_data'    => $this->formatForLog($context->get_error_data()),
          'wp_error_object'  => $this->formatForLog($context),
        ];
      } else {
        $context = ['data' => $this->formatForLog($context)];
      }
    } else {
      $context = array_map([$this, 'formatForLog'], $context);
    }

    // Merge with default context and log
    $finalContext = array_merge($this->context, $context);
    $this->logger->log($level, $message, $finalContext);
  }

  /**
   * Formats any value (including arrays and objects) into a log-safe format.
   *
   * Recursively processes arrays.
   * Converts objects to JSON if possible, otherwise uses print_r.
   *
   * @since  1.0.0
   * @param  mixed $data The data to format
   * @return mixed|string Formatted data (strings, JSON strings, or processed arrays)
   */
  private function formatForLog($data)
  {
    if (is_array($data)) {
      return array_map([$this, 'formatForLog'], $data);
    }

    if (is_object($data)) {
      $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
      if (json_last_error() === JSON_ERROR_NONE) {
        return $json;
      }
      return print_r($data, true);
    }

    return $data;
  }

  /**
   * Logs an informational message.
   *
   * @since  1.0.0
   * @param  string $message Message to log
   * @param  mixed  $context Optional context data
   * @return void
   */
  public function info($message, $context = [])
  {
    $this->log('info', $message, $context);
  }

  /**
   * Logs an error message.
   *
   * @since  1.0.0
   * @param  string $message Error message
   * @param  mixed  $context Optional additional data (e.g., WP_Error, response body)
   * @return void
   */
  public function error($message, $context = [])
  {
    $this->log('error', $message, $context);
  }

  /**
   * Logs a debug message (only if WP_DEBUG is enabled).
   *
   * @since  1.0.0
   * @param  string $message Debug message
   * @param  mixed  $context Optional debug data
   * @return void
   */
  public function debug($message, $context = [])
  {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      $this->log('debug', $message, $context);
    }
  }
}

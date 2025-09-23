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

  public function log($level, $message)
  {
    $this->logger->{$level}($message, $this->context);
  }

  public function info($message)
  {
    $this->log('info', $message);
  }

  public function error($message)
  {
    $this->log('error', $message);
  }

  public function debug($message)
  {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      $this->log('debug', $message);
    }
  }
}

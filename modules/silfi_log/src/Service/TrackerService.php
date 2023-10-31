<?php

namespace Drupal\silfi_log\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\silfi_log\VisitorsTrackerInterface;
use Drupal\silfi_log\Logger\SilfiFileLog;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tracker for web analytics.
 */
class TrackerService implements VisitorsTrackerInterface {

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The date service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

   /**
   * The logger handler.
   *
   * @var \Drupal\silfi_log\Logger\SilfiFileLog
   */
  protected $logger;

  /**
   * Tracks visits and actions.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Component\Datetime\TimeInterface $time_service
   *   The date service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(
    RequestStack $request_stack,
    TimeInterface $time_service,
    ModuleHandlerInterface $module_handler,
    SilfiFileLog $logger
  ) {

    $this->request = $request_stack->getCurrentRequest();
    $this->time = $time_service;
    $this->moduleHandler = $module_handler;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function log(array $agent): void {
    $this->write($agent);
  }

  /**
   * Writes the events to the database.
   *
   * @param string[] $agent
   *   The agent array.
   */
  protected function write(array $agent): void {
    $path = '';
    $route = '';
    $viewed = NULL;
    $ip = $this->request->getClientIp();
    $sessionId = md5($ip . $this->getUserAgent() . $agent['res']);

    if ($this->request->hasSession()) {
      $session = $this->request->getSession();
      $sessionId = $session->getId();
    }

    // dataora: data/ora di accesso (YYYYMMDD:hh24:mm:ss )
    // ente: rete civica visitata
    // pagina: percorso completo della risorsa visitata (solo pagine, esclusi script o css)
    // idSession: identificativo della sessione trasmesso dal browser
    // useragent: come da default apache (dati sul dispositivo e sul browser)
    $requestTime = $this->time->getRequestTime();

    $fields = [
      'visitors_date_time'  => date("Ymd:H:i:s", $requestTime),
      'visitors_site'       => $agent['idsite'],
      'visitors_url'        => $agent['url'],
      'visitors_session_id' => $sessionId,
      'visitors_user_agent' => $this->getUserAgent(),
    ];

    // scrivere nel file
    $this->logger->log($fields);
  }

  /**
   * Get visitor user agent.
   *
   * @return string
   *   string user agent, or empty string if user agent does not exist
   */
  protected function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
  }
}

<?php

namespace Drupal\silfi_log;

/**
 * Interface for tracker visitors.
 */
interface VisitorsTrackerInterface {

  /**
   * Logs the visitors action.
   *
   * @param string[] $agent
   *   The agent array.
   */
  public function log(array $agent): void;

}

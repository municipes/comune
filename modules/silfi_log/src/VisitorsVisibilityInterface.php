<?php

namespace Drupal\silfi_log;

use Drupal\Core\Session\AccountInterface;

/**
 * Interface for checking visitors visibility.
 */
interface VisitorsVisibilityInterface {

  /**
   * Tracking visibility check for an user object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   A user object containing an array of roles to check.
   *
   * @return bool
   *   TRUE if the current user is being tracked by Visitors, otherwise FALSE.
   */
  public function user(AccountInterface $account): bool;

  /**
   * Tracking visibility check for pages.
   *
   * @return bool
   *   TRUE if JS code should be added to the current page and otherwise FALSE.
   */
  public function page(): bool;

  /**
   * Tracking visibility check for user roles.
   *
   * @return bool
   *   TRUE if JS code should be added for the current role and otherwise FALSE.
   */
  public function isVisible(): bool;

  /**
   * Tracking visibility check for user roles.
   *
   * Based on visibility setting this function returns TRUE if Visitors code
   * should be added for the current role and otherwise FALSE.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   A user object containing an array of roles to check.
   *
   * @return bool
   *   TRUE if JS code should be added for the current role and otherwise FALSE.
   */
  public function roles(AccountInterface $account): bool;

}

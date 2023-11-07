<?php

namespace Drupal\silfi_opencityitalia\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Cache\Cache;

/**
 * Controller routines for menu example routes.
 *
 * The response of Drupal's HTTP Kernel system's request is generated by
 * a piece of code called the controller.
 *
 * In Drupal 8, we use a controller class
 * for placing those piece of codes in methods which responds to a route.
 *
 * This file will be placed at {module_name}/src/Controller directory. Route
 * entries uses a key '_controller' to define the method called from controller
 * class.
 *
 * @see https://www.drupal.org/docs/8/api/routing-system/introductory-drupal-8-routes-and-controllers-example
 */
class OpencityitaliaController extends ControllerBase {

  /**
   * Page callback for the booking page.
   *
   * The controller callback defined menu_examples.routing.yml file,
   * maps the path 'examples/menu-example' to this method.
   *
   * @throws \InvalidArgumentException
   */
  public function booking() {
    $build['content'] = [
      '#theme' => 'opencityitalia_pages',
      '#data' => [
        'enabled' => $this->setting('enabled'),
        'page' => 'bookings',
        'common_url' => $this->setting('common_url'),
        'widget' => $this->setting('booking_path'),
        'variables' => $this->prepareVariables('variables_path')
      ],
    ];
    $build['#cache']['max-age'] = Cache::PERMANENT;

    return $build;
  }

  /**
   * Page callback for the inefficiencies page.
   *
   * The controller callback defined menu_examples.routing.yml file,
   * maps the path 'examples/menu-example' to this method.
   *
   * @throws \InvalidArgumentException
   */
  public function inefficiencies() {
    $build['content'] = [
      '#theme' => 'opencityitalia_pages',
      '#data' => [
        'enabled' => $this->setting('enabled'),
        'page' => 'inefficiencies',
        'common_url' => $this->setting('common_url'),
        'widget' => $this->setting('inefficency_path'),
        'variables' => array_merge($this->prepareVariables('variables_path'), $this->prepareVariables('inefficency_variables')),
      ],
    ];
    // $build['#cache']['max-age'] = Cache::PERMANENT;

    return $build;
  }

  /**
   * Page callback for the inefficiencies page.
   *
   * The controller callback defined menu_examples.routing.yml file,
   * maps the path 'examples/menu-example' to this method.
   *
   * @throws \InvalidArgumentException
   */
  public function helpdesk() {
    $build['content'] = [
      '#title' => NULL,
      '#theme' => 'opencityitalia_pages',
      '#data' => [
        'enabled' => $this->setting('enabled'),
        'page' => 'helpdesk',
        'common_url' => $this->setting('common_url'),
        'widget' => $this->setting('helpdesk_path'),
        'variables' => $this->prepareVariables('variables_path')
      ],
    ];
    // $build['#cache']['max-age'] = Cache::PERMANENT;

    return $build;
  }

  /**
   * Prepara le variabili con chiave|valore
   *
   * @param string $setting
   * @return array
   */
  protected function prepareVariables(string $setting): array {
    $list = explode("\n", $this->setting($setting));
    $list = array_map('trim', $list);
    $list = array_filter($list, 'strlen');
    foreach ($list as $key => $value) {
      $listFinal[$key] = str_replace('%site', $this->setting('common_url'), $value);
    }
    return $listFinal;
  }

  /**
   * Helper method to access the settings of this module.
   *
   * @param string $key
   *   The key of the configuration.
   *
   * @return mixed
   */
  protected function setting(string $key): mixed {
    $setting = $this->config('silfi_opencityitalia.settings')->get($key);
    if (!$setting) {
      throw new \Exception('Configurazione mancante.');
    }

    return $setting;
  }
}
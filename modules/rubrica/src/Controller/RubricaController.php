<?php

namespace Drupal\rubrica\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for Rubrica routes.
 */
class RubricaController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works! '),
    ];

    return $build;
  }

}

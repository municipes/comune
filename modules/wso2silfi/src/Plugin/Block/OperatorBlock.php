<?php

namespace Drupal\wso2silfi\Plugin\Block;

use Drupal\Core\Block\BlockBase;

// /**
//  * Provides a 'OperatorBlock' block.
//  *
//  * @Block(
//  *  id = "operator_block",
//  *  admin_label = @Translation("WSO2 Blocco login Operatore"),
//  * )
//  */
class OperatorBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    if (!\Drupal::config('wso2silfi.settings')->get('general.wso2silfi_enabled')) {
      return;
    }

    if (!\Drupal::currentUser()->isAnonymous()) {
      return;
    }

    /** @var \Symfony\Component\HttpFoundation\Request $request */
    $request = \Drupal::service('request_stack')->getCurrentRequest();
    // $fullUrl = $request->getSchemeAndHttpHost() . $request->getRequestUri();

    return [
      '#theme' => 'wso2silfi_block',
      '#title' => 'WSO2 login Operatore',
      '#profile' => 'operatore',
      '#requestUri' => \rawurlencode($request->getRequestUri()),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}

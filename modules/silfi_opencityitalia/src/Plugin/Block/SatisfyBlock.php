<?php

namespace Drupal\silfi_opencityitalia\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Uuid\Uuid;

/**
 * Provides an valutation block.
 *
 * @Block(
 *   id = "silfi_opencityitalia_satisfy",
 *   admin_label = @Translation("OpenCityItalia: Valutazione"),
 *   category = @Translation("Silfi OpenCityItalia")
 * )
 */
class SatisfyBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'uuid' => '',
      'domain' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['uuid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site UUID'),
      '#description' => $this->t('UUID fornito da OpenCityItalia'),
      '#default_value' => $this->configuration['uuid'],
    ];
    $form['domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site domain'),
      '#description' => $this->t('Ex: www.comune.pontassieve.it'),
      '#default_value' => $this->configuration['domain'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['uuid']
      = $form_state->getValue('uuid');
    $this->configuration['domain']
      = $form_state->getValue('domain');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if (Uuid::isValid($this->configuration['uuid'])) {
      $build['content'] = [
        '#theme' => 'oci_satisfy_block',
        '#uuid' => $this->configuration['uuid'],
      ];
    }
    else {
      $build['content'] = [
        '#markup' => $this->t('UUID mancante o non valido'),
      ];
    }

    return $build;
  }

}

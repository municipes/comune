<?php

namespace Drupal\silfi_services\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Modulo di configurazione per impostare la chiave API.
 */
class SilfiServicesSettingsForm extends ConfigFormBase {

  /**
   * Restituisce l'ID della form.
   *
   * @return string
   *   L'ID della form.
   */
  public function getFormId() {
    return 'silfi_services_settings_form';
  }

  /**
   * Restituisce l'ID della configurazione.
   */
  protected function getEditableConfigNames() {
    return ['silfi_services.settings'];
  }

  /**
   * Costruisce la form per la configurazione.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('silfi_services.settings');

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#description' => $this->t('Inserisci la chiave API per l\'autenticazione delle richieste.'),
      '#default_value' => $config->get('api_key'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Gestisce il submit della form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('silfi_services.settings')
      ->set('api_key', $form_state->getValue('api_key'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}

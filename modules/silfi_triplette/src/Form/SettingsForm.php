<?php

namespace Drupal\silfi_triplette\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Silfi Triplette settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Creates a SettingsForm instance.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   *
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'silfi_triplette_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['silfi_triplette.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('silfi_triplette.settings');

    $form['silfi_triplette_config'] = [
      '#type' => 'fieldset',
      '#title' => t('Configurazioni triplette'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];
    $form['silfi_triplette_config']['silfi_triplette_url'] = [
      '#type' => 'textfield',
      '#title' => t('URL host'),
      '#default_value' => $config->get('silfi_triplette_url'),
      '#description' => t('Inserire l\'url del web service da chiamare completo di protocollo http o https'),
      '#required' => TRUE,
    ];
    $form['silfi_triplette_config']['silfi_triplette_codice_ente'] = [
      '#type' => 'textfield',
      '#title' => t('Codice Ente'),
      '#default_value' => $config->get('silfi_triplette_codice_ente'),
      '#description' => t('Inserire il codice ente per l\'importazione. CASE SENSITIVE'),
      '#required' => TRUE,
    ];
    $form['silfi_triplette_config_cron'] = [
      '#type' => 'fieldset',
      '#title' => t('Cron'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];
    $options = [1 => 'Attivo', 0 => 'Disattivo'];
    $form['silfi_triplette_config_cron']['silfi_triplette_sync'] = [
      '#type' => 'radios',
      '#title' => t('Attivazione cron'),
      '#default_value' => $config->get('silfi_triplette_sync'),
      '#options' => $options,
      '#description' => t('Attivare la sincronizzazione al processo di manutenzione del cron'),
      '#required' => TRUE,
    ];
    $options = [
      '1800'  => '30 min',
      '3600'  => '60 min',
      '7200'  => '2 ore',
      '21600' => '6 ore',
      '43200' => '12 ore',
      '86400' => '1 volta al giorno',
    ];
    $form['silfi_triplette_config_cron']['silfi_triplette_timecron'] = [
      '#type' => 'select',
      '#title' => t('Frequenza cron'),
      '#default_value' => $config->get('silfi_triplette_timecron'),
      '#options' => $options,
      '#description' => t('Selezionare la frequenza del cron'),
      '#required' => TRUE,
    ];
    $form['silfi_triplette_config_cron']['lightweight_access_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Lightweight cron access key'),
      '#default_value' => $config->get('lightweight_cron_access_key'),
      '#required' => TRUE,
      '#size' => 35,
      '#description' => $this->t("Similar to Drupal's cron key this acts as a
        security token to prevent unauthorised calls to silfi_triplette/cron. The key should be passed as silfi_triplette/cron/{access key}"),
    ];
    // Add a submit handler function for the key generation.
    $form['silfi_triplette_config_cron']['create_key'][] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate new random key'),
      '#submit' => ['::generateRandomKey'],
      // No validation at all is required in the equivocate case, so
      // we include this here to make it skip the form-level validator.
      '#validate' => [],
    ];
    $base_url = $GLOBALS['base_url'];
    $form['silfi_triplette_config_cron']['silfi_triplette_token_url'] = [
      '#markup' => 'Url da chiamare per inizializzare il cron: '.
      $base_url . '/silfi_triplette/cron/' . $config->get('lightweight_cron_access_key'),
      '#title' => t('URL Token'),
      '#prefix' => '<div class="url-token">',
      '#sufix' => '</div>'
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // if ($form_state->getValue('example') != 'example') {
    //   $form_state->setErrorByName('example', $this->t('The value is not correct.'));
    // }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('silfi_triplette.settings')
      ->set('silfi_triplette_url', $form_state->getValue('silfi_triplette_url'))
      ->set('silfi_triplette_codice_ente', $form_state->getValue('silfi_triplette_codice_ente'))
      ->set('silfi_triplette_sync', $form_state->getValue('silfi_triplette_sync'))
      ->set('silfi_triplette_timecron', $form_state->getValue('silfi_triplette_timecron'))
      ->set('lightweight_cron_access_key', $form_state->getValue('lightweight_access_key'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Form submission handler for the random key generation.
   *
   * This only fires when the 'Generate new random key' button is clicked.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function generateRandomKey(array &$form, FormStateInterface $form_state) {
    $config = $this->config('silfi_triplette.settings');
    $config->set('lightweight_cron_access_key', substr(md5(rand()), 0, 32));
    $config->save();
    parent::submitForm($form, $form_state);
  }
}

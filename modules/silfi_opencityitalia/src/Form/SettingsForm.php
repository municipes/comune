<?php

namespace Drupal\silfi_opencityitalia\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Visitors Settings Form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'silfi_opencityitalia.settings';

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
    return 'silfi_opencityitalia_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('silfi_opencityitalia.settings');

    $form['enabled'] = [
      '#type'          => 'checkbox',
      '#title'         => t('Silfi Open City Italia'),
      '#default_value' => $config->get('enabled'),
    ];

    $form['common_url'] = [
      '#type'          => 'textfield',
      '#title'         => t('URL di base'),
      '#description'   => t('URL base dell\'installazione Open City Italia, es: https://servizi.comune.bugliano.pi.it'),
      '#default_value' => $config->get('common_url'),
      '#states'        => [
        'visible' => [':input[name="enabled"]' => ['checked' => TRUE]],
      ],
    ];

    $description = $this->t(
      "<p>Inserire, una per riga, le variabili da usare con il loro valore, nel formato. Es: OC_BASE_URL|%site/lang</p>" .
      "<p>usare il token %site quando nel valore della variabile ci deve essere l'URL di base</p>",
    );
    $form['variables_path'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Variabili e path'),
      // '#title_display' => 'invisible',
      '#default_value' => !empty($config->get('variables_path')) ? $config->get('variables_path') : '',
      '#description' => $description,
      '#rows' => (int) 10,
      '#states'        => [
        'visible' => [':input[name="enabled"]' => ['checked' => TRUE]],
      ],
    ];

    $form['booking_path'] = [
      '#type'          => 'textfield',
      '#title'         => t('Path script: Prenota appuntamento'),
      '#description'   => t('Path dello script per la prenotazione appuntamento, es: /widgets/bookings/bootstrap-italia@2/js/bookings.js'),
      '#default_value' => $config->get('booking_path'),
      '#states'        => [
        'visible' => [':input[name="enabled"]' => ['checked' => TRUE]],
      ],
    ];

    $form['inefficency_path'] = [
      '#type'          => 'textfield',
      '#title'         => t('Path script: Disservizi'),
      '#description'   => t('Path dello script per i disservizi, es: /widgets/inefficiencies/bootstrap-italia@2/js/inefficiencies.js'),
      '#default_value' => $config->get('inefficency_path'),
      '#states'        => [
        'visible' => [':input[name="enabled"]' => ['checked' => TRUE]],
      ],
    ];

    $description = $this->t(
      "Inserire, una per riga, le variabili specifiche per i <strong>Disservizi</strong> da usare con il loro valore, nel formato. Es: OC_MAP_SEARCH_PROVIDER|nominatim.openstreetmap.org",
    );
    $form['inefficency_variables'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Variabili e path per i Disservizi'),
      // '#title_display' => 'invisible',
      '#default_value' => !empty($config->get('inefficency_variables')) ? $config->get('inefficency_variables') : '',
      '#description' => $description,
      '#rows' => (int) 10,
      '#states'        => [
        'visible' => [':input[name="enabled"]' => ['checked' => TRUE]],
      ],
    ];

    $form['helpdesk_path'] = [
      '#type'          => 'textfield',
      '#title'         => t('Path script: Richiesta assistenza'),
      '#description'   => t('Path dello script per la Richiesta assistenza, es: /widgets/helpdesk/bootstrap-italia@2/js/helpdesk.js'),
      '#default_value' => $config->get('helpdesk_path'),
      '#states'        => [
        'visible' => [':input[name="enabled"]' => ['checked' => TRUE]],
      ],
    ];

    $form['uuid'] = [
      '#type'          => 'textfield',
      '#title'         => t('Questionario di soddisfazione: UUID'),
      '#description'   => t(''),
      '#states'        => [
        'visible' => [':input[name="enabled"]' => ['checked' => TRUE]],
      ],
      '#default_value' => $config->get('uuid'),
    ];

    $form['api'] = [
      '#type'          => 'textfield',
      '#title'         => t('Questionario di soddisfazione: API'),
      '#description'   => t(''),
      '#states'        => [
        'visible' => [':input[name="enabled"]' => ['checked' => TRUE]],
      ],
      '#default_value' => $config->get('api'),
    ];

    $form['satisfy_path'] = [
      '#type'          => 'textfield',
      '#title'         => t('Path script: Questionario soddisfazione'),
      '#description'   => t('Path dello script per il form, es: /widgets/satisfy/js/satisfy.js'),
      '#default_value' => $config->get('satisfy_path'),
      '#states'        => [
        'visible' => [':input[name="enabled"]' => ['checked' => TRUE]],
      ],
    ];

    $form['login_path'] = [
      '#type'          => 'textfield',
      '#title'         => t('Path script: Login'),
      '#description'   => t('Path dello script per il login, es: /widgets/login-box/bootstrap-italia@2/js/login-box.js'),
      '#default_value' => $config->get('login_path'),
      '#states'        => [
        'visible' => [':input[name="enabled"]' => ['checked' => TRUE]],
      ],
    ];

    $form['oc_spid_button'] = [
      '#type'          => 'checkbox',
      '#title'         => t('Da usare per accedere tramite la sezione SPID'),
      '#default_value' => $config->get('oc_spid_button'),
      '#states'        => [
        'visible' => [':input[name="enabled"]' => ['checked' => TRUE]],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable(static::SETTINGS);
    $values = $form_state->getValues();

    $config
      ->set('enabled', $values['enabled'])
      ->set('common_url', $values['common_url'])
      ->set('variables_path', $values['variables_path'])
      ->set('booking_path', $values['booking_path'])
      ->set('inefficency_path', $values['inefficency_path'])
      ->set('inefficency_variables', $values['inefficency_variables'])
      ->set('helpdesk_path', $values['helpdesk_path'])
      ->set('uuid', $values['uuid'])
      ->set('api', $values['api'])
      ->set('satisfy_path', $values['satisfy_path'])
      ->set('login_path', $values['login_path'])
      ->set('oc_spid_button', $values['oc_spid_button'])
      ->save();

    parent::submitForm($form, $form_state);
  }
}

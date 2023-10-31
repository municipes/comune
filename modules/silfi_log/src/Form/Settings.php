<?php

namespace Drupal\silfi_log\Form;

use Drupal\Component\FileSecurity\FileSecurity;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Visitors Settings Form.
 */
class Settings extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'silfi_log.settings';

  /**
   * Shows this block on every page except the listed pages.
   */
  const PATH_NOT_LISTED = 0;

  /**
   * Shows this block on only the listed pages.
   */
  const PATH_LISTED = 1;

  /**
   * When visibility on pages is conditioned by PHP code.
   */
  public const VISIBILITY_REQUEST_PATH_MODE_PHP = 2;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Using constructor less class instantiation inspired by the Webform
    // module.
    // @see https://www.drupal.org/node/3076421
    $instance = parent::create($container);
    $instance->currentUser = $container->get('current_user');
    $instance->httpClient = $container->get('http_client');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'silfi_log_admin_settings';
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
    $config = $this->config('silfi_log.settings');
    $form = parent::buildForm($form, $form_state);

    // Visibility settings.
    $form['tracking_scope'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Tracking scope'),
      '#attached' => [
        'library' => [
          'silfi_log/silfi_log.admin',
        ],
      ],
    ];

    $form['tracking']['filelogging'] = [
      '#type' => 'details',
      '#title' => $this->t('File logging'),
      '#group' => 'tracking_scope',
    ];

    $form['tracking']['filelogging']['silfi_log_enabled'] = [
      '#type'          => 'checkbox',
      '#title'         => t('Silfi Log visits to file'),
      '#default_value' => $config->get('enabled'),
    ];

    $form['tracking']['filelogging']['silfi_log'] = [
      '#type'   => 'fieldset',
      '#title'  => 'Logfile settings',
      '#tree'   => TRUE,
      '#states' => [
        'visible' => [':input[name="silfi_log_enabled"]' => ['checked' => TRUE]],
      ],
    ];

    $form['tracking']['filelogging']['silfi_log']['id_site'] = [
      '#type'          => 'textfield',
      '#title'         => t('ID Site'),
      '#description'   => t('The site ID. Es: FIRENZE'),
      '#states'        => [
        'required' => [':input[name="silfi_log_enabled"]' => ['checked' => TRUE]],
      ],
      '#default_value' => $config->get('id_site'),
    ];

    $location = $config->get('file.location');
    if (!str_starts_with($location, 'public://') && !str_starts_with($location, '/')) {
      // $location = substr($location, strlen('public://'));
      $location = 'public://' . $location;
    }

    $form['tracking']['filelogging']['silfi_log']['location'] = [
      '#type'          => 'textfield',
      '#title'         => t('Location'),
      '#description'   => t('The location where logs are saved. Relative paths are inside the public <code>files/</code> directory, but protected from web access.'),
      '#states'        => [
        'required' => [':input[name="silfi_log_enabled"]' => ['checked' => TRUE]],
      ],
      '#default_value' => $location,
    ];

    $form['tracking']['filelogging']['silfi_log']['rotation'] = [
      '#type'     => 'details',
      '#open'     => $config->get('rotation.mode') !== 'none',
      '#title'    => t('Rotation'),
      '#required' => $config->get('rotation.enabled'),
      '#tree'     => TRUE,
    ];

    $form['tracking']['filelogging']['silfi_log']['rotation']['schedule'] = [
      '#type'          => 'select',
      '#title'         => t('Schedule'),
      '#options'       => [
        'daily'   => t('Daily'),
        'weekly'  => t('Weekly'),
        'monthly' => t('Monthly'),
        'never'   => t('Never'),
      ],
      '#default_value' => $config->get('rotation.schedule'),
      '#description'   => t('The rotation will happen on the first cron run after the specified part of the calendar date changes; this is dependent on the server timezone. Use an external cron task for more control.'),
    ];

    $form['tracking']['filelogging']['silfi_log']['rotation']['delete'] = [
      '#type'          => 'checkbox',
      '#title'         => t('Delete logfile instead of moving it.'),
      '#default_value' => $config->get('rotation.delete'),
      '#states'        => [
        'invisible' => [':input[name="silfi_log[rotation][schedule]"]' => ['value' => 'never']],
      ],
    ];

    $form['tracking']['filelogging']['silfi_log']['rotation']['destination'] = [
      '#type'          => 'textfield',
      '#title'         => t('Destination filename'),
      '#default_value' => $config->get('rotation.destination'),
      '#description'   => t('Where to save archived files (relative to the log directory). Use <code>[date:custom:...]</code> to include a date. Old files with the same name will be overwritten.'),
      '#states'        => [
        'invisible' => [
          [':input[name="silfi_log[rotation][schedule]"]' => ['value' => 'never']],
          [':input[name="silfi_log[rotation][delete]"]' => ['checked' => TRUE]],
        ],
      ],
    ];

    $gzip = extension_loaded('zlib');
    $form['tracking']['filelogging']['silfi_log']['rotation']['gzip'] = [
      '#type'          => 'checkbox',
      '#title'         => t('Compress archived files with <code>gzip</code>.'),
      '#default_value' => $config->get('rotation.gzip') && $gzip,
      '#disabled'      => !$gzip,
      '#states'        => [
        'invisible' => [
          [':input[name="silfi_log[rotation][schedule]"]' => ['value' => 'never']],
          [':input[name="silfi_log[rotation][delete]"]' => ['checked' => TRUE]],
        ],
      ],
    ];


    // Page specific visibility configurations.
    $visibility_request_path_pages = $config->get('visibility.request_path_pages');

    $form['tracking']['page_visibility_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Pages'),
      '#group' => 'tracking_scope',
    ];

    if ($config->get('visibility.request_path_mode') == self::VISIBILITY_REQUEST_PATH_MODE_PHP) {
      // No permission to change PHP snippets, but keep existing settings.
      $form['tracking']['page_visibility_settings'] = [];
      $form['tracking']['page_visibility_settings']['silfi_log_visibility_request_path_mode'] = [
        '#type' => 'value',
        '#value' => self::VISIBILITY_REQUEST_PATH_MODE_PHP,
      ];
      $form['tracking']['page_visibility_settings']['silfi_log_visibility_request_path_pages'] = [
        '#type' => 'value',
        '#value' => $visibility_request_path_pages,
      ];
    } else {
      $options = [
        $this->t('All pages except those listed'),
        $this->t('Only the listed pages'),
      ];
      $description = $this->t(
        "Specify pages by using their paths. Enter one path per line. The '*' character is a wildcard. Example paths are %blog for the blog page and %blog-wildcard for every personal blog. %front is the front page.",
        [
          '%blog' => '/blog',
          '%blog-wildcard' => '/blog/*',
          '%front' => '<front>',
        ]
      );

      $form['tracking']['page_visibility_settings']['silfi_log_visibility_request_path_pages'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Pages'),
        '#title_display' => 'invisible',
        '#default_value' => !empty($visibility_request_path_pages) ? $visibility_request_path_pages : '',
        '#description' => $description,
        '#rows' => (int) 10,
      ];
      $form['tracking']['page_visibility_settings']['silfi_log_visibility_request_path_mode'] = [
        '#type' => 'radios',
        '#title' => $this->t('Add tracking to specific pages'),
        '#title_display' => 'invisible',
        '#options' => $options,
        '#default_value' => $config->get('visibility.request_path_mode'),
      ];
    }

    // Status Code configurations.
    $form['tracking']['status_codes'] = [
      '#type' => 'details',
      '#title' => $this->t('Status Codes'),
      '#group' => 'tracking_scope',
    ];

    $form['tracking']['status_codes']['status_codes_disabled'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Prevent tracking of pages with given HTTP Status code:'),
      '#options' => [
        '404' => $this->t('404 - Not found'),
        '403' => $this->t('403 - Access denied'),
      ],
      '#default_value' => $config->get('status_codes_disabled'),
    ];

    // Advanced feature configurations.
    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => TRUE,
    ];

    $form['advanced']['visitors_disable_tracking'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable tracking'),
      '#description' => $this->t('If checked, the tracking code is disabled generally.'),
      '#default_value' => $config->get('disable_tracking'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\File\FileSystemInterface $fileSystem */
    $fileSystem = \Drupal::service('file_system');
    $streamWrapperManager = \Drupal::service('stream_wrapper_manager');
    // Ignore the settings if logging is disabled.
    if ($form_state->getValue('silfi_log_enabled')) {
      // Place relative paths into the public files directory.
      $location = (string) $form_state->getValue(['silfi_log', 'location']);
      if (!$streamWrapperManager->getScheme($location) && $location[0] !== '/') {
        $location = 'public://' . $location;
        $form_state->setValue(['tracking', 'filelogging', 'silfi_log', 'location'], $location);
      }

      // Set up the logging directory.
      if (
        !$fileSystem->prepareDirectory($location, $fileSystem::CREATE_DIRECTORY) ||
        !FileSecurity::writeHtaccess($location)
      ) {
        $form_state->setError(
          $form['tracking']['filelogging']['silfi_log']['location'],
          t(
            'The directory %dir could not be created, or is not writable.',
            [
              '%dir' => $location,
            ]
          )
        );
      }

      // Ensure that gzip is enabled.
      if (
        $form_state->getValue(['tracking', 'filelogging', 'silfi_log', 'rotation', 'gzip']) &&
        !extension_loaded('zlib')
      ) {
        $form_state->setError(
          $form['tracking']['filelogging']['silfi_log']['rotation']['gzip'],
          t('The <em>zlib</em> extension is required for gzip compression.')
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable(static::SETTINGS);
    $values = $form_state->getValues();

    $config
      ->set('enabled', $values['silfi_log_enabled'])
      ->set('id_site', $values['silfi_log']['id_site'])
      ->set('file.location', $values['silfi_log']['location'])
      ->set('rotation.schedule', $values['silfi_log']['rotation']['schedule'])
      ->set('rotation.delete', $values['silfi_log']['rotation']['delete'])
      ->set('rotation.destination', $values['silfi_log']['rotation']['destination'])
      ->set('rotation.gzip', $values['silfi_log']['rotation']['gzip'])
      ->set('visibility.request_path_mode', $values['silfi_log_visibility_request_path_mode'])
      ->set('visibility.request_path_pages', $values['silfi_log_visibility_request_path_pages'])
      ->set('status_codes_disabled', array_values(array_filter($values['status_codes_disabled'])))
      ->set('disable_tracking', $values['visitors_disable_tracking'])
      ->save();

    parent::submitForm($form, $form_state);
  }
}

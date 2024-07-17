<?php

namespace Drupal\silfi_simplenews\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Silfi Newsletter settings for this site.
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
    return 'silfi_simplenews_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['silfi_simplenews.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('silfi_simplenews.settings');

    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('Qui trovi varie impostazioni per il template della newsletter.'),
    ];

    // Add a file upload field to set a custom header image for e-mails.
    $form['header_image_mail'] = [
      '#type' => 'managed_file',
      '#title' => t('Immagine per la testata delle e-mail.'),
      '#default_value' => $config->get('header_image_mail'),
      '#progress_indicator' => 'bar',
      '#progress_message' => t('Please wait...'),
      '#upload_location' => 'public://mail',
      '#upload_validators' => [
        'file_validate_extensions' => [
          'gif png jpg jpeg webp',
        ],
      ],
    ];

    $image = $config->get('header_image_mail') ? $config->get('header_image_mail') : [];
    if (count($image) && $file = File::load($image[0])) {
      $form['image'] = [
        '#theme' => 'image_style',
        '#style_name' => 'medium',
        '#uri' => $file->getFileUri(),
      ];
    }

    $default_values = $config->get('nl_links') ? $config->get('nl_links'): [];

    if ($form_state->get('num_names') === NULL && count($default_values)) {
      $name_field = $form_state->set('num_names', count($default_values));
    }
    // Gather the number of names in the form already.
    $num_names = $form_state->get('num_names');

    // We have to ensure that there is at least one name field.
    if ($num_names === NULL) {
      $name_field = $form_state->set('num_names', 1);
      $num_names = 1;
    }

    $form['#tree'] = TRUE;
    $form['names_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Link'),
      '#prefix' => '<div id="names-fieldset-wrapper">',
      '#suffix' => '</div>',
    ];

    for ($i = 0; $i < $num_names; $i++) {
      $form['names_fieldset']['nl_links'][$i] = [
        'title' => [
          '#type' => 'textfield',
          '#title' => t('Link title'),
          '#default_value' => count($default_values) ? $default_values[$i]['title'] : '',
        ],
        'url' => [
          '#type' => 'url',
          '#title' => t('Link URL'),
          '#default_value' => count($default_values) ? $default_values[$i]['url'] : '',
        ],
      ];
    }

    $form['names_fieldset']['actions'] = [
      '#type' => 'actions',
    ];
    $form['names_fieldset']['actions']['add_name'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add one more'),
      '#submit' => ['::addOne'],
      '#ajax' => [
        'callback' => '::addmoreCallback',
        'wrapper' => 'names-fieldset-wrapper',
      ],
    ];
    // If there is more than one name, add the remove button.
    if ($num_names > 1) {
      $form['names_fieldset']['actions']['remove_name'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove one'),
        '#submit' => ['::removeCallback'],
        '#ajax' => [
          'callback' => '::addmoreCallback',
          'wrapper' => 'names-fieldset-wrapper',
        ],
      ];
    }

    $form['footer_color'] = [
      '#type' => 'color',
      '#title' => t('Footer color'),
      '#default_value' => $config->get('footer_color'),
    ];

    $form['text_color'] = [
      '#type' => 'color',
      '#title' => t('Footer Text color'),
      '#default_value' => $config->get('text_color'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Callback for both ajax-enabled buttons.
   *
   * Selects and returns the fieldset with the names in it.
   */
  public function addmoreCallback(array &$form, FormStateInterface $form_state) {
    return $form['names_fieldset'];
  }

  /**
   * Submit handler for the "add-one-more" button.
   *
   * Increments the max counter and causes a rebuild.
   */
  public function addOne(array &$form, FormStateInterface $form_state) {
    $name_field = $form_state->get('num_names');
    $add_button = $name_field + 1;
    $form_state->set('num_names', $add_button);
    // Since our buildForm() method relies on the value of 'num_names' to
    // generate 'name' form elements, we have to tell the form to rebuild. If we
    // don't do this, the form builder will not call buildForm().
    $form_state->setRebuild();
  }

  /**
   * Submit handler for the "remove one" button.
   *
   * Decrements the max counter and causes a form rebuild.
   */
  public function removeCallback(array &$form, FormStateInterface $form_state) {
    $name_field = $form_state->get('num_names');
    if ($name_field > 1) {
      $remove_button = $name_field - 1;
      $form_state->set('num_names', $remove_button);
    }
    // Since our buildForm() method relies on the value of 'num_names' to
    // generate 'name' form elements, we have to tell the form to rebuild. If we
    // don't do this, the form builder will not call buildForm().
    $form_state->setRebuild();
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
    $debug = $form_state->getValue('header_image_mail');
    $this->config('silfi_simplenews.settings')
      ->set('header_image_mail', $form_state->getValue('header_image_mail'))
      ->set('nl_links', $form_state->getValue(['names_fieldset', 'nl_links']))
      ->set('footer_color', $form_state->getValue('footer_color'))
      ->set('text_color', $form_state->getValue('text_color'))
      ->save();
    parent::submitForm($form, $form_state);
  }
}

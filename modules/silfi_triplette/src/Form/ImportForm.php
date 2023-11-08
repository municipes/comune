<?php

namespace Drupal\silfi_triplette\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Import Silfi Triplette.
 */
class ImportForm extends FormBase {

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
    return 'silfi_triplette_import';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('silfi_triplette.settings');
    $form['confirm_markup'] = [
      '#markup' => '<h2>Vuoi importare tutte le triplette con i seguenti dati?</h2>',
    ];
    $form['confirm_markup_host'] = [
      '#markup' => '<div><label>HOST: </label>' . $config->get('silfi_triplette_url') . '<div>',
    ];
    $form['confirm_markup_ente'] = [
      '#markup' => '<div><label>Ente: </label>' . $config->get('silfi_triplette_codice_ente') . '<div>',
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Import'),
    ];

    return $form;
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

    if ($data = \Drupal::service('silfi_triplette.triplette_json_data')->getTripletteData()) {
      \Drupal::service('silfi_triplette.import')->createAllTreeTerms($data);
    }

  }
}

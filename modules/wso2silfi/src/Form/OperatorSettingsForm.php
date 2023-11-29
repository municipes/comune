<?php

namespace Drupal\wso2silfi\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\wso2silfi\Helper\CheckUserFieldExist;

/**
 * Class OperatorSettingsForm.
 */
class OperatorSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'wso2silfi.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'operator_config';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('wso2silfi.settings');

    // Add OAuth2 fieldset.
    $form['oauth2'] = array(
      '#type' => 'details',
      '#title' => t('OAuth2 information'),
      '#open' => TRUE,
    );
    // OAuth2 client_id.
    $form['oauth2']['client_id'] = [
      '#type' => 'textfield',
      '#title' => t('OAuth2 Client_ID'),
      '#default_value' => $config->get('operator.client_id'),
      '#size' => 25,
      '#maxlength' => 64,
      '#description' => t('The OAuth2 client_id.'),
      '#required' => TRUE,
    ];
      // OAuth2 secret_id.
    $form['oauth2']['client_secret'] = [
      '#type' => 'password',
      '#title' => t('OAuth2 Client_Secret'),
      '#default_value' => $config->get('operator.client_secret'),
      '#size' => 25,
      '#maxlength' => 64,
      '#description' => t('The OAuth2 client_secret.'),
      '#required' => TRUE,
    ];

    //
    $form['login'] = array(
      '#type' => 'details',
      '#title' => t('Login information'),
      '#open' => TRUE,
    );
    //
    $form['login']['username'] = [
      '#type' => 'textfield',
      '#title' => t('Username'),
      '#default_value' => $config->get('operator.username'),
      '#size' => 25,
      '#maxlength' => 64,
      '#description' => t('Autenticazione tokenJWT: username'),
      '#required' => TRUE,
    ];
      //
    $form['login']['password'] = [
      '#type' => 'password',
      '#title' => t('Password'),
      '#default_value' => $config->get('operator.password'),
      '#size' => 25,
      '#maxlength' => 64,
      '#description' => t('Autenticazione tokenJWT: password'),
      '#required' => TRUE,
    ];
    // ambiente di staging
    // $form['login']['stage'] = [
    //   '#type' => 'checkbox',
    //   '#title' => t('Ambiente di staging'),
    //   '#default_value' => $config->get('operator.stage'),
    //   '#description' => t('Usa ambiente di staging invece di quello di produzione.'),
    //   '#required' => FALSE,
    // ];

    $form['params'] = array(
      '#type' => 'details',
      '#title' => t('Parametri'),
      '#open' => TRUE,
    );
    $form['params']['ente'] = [
      '#type' => 'textfield',
      '#title' => t('Ente'),
      '#default_value' => $config->get('operator.ente'),
      '#size' => 25,
      '#maxlength' => 64,
      '#description' => t('Codice ente'),
      '#required' => TRUE,
    ];
    $form['params']['app'] = [
      '#type' => 'textfield',
      '#title' => t('App'),
      '#default_value' => $config->get('operator.app'),
      '#size' => 25,
      '#maxlength' => 64,
      '#description' => t('Codice applicazione'),
      '#required' => TRUE,
    ];

    // Add an attributes fieldset.
    $form['attributes'] = array(
      '#type' => 'fieldset',
      '#title' => t('Attributes mapping'),
    );
    $form['attributes']['role_population'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Automatic role population from LDAP attributes'),
      '#default_value' => $config->get('operator.rolepopulation'),
      '#description' => $this->t('A pipe separated list of rules. Each rule consists of a Drupal role id, a LDAP attribute name, an operation and a value to match. <i>e.g. redattore:Redattore|responsabile:ResponsabileSito... etc</i>.'),
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
    parent::submitForm($form, $form_state);

    $this->config('wso2silfi.settings')
      ->set('operator.client_id', $form_state->getValue('client_id'))
      ->set('operator.client_secret', $form_state->getValue('client_secret'))
      ->set('operator.username', $form_state->getValue('username'))
      ->set('operator.password', $form_state->getValue('password'))
      // ->set('operator.stage', $form_state->getValue('stage'))
      ->set('operator.ente', $form_state->getValue('ente'))
      ->set('operator.app', $form_state->getValue('app'))
      ->set('operator.rolepopulation', $form_state->getValue('role_population'))
      ->save();
  }

}

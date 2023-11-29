<?php

namespace Drupal\wso2silfi\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class GeneralSettingsForm.
 */
class GeneralSettingsForm extends ConfigFormBase {

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
    return 'general_config';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('wso2silfi.settings');

    // Even if the module is activated, it is required to enabled the WSO2 authentication here.
    $form['wso2silfi_enabled'] = [
      '#type' => 'checkbox',
      '#title' => t('Enable WSO2'),
      '#default_value' => $config->get('general.wso2silfi_enabled'),
      '#description' => t('Activate WSO2 when a user trying to connect to Drupal.'),
      '#required' => FALSE,
    ];

    // ambiente di staging
    $form['wso2silfi_stage'] = [
      '#type' => 'checkbox',
      '#title' => t('Ambiente di staging'),
      '#default_value' => $config->get('general.stage'),
      '#description' => t('Usa ambiente di staging invece di quello di produzione.'),
      '#required' => FALSE,
    ];

    $form['wso2silfi_text'] = [
      '#markup' => 'Il path da abilitare in WSO2 come redirect è sempre "oauth2/authorized". ',
    ];
    // $form['wso2_subfolder'] = [
    //   '#type' => 'textfield',
    //   '#title' => t('Sottocartella'),
    //   '#default_value' => $config->get('general.wso2_subfolder') ?? null,
    //   '#size' => 64,
    //   '#maxlength' => 128,
    //   '#description' => t('Se Drupal è installato in una sottocartella, indicare qui il path. Es: /drupal/'),
    //   '#required' => false,
    // ];

    $form['wso2_config'] = [
      '#type' => 'details',
      '#title' => t('WSO2 Configuration'),
      '#description' => t('Sezione WSO2.'),
      '#open' => TRUE, // Controls the HTML5 'open' attribute. Defaults to FALSE.
    ];

    // Define the URL of the server.
    // $form['wso2_config']['server_url'] = [
    //   '#type' => 'textfield',
    //   '#title' => t('WSO2 server URL'),
    //   '#default_value' => $config->get('general.server_url') ?? 'https://id.055055.it:9443/oauth2',
    //   '#size' => 64,
    //   '#maxlength' => 128,
    //   '#description' => t('WSO2 server URL, without trailing slash. <br/>For example : https://id.055055.it:9443/oauth2'),
    //   '#required' => TRUE,
    // ];
    // $form['wso2_config']['logout_url'] = [
    //   '#type' => 'textfield',
    //   '#title' => t('WSO2 logout URL'),
    //   '#default_value' => $config->get('general.logout_url') ?? 'https://id.055055.it:9443/oidc/logout',
    //   '#size' => 64,
    //   '#maxlength' => 128,
    //   '#description' => t('The server URL of the open am, without trailing slash. <br/>For example : https://id.055055.it:9443/oidc/logout'),
    //   '#required' => TRUE,
    // ];

    $form['wso2_config']['agEntityId'] = [
      '#type' => 'textfield',
      '#title' => t('ID servizio'),
      '#default_value' => $config->get('general.agEntityId'),
      '#size' => 25,
      '#maxlength' => 64,
      '#description' => t('ID del servizio. Es: FIRENZE (parameter agEntityId)'),
      '#required' => TRUE,
    ];
    $form['wso2_config']['comEntityId'] = array(
      '#type' => 'textfield',
      '#title' => t('WSO Silfi server URL additional parameter comEntityId'),
      '#default_value' => $config->get('general.comEntityId'),
      '#size' => 64,
      '#maxlength' => 128,
      '#description' => t('Additional parameter comEntityId'),
      '#required' => TRUE,
    );
    // Add OAuth2 fieldset.
    $form['oauth2'] = array(
      '#type' => 'details',
      '#title' => t('OAuth2 information'),
      '#open' => TRUE,
    );

    // methods
    // $form['oauth2']['authorize'] = [
    //   '#type' => 'textfield',
    //   '#title' => t('Authorize method'),
    //   '#default_value' => $config->get('general.authorize') ?? '/authorize',
    //   '#size' => 25,
    //   '#maxlength' => 64,
    //   '#description' => t('Authorized method. With initial slash! es: /authorize'),
    //   '#required' => TRUE,
    // ];
    // $form['oauth2']['token'] = [
    //   '#type' => 'textfield',
    //   '#title' => t('GetToken method'),
    //   '#default_value' => $config->get('general.token') ?? '/token',
    //   '#size' => 25,
    //   '#maxlength' => 64,
    //   '#description' => t('GetToken method. With initial slash! es: /token'),
    //   '#required' => TRUE,
    // ];
    // $form['oauth2']['userinfo'] = [
    //   '#type' => 'textfield',
    //   '#title' => t('UserInfo method'),
    //   '#default_value' => $config->get('general.userinfo') ?? '/userinfo',
    //   '#size' => 25,
    //   '#maxlength' => 64,
    //   '#description' => t('UserInfo method. With initial slash! es: /userinfo'),
    //   '#required' => TRUE,
    // ];

    // proxy
    // $form['oauth2']['proxy_enabled'] = [
    //   '#type' => 'checkbox',
    //   '#title' => t('Enable Proxy configuration'),
    //   '#default_value' => $config->get('general.proxy_enabled'),
    //   '#description' => t('Activate Proxy configuration for SOAP call.'),
    //   '#required' => FALSE,
    // ];
    // skip SSL verification
    $form['oauth2']['skip-ssl-verification'] = array(
      '#type' => 'checkbox',
      '#title' => t('Skip SSL verification'),
      '#default_value' => $config->get('general.skip-ssl-verification'),
      '#description' => t('ONLY FOR DEVELOPMENT.'),
      '#required' => FALSE,
    );
    // picture on login form
    $form['picture_enabled'] = [
      '#type' => 'checkbox',
      '#title' => t('Enable picture (LC logo) on login form'),
      '#default_value' => $config->get('general.picture_enabled'),
      '#description' => t('Activate logo.'),
      '#required' => FALSE,
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
      ->set('general.wso2silfi_enabled', $form_state->getValue('wso2silfi_enabled'))
      ->set('general.stage', $form_state->getValue('wso2silfi_stage'))
      // ->set('general.server_url', $form_state->getValue('server_url'))
      // ->set('general.logout_url', $form_state->getValue('logout_url'))
      ->set('general.agEntityId', $form_state->getValue('agEntityId'))
      ->set('general.comEntityId', $form_state->getValue('comEntityId'))
      // ->set('general.authorize', $form_state->getValue('authorize'))
      // ->set('general.token', $form_state->getValue('token'))
      // ->set('general.userinfo', $form_state->getValue('userinfo'))
      // ->set('general.proxy_enabled', $form_state->getValue('proxy_enabled'))
      ->set('general.skip-ssl-verification', $form_state->getValue('skip-ssl-verification'))
      ->set('general.picture_enabled', $form_state->getValue('picture_enabled'))
      ->save();
  }

}

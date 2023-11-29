<?php

namespace Drupal\wso2silfi\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\wso2silfi\Helper\CheckUserFieldExist;

/**
 * Class CitizenSettingsForm.
 */
class CitizenSettingsForm extends ConfigFormBase {

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
    return 'citizen_config';
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
      '#default_value' => $config->get('citizen.client_id'),
      '#size' => 25,
      '#maxlength' => 64,
      '#description' => t('The OAuth2 client_id.'),
      '#required' => TRUE,
    ];
      // OAuth2 secret_id.
    $form['oauth2']['client_secret'] = [
      '#type' => 'password',
      '#title' => t('OAuth2 Client_Secret'),
      '#default_value' => $config->get('citizen.client_secret'),
      '#size' => 25,
      '#maxlength' => 64,
      '#description' => t('The OAuth2 client_secret.'),
      '#required' => TRUE,
    ];

    // Add an attributes fieldset.
    $form['attributes'] = array(
      '#type' => 'fieldset',
      '#title' => t('Attributes mapping'),
    );
    // Define the username attribute, which will be used for connection.
    $form['attributes']['username_attribute'] = array(
      '#type' => 'textfield',
      '#title' => t('Username'),
      '#default_value' => $config->get('citizen.username_attribute') ?? 'cn',
      '#size' => 25,
      '#maxlength' => 64,
      '#description' => t('The profile name attribute to use as the Drupal username.'),
      '#required' => TRUE,
    );
    // Define the mail attribute.
    $form['attributes']['mail_attribute'] = array(
      '#type' => 'textfield',
      '#title' => t('Mail'),
      '#default_value' => $config->get('citizen.mail_attribute') ?? 'email',
      '#size' => 25,
      '#maxlength' => 64,
      '#description' => t('Define the attribute for the field mail.'),
      '#required' => TRUE,
    );

    if (CheckUserFieldExist::exist('field_user_firstname')) {
      // Define the peopleNome attribute.
      $form['attributes']['firstname_attribute'] = array(
        '#type' => 'textfield',
        '#title' => t('Nome'),
        '#default_value' => $config->get('citizen.firstname_attribute') ?? 'given_name',
        '#size' => 25,
        '#maxlength' => 64,
        '#description' => t('Define the OpenAM attribute for the field name.'),
        '#required' => TRUE,
      );
    }
    else {
      $form['attributes']['no_firstname_attribute'] = array(
        '#type' => 'fieldset',
        '#title' => t('First name field'),
        '#description' => t('Il campo Nome (field_user_firstname) non esiste nel profilo utente; fino a quando non verrà creato il campo per il match non apparirà.'),
      );
      $form['attributes']['firstname_attribute'] = array(
        '#type' => 'hidden',
        '#value' => NULL,
      );
    }
    if (CheckUserFieldExist::exist('field_user_lastname')) {
      // Define the peopleCognome attribute.
      $form['attributes']['lastname_attribute'] = array(
        '#type' => 'textfield',
        '#title' => t('Cognome'),
        '#default_value' => $config->get('citizen.lastname_attribute') ?? 'family_name',
        '#size' => 25,
        '#maxlength' => 64,
        '#description' => t('Define the OpenAM attribute for the field lastname.'),
        '#required' => TRUE,
      );
    }
    else {
      $form['attributes']['no_lastname_attribute'] = array(
        '#type' => 'fieldset',
        '#title' => t('Last name field'),
        '#description' => t('Il campo Cognome (field_user_lastname) non esiste nel profilo utente; fino a quando non verrà creato il campo per il match non apparirà.'),
      );
      $form['attributes']['lastname_attribute'] = array(
        '#type' => 'hidden',
        '#value' => NULL,
      );
    }
    // if (CheckUserFieldExist::exist('field_user_birthday')) {
    //   // Define the peopleDataNascita attribute.
    //   $form['attributes']['birthday_attribute'] = array(
    //     '#type' => 'textfield',
    //     '#title' => t('Data di nascita'),
    //     '#default_value' => $config->get('citizen.birthday_attribute'),
    //     '#size' => 25,
    //     '#maxlength' => 64,
    //     '#description' => t('Define the OpenAM attribute for the field birthday.'),
    //     '#required' => TRUE,
    //   );
    // }
    // else {
    //   $form['attributes']['no_birthday_attribute'] = array(
    //     '#type' => 'fieldset',
    //     '#title' => t('Birthday field'),
    //     '#description' => t('Il campo Giorno di nascita (field_user_birthday) non esiste nel profilo utente; fino a quando non verrà creato il campo per il match non apparirà.'),
    //   );
    //   $form['attributes']['birthday_attribute'] = array(
    //     '#type' => 'hidden',
    //     '#value' => NULL,
    //   );
    // }
    // if (CheckUserFieldExist::exist('field_user_birthplace')) {
    //   // Define the peopleLuogoNascita attribute.
    //   $form['attributes']['birthplace_attribute'] = array(
    //     '#type' => 'textfield',
    //     '#title' => t('Luogo di nascita'),
    //     '#default_value' => $config->get('citizen.birthplace_attribute'),
    //     '#size' => 25,
    //     '#maxlength' => 64,
    //     '#description' => t('Define the OpenAM attribute for the field birthplace.'),
    //     '#required' => TRUE,
    //   );
    // }
    // else {
    //   $form['attributes']['no_birthplace_attribute'] = array(
    //     '#type' => 'fieldset',
    //     '#title' => t('Birthplace field'),
    //     '#description' => t('Il campo Luogo di nascita (field_user_birthplace) non esiste nel profilo utente; fino a quando non verrà creato il campo per il match non apparirà.'),
    //   );
    //   $form['attributes']['birthplace_attribute'] = array(
    //     '#type' => 'hidden',
    //     '#value' => NULL,
    //   );
    // }
    if (CheckUserFieldExist::exist('field_user_fiscalcode')) {
      // Define the peopleLuogoNascita attribute.
      $form['attributes']['fiscalcode_attribute'] = array(
        '#type' => 'textfield',
        '#title' => t('Codice Fiscale'),
        '#default_value' => $config->get('citizen.fiscalcode_attribute') ?? 'cn',
        '#size' => 25,
        '#maxlength' => 64,
        '#description' => t('Define the OpenAM attribute for the field fiscalcode.'),
        '#required' => TRUE,
      );
    }
    else {
      $form['attributes']['no_fiscalcode_attribute'] = array(
        '#type' => 'fieldset',
        '#title' => t('Codice Fiscale field'),
        '#description' => t('Il campo Codice Fiscale (field_user_fiscalcode) non esiste nel profilo utente; fino a quando non verrà creato il campo per il match non apparirà.'),
      );
      $form['attributes']['fiscalcode_attribute'] = array(
        '#type' => 'hidden',
        '#value' => NULL,
      );
    }
    // if (CheckUserFieldExist::exist('field_user_phone')) {
    //   // Define the peopleLuogoNascita attribute.
    //   $form['attributes']['phone_attribute'] = array(
    //     '#type' => 'textfield',
    //     '#title' => t('Luogo di nascita'),
    //     '#default_value' => $config->get('citizen.phone_attribute'),
    //     '#size' => 25,
    //     '#maxlength' => 64,
    //     '#description' => t('Define the OpenAM attribute for the field phone.'),
    //     '#required' => TRUE,
    //   );
    // }
    // else {
    //   $form['attributes']['no_phone_attribute'] = array(
    //     '#type' => 'fieldset',
    //     '#title' => t('Phone field'),
    //     '#description' => t('Il campo Telefono (field_user_phone) non esiste nel profilo utente; fino a quando non verrà creato il campo per il match non apparirà.'),
    //   );
    //   $form['attributes']['phone_attribute'] = array(
    //     '#type' => 'hidden',
    //     '#value' => NULL,
    //   );
    // }

    // $form['attributes']['redirectAfterLogin'] = array(
    //   '#type' => 'textfield',
    //   '#title' => t('Pagina di arrivo dopo il login'),
    //   '#default_value' => $config->get('citizen.redirectAfterLogin'),
    //   '#size' => 60,
    //   '#maxlength' => 60,
    //   '#description' => t('Pagina di arrivo dopo il login'),
    //   '#required' => FALSE,
    // );

    // Define the role for authenticated.
    $roles = array_map([
      '\\Drupal\\Component\\Utility\\Html',
      'escape',
    ], user_role_names(TRUE));
    unset ($roles['authenticated']);
    $roles = ['none' => 'Nessuno (solo Autenticato)'] + $roles;

    $form['attributes']['role'] = array(
      '#type' => 'select',
      '#title' => t('Ruolo da assegnare'),
      '#options' => $roles,
      '#default_value' => $config->get('citizen.role') ? $config->get('citizen.role') : ['none'],
      // '#size' => 25,
      // '#maxlength' => 64,
      '#description' => t('Define the role assigned after user register.'),
      '#required' => FALSE,
    );
    /* aggiunto per bonus viaggi */
    unset ($roles['none']);
    $form['attributes']['roletoexclude'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Ruoli da controllare'),
      '#options' => $roles,
      '#default_value' => NULL === $config->get('citizen.roletoexclude') ? ['administrator'] : $config->get('citizen.roletoexclude'),
      // '#size' => 25,
      // '#maxlength' => 64,
      '#description' => t('Ruoli da verificare al login. Se l\'utente esiste ed ha uno di questi ruoli allora non viene assegnato il ruolo di default'),
      '#required' => FALSE,
    );

    // $form['attributes']['skip-ssl-verification'] = array(
    //   '#type' => 'checkbox',
    //   '#title' => t('Skip SSL verification'),
    //   '#default_value' => $config->get('citizen.skip-ssl-verification'),
    //   '#description' => t('ONLY FOR DEVELOPMENT.'),
    //   '#required' => FALSE,
    // );

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
      // ->set('citizen.client_id', $form_state->getValue('client_id'))
      // ->set('citizen.client_secret', wso2silfi_encrypt($form_state->getValue('client_secret')))
      // ->set('citizen.realm', $form_state->getValue('realm'))
      // ->set('citizen.scope', $form_state->getValue('scope'))
      // ->set('citizen.codiceServizio', $form_state->getValue('codiceServizio'))
      // ->set('citizen.codiceCanale', $form_state->getValue('codiceCanale'))
      ->set('citizen.client_id', $form_state->getValue('client_id'))
      ->set('citizen.client_secret', $form_state->getValue('client_secret'))
      ->set('citizen.username_attribute', $form_state->getValue('username_attribute'))
      ->set('citizen.mail_attribute', $form_state->getValue('mail_attribute'))
      ->set('citizen.firstname_attribute', $form_state->getValue('firstname_attribute'))
      ->set('citizen.lastname_attribute', $form_state->getValue('lastname_attribute'))
      // ->set('citizen.birthday_attribute', $form_state->getValue('birthday_attribute'))
      // ->set('citizen.birthplace_attribute', $form_state->getValue('birthplace_attribute'))
      ->set('citizen.fiscalcode_attribute', $form_state->getValue('fiscalcode_attribute'))
      // ->set('citizen.phone_attribute', $form_state->getValue('phone_attribute'))
      // ->set('citizen.redirectAfterLogin', $form_state->getValue('redirectAfterLogin'))
      ->set('citizen.role', $form_state->getValue('role'))
      ->set('citizen.roletoexclude', $form_state->getValue('roletoexclude'))
      // ->set('citizen.skip-ssl-verification', $form_state->getValue('skip-ssl-verification'))
      ->save();
  }

}

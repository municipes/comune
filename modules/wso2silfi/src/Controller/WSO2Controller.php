<?php

namespace Drupal\wso2silfi\Controller;

use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\ResettableStackedRouteMatchInterface;
use Drupal\wso2silfi\Service\OAuth2Client;
use Drupal\wso2silfi\Service\GestionePrivilegiOperatori;
use Drupal\externalauth\Authmap;
use Drupal\externalauth\ExternalAuth;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\wso2silfi\Helper\CheckUserFieldExist;
use Drupal\wso2silfi\Helper\Status;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;


/**
 * Class OauthController.
 */
class WSO2Controller extends ControllerBase {

  /**
   * The http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * I valori della classe Status.
   *
   * @var \Drupal\wso2silfi\Helper\Status
   */
  protected $statusClass;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * @var string
   */
  protected $server_url;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The Current Route Match.
   *
   * @var \Drupal\Core\Routing\ResettableStackedRouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * WSO2 enabled.
   *
   * @var bool.
   */
  protected $wso2Enable;

  /**
   * @var Drupal\oauth2_client_lineacomune\Service\OAuth2Client
   */
  protected $oauth2client;

  /**
   * External Authentication's map between local users and service users.
   *
   * @var \Drupal\externalauth\Authmap
   */
  protected $authmap;

  /**
   * External Authentication's service for authenticating users.
   *
   * @var \Drupal\externalauth\ExternalAuth
   */
  protected $externalauth;

  /**
   * The tempstore client - acts like $_SESSION.
   *
   * @var \Drupal\user\PrivateTempStore
   */
  protected $tempstore;

  /**
   * Gestione privilegi operatore
   *
   * @var \Drupal\wso2silfi\Service\GestionePrivilegiOperatori
   */
  protected $gestionePrivilegiOperatore;

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $requestStack;

  protected $access_type;

  /**
   * {@inheritdoc}
   *
   * @param Client $http_client
   *   A GuzzleHttp client.
   *
   * @param Status $statusClass
   *   The statusClass object.
   *
   * @param LoggerInterface $logger
   *   A logger instance.
   *
   * @param Authmap $externalauth_authmap
   *   A Authmap instance.
   *
   * @param ExternalAuth $externalauth_externalauth
   *   The ExternalAuth factory.
   *
   * @param MessengerInterface $messenger
   *   The messenger factory.
   *
   * @param OAuth2Client $oauth2client
   *   A OAuth2 client.
   *
   * @param Authmap $externalauth_authmap
   *   A Authmap instance.
   *
   * @param ExternalAuth $externalauth_externalauth
   *   The ExternalAuth factory.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempstore
   *   The user private tempstore - acts like $_SESSION.
   *
   * @param GestionePrivilegiOperatori $gestionePrivilegiOperatore
   *   Gestione Privilegi Operatore.
   *
   */
  public function __construct(
    Client $http_client,
    Status $statusClass,
    LoggerInterface $logger,
    MessengerInterface $messenger,
    ResettableStackedRouteMatchInterface $currentRouteMatch,
    OAuth2Client $oauth2client,
    Authmap $externalauth_authmap,
    ExternalAuth $externalauth_externalauth,
    PrivateTempStoreFactory $tempstore,
    GestionePrivilegiOperatori $gestionePrivilegiOperatore,
    RequestStack $request_stack
  ) {
    $this->httpClient = $http_client;
    $this->statusClass = $statusClass;
    $this->logger = $logger;
    $this->setMessenger($messenger);
    $this->currentRouteMatch = $currentRouteMatch;
    $this->wso2Enable = $this->statusClass->isEnabled();
    $this->oauth2client = $oauth2client;
    $this->authmap = $externalauth_authmap;
    $this->externalauth = $externalauth_externalauth;
    $this->tempstore = $tempstore->get('wso2silfi');
    $this->gestionePrivilegiOperatore = $gestionePrivilegiOperatore;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('wso2silfi.status'),
      $container->get('logger.factory')->get('wso2silfi'),
      $container->get('messenger'),
      $container->get('current_route_match'),
      $container->get('oauth2.client'),
      $container->get('externalauth.authmap'),
      $container->get('externalauth.externalauth'),
      $container->get('tempstore.private'),
      $container->get('wso2silfi.gestione_privilegi_operatori'),
      $container->get('request_stack')
    );
  }

  /**
   * Initiating OAuth SSO flow
   */
  public function clientLogin(string $access_type, Request $request) {
    global $base_url;

    if ($this->wso2Enable) {
      // get your GET parameter
      $destination = $request->query->get('destination');
      $this->access_type = $access_type;
      $this->tempstore->set('access_type', $access_type);
      \Drupal::service('page_cache_kill_switch')->trigger();

      if (\Drupal::currentUser()->isAnonymous()) {
        $this->oauth2client->init($access_type);

        $redirect = [
          'uri' => $destination,
          'client' => 'wso2silfi',
        ];
        $state = $this->oauth2client->state;
        $this->oauth2client->setRedirect($state, $redirect);
        $access_token = $this->oauth2client->getAccessToken();
        // echo $access_token;
      }
    } else {
      $this->messenger->addMessage(t('Please enable <strong>Login with WSO2</strong> to initiate the SSO.'), 'error');
      return new RedirectResponse($base_url);
    }
    $this->messenger->addMessage(t('Sei già autenticato!'), 'error');
    return new RedirectResponse($base_url);
  }



  /**
   * {@inheritdoc}
   */
  public function redirectUrlPage(Request $request) {
    $wso2Response = [
      'code' => $this->requestStack->getCurrentRequest()->query->get('code'),
      'session_state' => $this->requestStack->getCurrentRequest()->query->get('session_state'),
      'state' => $this->requestStack->getCurrentRequest()->query->get('state'),
    ];
    $this->logger->debug('redirectUrlPage %data.', ['%data' => '<pre><code>' . print_r($wso2Response, true) . '</code></pre>']);
    // If there is any error in the server response, display it.
    if ($this->currentRouteMatch->getParameter('error')) {
      $error = $this->currentRouteMatch->getParameter('error');
      $error_description = $this->currentRouteMatch->getParameter('error_description');
      $message = $this->t('Error: @error: @error_description', ['@error' => $error, '@error_description' => $error_description]);

      $this->messenger()->addError($message);
      $this->logger->error('redirectUrlPage %data.', ['%data' => '<pre><code>' . print_r($error, true) . '</code></pre>']);
    }

    $redirects = $this->tempstore->get('redirect');
    $access_token = $this->oauth2client->getAccessToken();
    $token = $this->tempstore->get('single_token');
    $access_type = $this->tempstore->get('access_type');
    $token_info = $this->oauth2client->getTokenInfo($access_token);

    switch ($access_type) {
      case 'cittadino':
        /**
         * CIE:
         * $token_info["sub"] = "_bbec509ce73cf2208dee9247cace8bf094a451d647@carbon.super"
         * $token_info["cn"]  = "MRIRSS55H26501F"
         * $token_info["given_name"] = "Mario"
         * $token_info["family_name"] = "Rossi"
         * SPID in più:
         * $token_info["email"] = "mario@rossi.it"
         */

        /** check CNS */
        $is_spid = isset($token_info["cn"]) ? TRUE : FALSE;
        $username_attr = $is_spid ? 'cn' : 'upn';
        $email_attr = $is_spid ? 'email' : 'sub';
        /** andiamo a creare utente o fare login se esiste */
        $email = isset($token_info[$email_attr]) ? $token_info[$email_attr] : $token_info["given_name"] . '.' . $token_info["family_name"] . '@cie.nomail';
        $account_data = [
          'mail' => $email,
        ];

        $user = $this->loginUser($token_info[$username_attr], $account_data, 'wso2');
        $roleToExclude = $this->statusClass->citizenRoleToExclude();
        /* aggiungiamo ruolo */
        $role = (string)$this->statusClass->citizenRole();
        if (!$this->userHasRoles($roleToExclude, $user) && $role != 'none') {
          $user->addRole($role);
        }
        // Set the user fields value new value.
        if (
          CheckUserFieldExist::exist('field_user_firstname') &&
          isset($token_info['given_name'])
        ) {
          $user->set('field_user_firstname', $token_info['given_name']);
        }
        if (
          CheckUserFieldExist::exist('field_user_lastname') &&
          isset($token_info['family_name'])
        ) {
          $user->set('field_user_lastname', $token_info['family_name']);
        }
        // if (CheckUserFieldExist::exist('field_user_birthday') &&
        // isset($token_info['peopleDataNascita'])) {
        //   $user->set('field_user_birthday', $token_info['peopleDataNascita']);
        // }
        // if (CheckUserFieldExist::exist('field_user_birthplace') &&
        // isset($token_info['peopleLuogoNascita'])) {
        //   $comune = $this->getComune($token_info['peopleLuogoNascita']);
        //   $user->set('field_user_birthplace', $comune);
        // }
        if (
          CheckUserFieldExist::exist('field_user_fiscalcode') &&
          isset($token_info[$username_attr])
        ) {
          $user->set('field_user_fiscalcode', $token_info[$username_attr]);
        }
        // if (CheckUserFieldExist::exist('field_user_phone') &&
        //   isset($token_info['peopleTelefono'])) {
        //   $user->set('field_user_phone', $token_info['peopleTelefono']);
        // }

        $user->save();
        break;

        /**
         * sub: "operatore_michele"
         * upn: "operatore_michele"
         * groups: "OPERATORI-STAGING/OPERATORE_FIRENZE,Internal/everyone"
         * cn: "operatore_michele"
         * given_name: "Michele"
         * family_name: "Biagiotti"
         * email: "m.biagiotti@lineacomune.it"
         */
      case 'operatore':
      default:
        if ($this->gestionePrivilegiOperatore->login()) {
          $memberOf = $token_info['groups'];
          $tokenJwt = $this->gestionePrivilegiOperatore->getTokenJwt();
          $stage = $this->statusClass->isStage();
          $auth_ente = $this->statusClass->operatorEnte();
          if ($this->getEnte($memberOf, $stage) != $auth_ente) {
            return [
              '#markup' => 'Utente non abilitato!'
            ];
          }
          $authname = $token_info['cn'];
          $account_data = [
            'mail' => $token_info['email'],
          ];
          /* chiedo la lista funzioni applicazione */
          if ($ruolo = $this->gestionePrivilegiOperatore->operatoreFunzioni($authname)) {
            $account = $this->loginUser($authname, $account_data, 'silfi');

            $this->roleMatchAdd($account, $ruolo);

            $account->save();
          }
        }
        break;
    }

    $this->tempstore->set('redirect', $redirects);
    $this->tempstore->set('single_token', $token);
    // $this->tempstore->set('state', $this->oauth2client->state);

    \Drupal::service('page_cache_kill_switch')->trigger();
    // Redirect to the client that started the authentication.
    return $this->redirect('user.page');
    // $this->oauth2client->redirect(FALSE);
    // return [
    //   '#markup' => 'Redirect failed',
    // ];
  }

  /**
   * Log the user in, creating the account if it doesn't exist yet.
   *
   * @param x509cert $client_certificate
   *   The client certificate.
   *
   * @return \Drupal\user\UserInterface
   *   The registered Drupal user.
   */
  protected function loginUser($authname, array $account_data, $provider) {
    if (!$this->authmap->getUid($authname, $provider)) {
      // $debug = $this->externalauth->loginRegister($authname, $provider, $account_data, null);
      return $this->externalauth->loginRegister($authname, $provider, $account_data, null);
    }
    // $debug = $this->externalauth->login($authname, $provider);
    return $this->externalauth->login($authname, $provider);
  }

  /**
   * Utility function that checks for single or multiple roles.
   *
   * @param string or array $roles
   *   The role(s) to check.
   *
   * @param AccountInterface $account
   *   The user to check
   *
   * @return bool
   *   Whether the account has the role(s)
   *
   */
  private function userHasRoles($roles, AccountInterface $account) {
    // checks if the account has role(s)
    $roles = is_array($roles) ? $roles : [$roles];
    return (bool)count(array_intersect($roles, array_values($account->getRoles())));
  }

  /**
   * Ritorna il valore dell'ente operatore
   *
   * @param string $memberOf
   * @param bool $stage
   * @return string
   */
  private function getEnte(string $memberOf, bool $stage = FALSE): string {
    $pattern = "/OPERATORE_(.*)/";
    $result = preg_match($pattern, $memberOf, $matches);
    $this->logger->debug('Parse ente %data.', ['%data' => '<pre><code>' . print_r($matches, true) . '</code></pre>']);
    if ($result) {
      return $matches[1];
    }
    $pattern = "/OPERATORE_(.*),/";
    $result = preg_match($pattern, $memberOf, $matches);
    $this->logger->debug('Parse ente %data.', ['%data' => '<pre><code>' . print_r($matches, true) . '</code></pre>']);
    if ($result) {
      return $matches[1];
    }
  }

  /**
   * Adds roles to user accounts.
   *
   * @param \Drupal\user\UserInterface $account
   *   The Drupal user to add roles to.
   */
  public function roleMatchAdd(UserInterface $account, array $roles): void {
    $current_user = \Drupal::currentUser();
    $userRoles = $current_user->getRoles();
    // delete all roles
    if (count($userRoles) > 1) {
      unset($userRoles[0]);
      foreach ($userRoles as $key => $role) {
        $account->removeRole($role);
      }
    }
    // Get matching roles based on retrieved LDAP attributes.
    $matching_roles = $this->getMatchingRoles();

    if ($matching_roles) {
      foreach ($matching_roles as $role_id => $role_eval) {
        foreach ($roles as $function) {
          if ($function->funzione == $role_eval) {
            $account->addRole($role_id);
          }
        }
      }
      $account->save();
    }
  }

  /**
   * Get matching user roles to assign to user.
   *
   * Matching roles are based on retrieved LDAP attributes.
   *
   * @return array
   *   List of matching roles to assign to user.
   */
  public function getMatchingRoles(): array {
    $roles = [];
    // Obtain the role map stored. The role map is a concatenated string of
    // rules which, when LDAP attributes on the user match, will add
    // roles to the user.
    // The full role map string, when mapped to the variables below, presents
    // itself thus:
    // $role_id:$key,$op,$value;$key,$op,$value;|$role_id:$key,$op,$value etc.
    if ($rolemap = $this->statusClass->operatorRolePopulation()) {
      foreach (explode('|', $rolemap) as $rolerule) {
        list($role_id, $role_eval) = explode(':', $rolerule, 2);
        $roles[$role_id] = $role_eval;
      }
    }

    return $roles;
  }

  /**
   * Only for debug
   */
  public function debug(Request $request) {
    $this->gestionePrivilegiOperatore->login();

    return [
      // '#markup' => print_r($this->gestionePrivilegiOperatore->triplette()),
    ];
  }
}

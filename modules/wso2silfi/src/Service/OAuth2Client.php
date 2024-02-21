<?php

namespace Drupal\wso2silfi\Service;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\wso2silfi\Helper\Status;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;

/**
 * OAuth2Client service.
 *
 * The class OAuth2Client is used to get authorization from
 * an OAuth2 server.
 *
 * It can use authorization flows: server-side, client-credentials
 * and user-password. The details for each case are passed
 * to the constructor. All the three cases need a client_id,
 * a client_secret, and a token_endpoint. There can be an optional
 * scope as well.
 */
class OAuth2Client implements OAuth2ClientInterface {

  use StringTranslationTrait;

  /**
   * Unique identifier of an OAuth2Client object.
   *
   * @var string|null
   */
  protected $id = NULL;

  /**
   * Unique state identifier of an OAuth2Client object.
   *
   * @var string|null
   */
  public $state = NULL;

  /**
   * Parameters.
   *
   * Associative array of the parameters that are needed
   * by the different types of authorization flows.
   *  - auth_flow :: server-side | client-credentials | user-password
   *  - client_id :: Client ID, as registered on the oauth2 server
   *  - client_secret :: Client secret, as registered on the oauth2 server
   *  - token_endpoint :: something like:
   *       https://oauth2_server.example.org/oauth2/token
   *  - authorization_endpoint :: somethig like:
   *       https://oauth2_server.example.org/oauth2/authorize
   *  - redirect_uri :: something like:
   *       url('oauth2/authorized', array('absolute' => TRUE)) or
   *       https://oauth2_client.example.org/oauth2/authorized
   *  - scope :: requested scopes, separated by a space
   *  - username :: username of the resource owner
   *  - password :: password of the resource owner
   *  - skip-ssl-verification :: Skip verification of the SSL connection
   *  (needed for testing).
   *
   * @var array
   */
  protected $params = [
    'auth_flow' => NULL,
    'agEntityId' => NULL,
    'comEntityId' => NULL,
    'client_id' => NULL,
    'client_secret' => NULL,
    'redirect_uri' => NULL,
    'userinfo_endpoint' => NULL,
    'token_endpoint' => NULL,
    'authorization_endpoint' => NULL,
    'response_type' => NULL,
    'scope' => NULL,
    'state' => NULL,
    'username' => NULL,
    'password' => NULL,
    'skip-ssl-verification' => FALSE,
    'realm' => NULL,
  ];

  /**
   * Associated array that keeps data about the access token.
   *
   * @var array
   */
  protected $token = [
    'access_token' => NULL,
    'expires_in' => NULL,
    'token_type' => NULL,
    'scope' => NULL,
    'refresh_token' => NULL,
    'expiration_time' => NULL,
  ];

  /**
   * The HTTP Request client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The Request Stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The oauth2 client tempstore - acts like $_SESSION.
   *
   * @var \Drupal\user\PrivateTempStore
   */
  protected $tempstore;

  /**
   * I valori della classe Status.
   *
   * @var \Drupal\wso2silfi\Helper\Status
   */
  protected $statusClass;

  protected $access_type;

  protected $endpoint;

  /**
   * Construct an OAuth2Client object.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP Request client.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The Request Stack.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempstore
   *   The user private tempstore - acts like $_SESSION.
   */
  public function __construct(
    ClientInterface $httpClient,
    RequestStack $requestStack,
    PrivateTempStoreFactory $tempstore,
    Status $statusClass
  ) {
    // @todo when https://www.drupal.org/node/2865991 is resolved, use force
    //   start session API rather than setting an arbitrary value directly.
    $request = \Drupal::request();
    $request->getSession()
      ->set('core.tempstore.private', TRUE);

    if (\session_status() == PHP_SESSION_NONE) {
      \session_start();
    }

    $this->httpClient = $httpClient;
    $this->requestStack = $requestStack;
    $this->tempstore = $tempstore->get('wso2silfi');
    $this->statusClass = $statusClass;
    $this->endpoint = $this->statusClass->isStage() ? Status::$stageUrl : Status::$productionUrl;
    $this->state = STATE;
  }

  /**
   * {@inheritdoc}
   */
  public function init($access_type = 'cittadino', $id = NULL) {
    $params = $this->initClient($access_type);
    $this->access_type = $access_type;
    if ($params) {
      $this->params = $params;
    }

    if (!$id) {
      $id = md5($this->params['token_endpoint'] .
        $this->params['client_id'] .
        $this->params['auth_flow']);
    }
    $this->id = $id;
    // Get the token data from the tempstore, if it is stored there.
    $tokens = $this->tempstore->get('token');
    if (isset($tokens[$this->id])) {
      $this->token = $tokens[$this->id] + $this->token;
    }
    $this->tempstore->set('params', $params);
    $this->tempstore->set('id', $this->id);
    $this->tempstore->set('state', $this->state);
  }

  /**
   * {@inheritdoc}
   */
  public function clearToken() {
    $tokens = $this->tempstore->get('token');

    if (isset($tokens[$this->id])) {
      unset($tokens[$this->id]);
      $this->tempstore->set('token', $tokens);
    }

    $this->token = [
      'access_token' => NULL,
      'expires_in' => NULL,
      'token_type' => NULL,
      'scope' => NULL,
      'refresh_token' => NULL,
      'expiration_time' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function getAccessToken($redirect = TRUE) {
    // Check wheather the existing token has expired.
    // We take the expiration time to be shorter by 10 sec
    // in order to account for any delays during the request.
    // Usually a token is valid for 1 hour, so making
    // the expiration time shorter by 10 sec is insignificant.
    // However it should be kept in mind during the tests,
    // where the expiration time is much shorter.
    $expiration_time = $this->token['expiration_time'];
    if ($expiration_time > (time() + 10)) {
      // The existing token can still be used.
      return $this->token['access_token'];
    }

    try {
      // Try to use refresh_token.
      $token = $this->getTokenRefreshToken();
    } catch (\Exception $e) {
      // if (!$this->params['auth_flow']) {
        $this->params = $this->tempstore->get('params');
      // }
      // Get a token.
      if ($redirect) {
        $token = $this->getTokenServerSide();
      } else {
        $this->clearToken();
        return NULL;
      }
    }

    $token['expiration_time'] = \Drupal::time()->getRequestTime() + $token['expires_in'];
    // Store the token (on session as well).
    $this->token = $token;

    $tokens = $this->tempstore->get('token');
    $tokens[$this->id] = $token;
    $this->tempstore->set('token', $tokens);
    $this->tempstore->set('params', $this->params);
    $this->tempstore->set('id', $this->id);

    $token['state'] = $this->state;

    $this->tempstore->set('single_token', $token);
    // Return the token.
    return $token['access_token'];
  }

  /**
   * {@inheritdoc}
   */
  public function setRedirect($state, $redirect = NULL) {
    if ($redirect == NULL) {
      $redirect = [
        'uri' => \Drupal::request()->getRequestUri(),
        'client' => 'wso2silfi',
      ];
    }

    if (!isset($redirect['client'])) {
      $redirect['client'] = 'external';
    }
    // Ensure that an anonymous user has a session created for them, as
    // otherwise subsequent page loads will not be able to retrieve their
    // tempstore data.
    // if (\Drupal::currentUser()->isAnonymous()) {
    //   // @todo when https://www.drupal.org/node/2865991 is resolved, use force
    //   //   start session API rather than setting an arbitrary value directly.
    //   $request = \Drupal::request();
    //   $request->getSession()
    //     ->set('core.tempstore.private', TRUE);
    //   // \session_start();
    // }
    // /** @var \Drupal\Core\TempStore\PrivateTempStore $tempstore */
    // $tempstore = \Drupal::service('tempstore.private')->get('wso2silfi');
    $redirects = $this->tempstore->get('redirect');
    $redirects[$state] = $redirect;
    $this->tempstore->set('redirect', $redirects);

    return;
  }

  /**
   * {@inheritdoc}
   */
  public function redirect($clean = TRUE) {
    if (!$state = \Drupal::service('request_stack')->getCurrentRequest()->get('state')) {
      $messenger = \Drupal::messenger();
      $messenger->addMessage('STATE error', $messenger::TYPE_ERROR);
      return;
    }

    $redirects = $this->tempstore->get('redirect');

    if (!isset($redirects[$state])) {
      $messenger = \Drupal::messenger();
      $messenger->addMessage('REDIRECT error', $messenger::TYPE_ERROR);
      return;
    }

    $redirect = $redirects[$state];

    if ($redirect['client'] != 'wso2silfi') {
      unset($redirects[$state]);
      $this->tempstore->set('redirect', $redirects);

      $params = isset($redirect['params']) ? $redirect['params'] : [];
      $params = $params + \Drupal::request()->query->all();

      $url = Url::fromUri($redirect['uri'], ['query' => $params]);
      $redirect = new RedirectResponse($url->toString());
      $redirect->send();
      exit();
    } else {
      $params = \Drupal::request()->query->all();
      if ($clean) {
        unset($redirects[$state]);
        $this->tempstore->set('redirect', $redirects);
        unset($params['code']);
        unset($params['state']);
      }

      if (isset($redirect['params'])) {
        $params = $redirect['params'] + $params;
      }

      $url = Url::fromUri('internal:' . $redirect['uri'], ['query' => $params]);
      $new_redirect = new RedirectResponse($url->toString());
      $new_redirect->send();
      exit();
    }
  }

  /**
   * Get a new access_token using the refresh_token.
   *
   * This is used for the server-side and user-password
   * flows (not for client-credentials, there is no
   * refresh_token in it).
   *
   * @throws \Exception
   */
  protected function getTokenRefreshToken() {
    if (!$this->token['refresh_token']) {
      throw new \Exception(t('There is no refresh_token.'));
    }

    return $this->getToken([
      'grant_type' => 'refresh_token',
      'refresh_token' => $this->token['refresh_token'],
    ]);
  }

  /**
   * Get an access_token using the server-side (authorization code) flow.
   *
   * This is done in two steps:
   *   - First, a redirection is done to the authentication
   *     endpoint, in order to request an authorization code.
   *   - Second, using this code, an access_token is requested.
   *
   * There are lots of redirects in this case and this part is the most
   * tricky and difficult to understand of the wso2silfi, so let
   * me try to explain how it is done.
   *
   * Suppose that in the controller of the path 'test/xyz'
   * we try to get an access_token:
   *     $client = wso2silfi_load('server-side-test');
   *     $access_token = $client->getAccessToken();
   * or:
   *     $client = new OAuth2\Client(array(
   *         'token_endpoint' => 'https://oauth2_server/oauth2/token',
   *         'client_id' => 'client1',
   *         'client_secret' => 'secret1',
   *         'auth_flow' => 'server-side',
   *         'authorization_endpoint' =>
   *         'https://oauth2_server/oauth2/authorize',
   *         'redirect_uri' => 'https://oauth2_client/oauth2/authorized',
   *       ));
   *     $access_token = $client->getAccessToken();
   *
   * From getAccessToken() we come to this function, getTokenServerSide(),
   * and since there is no $_GET['code'], we redirect to the authentication
   * url, but first we save the current path in the session:
   *   $_SESSION['wso2silfi']['redirect'][$state]['uri'] = 'test/xyz';
   *
   * Once the authentication and authorization is done on the server, we are
   * redirected by the server to the redirect uri: 'oauth2/authorized'.  In
   * the controller of this path we redirect to the saved path 'test/xyz'
   * (since $_SESSION['wso2silfi']['redirect'][$state] exists), passing
   * along the query parameters sent by the server (which include 'code',
   * 'state', and maybe other parameters as well.)
   *
   * Now the code: $access_token = $client->getAccessToken(); is
   * called again and we come back for a second time to the function
   * getTokenServerSide(). However this time we do have a
   * $_GET['code'], so we get a token from the server and return it.
   *
   * Inside the function getAccessToken() we save the returned token in
   * session and then, since $_SESSION['wso2silfi']['redirect'][$state]
   * exists, we delete it and make another redirect to 'test/xyz'.  This third
   * redirect is in order to have in browser the original url, because from
   * the last redirect we have something like this:
   * 'test/xyz?code=8557&state=3d7dh3&....'
   *
   * We come again for a third time to the code
   *     $access_token = $client->getAccessToken();
   * But this time we have a valid token already saved in session,
   * so the $client can find and return it without having to redirect etc.
   *
   * @throws \Exception
   */
  protected function getTokenServerSide() {
    if (!$this->requestStack->getCurrentRequest()->get('code')) {
      $url = $this->getAuthenticationUrl();
      $url = Url::fromUri($url);
      $redirect = new RedirectResponse($url->toString());
      $redirect->send();
      exit();
    }

    // Check the query parameter 'state'.
    $state = $this->requestStack->getCurrentRequest()->get('state');
    $redirects = $this->tempstore->get('redirect');
    if (!$state || !isset($redirects[$state])) {
      throw new \Exception(t("State query parameter does not match"));
    }

    // Get and return a token.
    return $this->getToken([
      'grant_type' => 'authorization_code',
      'code' => $this->requestStack->getCurrentRequest()->get('code'),
      'redirect_uri' => $this->params['redirect_uri'],
      'client_id'     => $this->params['client_id'],
      'client_secret' => $this->params['client_secret'],
      'token_endpoint' => $this->params['token_endpoint'],
      // 'realm'     => $this->params['realm'],
    ]);
  }

  /**
   * Return the authentication url (used in case of the server-side flow).
   */
  protected function getAuthenticationUrl() {
    // $state = md5(uniqid(rand(), TRUE));
    $comEntityId = '';
    if ($this->params['comEntityId']) {
      $comEntityId = $this->params['comEntityId'];
    }
    $query_params = [
      'response_type' => 'code',
      'agEntityId' => $this->params['agEntityId'],
      'comEntityId' => $comEntityId,
      'client_id' => $this->params['client_id'],
      // 'client_secret' => $this->params['client_secret'],
      'redirect_uri' => $this->params['redirect_uri'],
      'state' => $this->state,
    ];
    if (isset($this->params['isAuthSilfi'])) {
      $query_params['isAuthSilfi'] = $this->params['isAuthSilfi'];
    }


    if ($this->params['scope']) {
      $query_params['scope'] = $this->params['scope'];
    }

    $serverUrl = $this->statusClass->authorizeUrlWso2();

    return Url::fromUri($serverUrl, ['query' => $query_params])->toString();
  }

  /**
   * Get and return an access token for the grant_type given in $params.
   */
  protected function getToken($data) {
    if (array_key_exists('scope', $data) && $data['scope'] === NULL) {
      unset($data['scope']);
    }

    $options = [
      'form_params' => $data,
      'headers' => [
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
    ];
    if ($this->params['skip-ssl-verification']) {
      $options['verify'] = FALSE;
    }
    unset($options['form_params']['token_endpoint']);

    try {
      $response = $this->httpClient->request('POST', $data['token_endpoint'], $options);
      $response_data = (string) $response->getBody();
    } catch (RequestException $e) {
      global $base_url;
      $options['timestamp'] = time();
      //An error happened.
      \Drupal::messenger()->addError($e->getMessage());
      \Drupal::logger('wso2silfi')->error('getToken %data.', ['%data' => '<pre><code>' . print_r($options, true) . '</code></pre>']);
      \Drupal::logger('wso2silfi')->error('Request Token failed with HTTP error %error.', ['%error' => $e->getMessage()]);

      $response = new RedirectResponse($base_url);
      $response->send();
      return new Response();
    }

    if (empty($response_data)) {
      throw new \Exception(
        $this->t(
          "Failed to get an access token of grant_type @grant_type.\nError: @result_error",
          [
            '@grant_type' => $data['grant_type'],
          ]
        )
      );
    }

    $serializer = new Serializer([new GetSetMethodNormalizer()], ['json' => new JsonEncoder()]);

    $token = $serializer->decode($response_data, 'json');

    if (!isset($token['expiration_time'])) {
      // Some providers do not return an 'expires_in' value, so we
      // set a default of an hour. If the token expires dies within that time,
      // the system will request a new token automatically.
      $token['expiration_time'] = \Drupal::time()->getRequestTime() + 60;
    }

    $token['state'] = $this->state;

    return $token;
  }

  /**
   *
   */
  public function getTokenInfo() {
    $token = $this->token;
    $options = [
      'headers' => [
        'Authorization' => $token['token_type'] . ' ' . $token['access_token'],
        'refresh_token' => $token['refresh_token'],
        'scope' => 'openid',
      ],
    ];
    try {
      $response = $this->httpClient->request('GET', $this->params['userinfo_endpoint'], $options);
      $response_data = (string) $response->getBody();
    } catch (RequestException $e) {
      global $base_url;
      //An error happened.
      \Drupal::messenger()->addError($e->getMessage());
      \Drupal::logger('wso2silfi')->error('getToken %data.', ['%data' => '<pre><code>' . print_r($options, true) . '</code></pre>']);
      \Drupal::logger('wso2silfi')->error('Request Token failed with HTTP error %error.', ['%error' => $e->getMessage()]);

      $response = new RedirectResponse($base_url);
      $response->send();
      return new Response();
    }

    $serializer = new Serializer([new GetSetMethodNormalizer()], ['json' => new JsonEncoder()]);

    $token_info = $serializer->decode($response_data, 'json');

    return $token_info;
  }

  /**
   * Creai i parametri del client oauth2
   *
   * @param string $access_type
   * @return array
   */
  private function initClient(string $access_type): array {
    global $base_url;
    switch ($access_type) {
      case 'cittadino':
        $client_id = $this->statusClass->clientIdWso2();
        $client_secret = $this->statusClass->clientSecretWso2();
        $agEntityId = $this->statusClass->agEntityIdWso2();
        $oauth2_config['comEntityId'] = $this->statusClass->comEntityIdWso2();
        break;

      case 'operatore':
        $client_id = $this->statusClass->operatorClientIdWso2();
        $client_secret = $this->statusClass->operatorClientSecretWso2();
        $agEntityId = $this->statusClass->operatorAgEntityIdWso2();
        $oauth2_config['isAuthSilfi'] = 'yes';
        break;
    }
    $authorization_endpoint = $this->statusClass->authorizeUrlWso2();
    $token_endpoint = $this->statusClass->tokenUrlWso2();
    $userinfo_endpoint = $this->statusClass->userInfoUrlWso2();
    $redirect_url = new Url('wso2silfi.authorized');

    $oauth2_config += [
      'authorization_endpoint' => $authorization_endpoint,
      'token_endpoint' => $token_endpoint,
      'userinfo_endpoint' => $userinfo_endpoint,
      'auth_flow' => 'server-side',
      'agEntityId' => $agEntityId,
      'client_id' => $client_id,
      'client_secret' => $client_secret,
      'redirect_uri' => $base_url . $redirect_url->toString(),
      'response_type' => 'code',
      'scope' => 'openid',
      // 'state' => STATE,
      'skip-ssl-verification' => $this->statusClass->skipSslVerification(),
    ];

    return $oauth2_config;
  }
}

<?php

namespace Drupal\wso2silfi\Service;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\wso2silfi\Helper\Status;

/**
 * Class GestionePrivilegiOperatori.
 */
class GestionePrivilegiOperatori {

  use LoggerChannelTrait;

  /**
   * The http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * I valori della classe Status.
   *
   * @var \Drupal\wso2silfi\Helper\Status
   */
  protected $statusClass;

  protected $tokenJWT;

  protected $endpoint;

  /**
   * {@inheritdoc}
   *
   * @param Client $http_client
   *   A GuzzleHttp client.
   *
   * @param MessengerInterface $messenger
   *   The messenger factory.
   *
   * @param Status $statusClass
   *   The statusClass object.
   *
   */
  public function __construct(
    Client $http_client,
    MessengerInterface $messenger,
    Status $statusClass
  ) {
    $this->httpClient = $http_client;
    $this->messenger = $messenger;
    $this->statusClass = $statusClass;
    $this->endpoint = $this->statusClass->isStage() ? Status::$stageUrlBpo : Status::$productionUrlBpo;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('messenger'),
      $container->get('wso2silfi.status')
    );
  }

  /**
   * Per richiedere un token JWT di autenticazione
   *
   * @return bool
   */
  public function login() : bool {
    $path = '/login';
    $body = $this->statusClass->credentialsBpo();
    $options = [
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'json' => $body,
    ];
    if ($this->statusClass->skipSslVerification()) {
      $options['verify'] = FALSE;
    }
    if ($response = $this->call('POST', $this->endpoint . $path, $options)) {
      $this->tokenJWT = $response;
      return TRUE;
    }
    else {
      throw new \Exception('Errore nel POST login: ' . $response->error);
      $this->messenger->addError('Errore nel POST login: ' . $response->error);
      $this->getLogger('wso2silfi')->error('Errore nel POST login: %error.', ['%error' => $response->error]);
    }
    return FALSE;
  }

  /**
   * Metodo per recuperare la lista delle funzioni applicative associate all’operatore dell’ente
   *
   * @param string $operator
   * @return mixed
   */
  public function operatoreFunzioni(string $operator) : mixed {
    $path = '/1.0/operatore-funzioni/' . $operator . '/' . $this->statusClass->operatorApp() . '/' . $this->statusClass->operatorEnte();
    return $this->callMethod('listaFunzioneOperatore', $path);
  }

  /**
   * Metodo per verificare se un operatore dell’ente è abilitato per una data funzione applicativa
   *
   * @param string $operator
   * @param string $function
   * @return void
   */
  public function operatoreCheck(string $operator, string $function) : mixed {
    $path = '/1.0/operatore-check/' . $operator . '/' . $this->statusClass->operatorApp() . '/' . $function . '/' . $this->statusClass->operatorEnte();
    return $this->callMethod('operatoreAbilitato', $path);
  }

  /**
   * Metodo per recuperare la lista delle triplette definite per l’ente
   *
   * @return array
   */
  public function triplette() : array {
    $data = [];
    $path = '/1.0/triplette/' . $this->statusClass->operatorEnte();
    if ($this->callMethod('listaTriplette', $path)) {
      $data = $this->callMethod('listaTriplette', $path);
    }
    return $data;
  }

  /**
   * Metodo per recuperare la lista delle triplette abilitate per un operatore dell’ente
   *
   * @param string $operator
   * @return void
   */
  public function operatoreTriplette(string $operator) : void {
    $path = '/1.0/operatore-triplette/' . $operator . '/' . $this->statusClass->operatorApp() . '/' . $this->statusClass->operatorEnte();
    $this->callMethod('listaTriplettaOperatore', $path);
  }

  /**
   * Restituisce il tokenJWT di autenticazione su baseprivilegioperatore
   *
   * @return string
   */
  public function getTokenJwt() : string {
    return $this->tokenJWT;
  }

  /**
   * Chiama il metodo specifico
   *
   * @param string $method
   * @param string $path
   * @return mixed
   */
  private function callMethod(string $method, string $path) : mixed {
    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->tokenJWT,
      ],
    ];
    if ($response = $this->call('GET', $this->endpoint . $path, $options)) {
      $response = json_decode($response);
      if ($response->esito === 'SUCCESS') {
        return $response->$method;
      }
      else {
        throw new \Exception("Errore $method: " . $response->messaggio);
        $this->messenger->addError("Errore $method: " . $response->messaggio);
        $this->getLogger('wso2silfi')->error("Errore $method:  %error.", ['%error' => $response->messaggio]);
      }
    }
    return FALSE;
  }

  /**
   * HTTP call with Guzzle
   *
   * @param string $method
   * @param string $url
   * @param array $options
   * @return void
   */
  private function call($method, $url, $options = []) {
    $response_data = false;
    try {
      $response = $this->httpClient->request($method, $url, $options);
      $response_data = (string) $response->getBody();
    }
    // catch (GuzzleException $error) {
    //   // Get the original response
    //   $response = $error->getResponse();
    //   // Get the info returned from the remote server.
    //   $response_info = $response->getBody()->getContents();
    //   // Using FormattableMarkup allows for the use of <pre/> tags, giving a more readable log item.
    //   $message = new FormattableMarkup('API connection error. Error details are as follows:<pre>@response</pre>', ['@response' => print_r(json_decode($response_info), TRUE)]);
    //   // Log the error
    //   watchdog_exception('Remote API Connection', $error, $message);
    // }

    catch (RequestException $e) {
      global $base_url;
      //An error happened.
      $this->messenger->addError(new FormattableMarkup($e->getMessage()));
      $this->getLogger('wso2silfi')->error('call data %data.', ['%data' => '<pre><code>' . print_r($options, true) .'</code></pre>']);
      $this->getLogger('wso2silfi')->error('Request failed with HTTP error %error.', ['%error' => $e->getMessage()]);

      $response = new RedirectResponse($base_url);
      $response->send();
      return new Response();
    }
    return $response_data;
  }

}

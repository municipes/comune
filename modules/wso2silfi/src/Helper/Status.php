<?php

namespace Drupal\wso2silfi\Helper;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Status.
 *
 * Gestisce la configurazione e lo stato del servizio WSO2 SILFI.
 */
class Status {

  /**
   * URL per l'ambiente di staging.
   *
   * @var string
   */
  public static $stageUrl = 'https://id-staging.055055.it:9443';

  /**
   * URL per l'ambiente di produzione.
   *
   * @var string
   */
  public static $productionUrl = 'https://id.055055.it:9443';

  /**
   * Path OAuth.
   *
   * @var string
   */
  public static $oauthPath = '/oauth2';

  /**
   * URL per l'API di base privilegi operatori in staging.
   *
   * @var string
   */
  public static $stageUrlBpo = 'http://baseprivilegioperatori-staging.cst:8080/baseprivilegioperatore/api';

  /**
   * URL per l'API di base privilegi operatori in produzione.
   *
   * @var string
   */
  public static $productionUrlBpo = 'http://baseprivilegioperatore.cst:8080/baseprivilegioperatore/api';

  /**
   * Configurazione del modulo.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Indica se il modulo è abilitato.
   *
   * @var bool
   */
  protected $isEnabled;

  /**
   * Indica se siamo in ambiente di staging.
   *
   * @var bool
   */
  protected $stage;

  /**
   * Endpoint WSO2 da utilizzare.
   *
   * @var string
   */
  protected $endpoint;

  /**
   * Endpoint BPO da utilizzare.
   *
   * @var string
   */
  protected $endpointBpo;

  /**
   * Costruttore della classe Status.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Factory per l'accesso alla configurazione.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('wso2silfi.settings');
    $this->isEnabled = (bool) $this->config->get('general.wso2silfi_enabled');
    $this->stage = (bool) $this->config->get('general.stage');
    $this->endpoint = $this->stage ? self::$stageUrl : self::$productionUrl;
    $this->endpointBpo = $this->stage ? self::$stageUrlBpo : self::$productionUrlBpo;
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
   * Verifica se siamo in ambiente di staging.
   *
   * @return bool
   *   TRUE se siamo in ambiente di staging, FALSE altrimenti.
   */
  public function isStage(): bool {
    return $this->stage;
  }

  /**
   * Verifica se il modulo è abilitato.
   *
   * @return bool
   *   TRUE se il modulo è abilitato, FALSE altrimenti.
   */
  public function isEnabled(): bool {
    return $this->isEnabled;
  }

  /**
   * Restituisce il client ID per l'autenticazione cittadino.
   *
   * @return string
   *   Il client ID.
   */
  public function clientIdWso2(): string {
    return trim($this->config->get('citizen.client_id') ?? '');
  }

  /**
   * Restituisce il client secret per l'autenticazione cittadino.
   *
   * @return string
   *   Il client secret.
   */
  public function clientSecretWso2(): string {
    return trim($this->config->get('citizen.client_secret') ?? '');
  }

  /**
   * Restituisce l'agEntityId per l'autenticazione.
   *
   * @return string
   *   L'agEntityId.
   */
  public function agEntityIdWso2(): string {
    return trim($this->config->get('general.agEntityId') ?? '');
  }

  /**
   * Restituisce il comEntityId per l'autenticazione.
   *
   * @return string
   *   Il comEntityId.
   */
  public function comEntityIdWso2(): string {
    return trim($this->config->get('general.comEntityId') ?? '');
  }

  /**
   * Restituisce l'URL di logout.
   *
   * @return string
   *   L'URL di logout.
   */
  public function logoutUrlWso2(): string {
    return $this->endpoint . '/oidc/logout';
  }

  /**
   * Restituisce l'URL di autorizzazione.
   *
   * @return string
   *   L'URL di autorizzazione.
   */
  public function authorizeUrlWso2(): string {
    return $this->endpoint . self::$oauthPath . '/authorize';
  }

  /**
   * Restituisce l'URL per ottenere i token.
   *
   * @return string
   *   L'URL per i token.
   */
  public function tokenUrlWso2(): string {
    return $this->endpoint . self::$oauthPath . '/token';
  }

  /**
   * Restituisce l'URL per ottenere le informazioni utente.
   *
   * @return string
   *   L'URL per le informazioni utente.
   */
  public function userInfoUrlWso2(): string {
    return $this->endpoint . self::$oauthPath . '/userinfo';
  }

  /**
   * Verifica se saltare la verifica SSL.
   *
   * @return bool
   *   TRUE se saltare la verifica SSL, FALSE altrimenti.
   */
  public function skipSslVerification(): bool {
    return (bool) $this->config->get('general.skip-ssl-verification');
  }

  /**
   * Restituisce i ruoli da escludere per cittadini.
   *
   * @return array|string
   *   I ruoli da escludere.
   */
  public function citizenRoleToExclude() {
    return $this->config->get('citizen.roletoexclude') ?? [];
  }

  /**
   * Restituisce il ruolo per cittadini.
   *
   * @return string
   *   Il ruolo per cittadini.
   */
  public function citizenRole(): string {
    return $this->config->get('citizen.role') ?? 'none';
  }

  /**
   * Restituisce le credenziali per Base Privilegi Operatore.
   *
   * @return array
   *   Le credenziali.
   */
  public function credentialsBpo(): array {
    return [
      'username' => $this->config->get('operator.username') ?? '',
      'password' => $this->config->get('operator.password') ?? '',
    ];
  }

  /**
   * Restituisce il client ID per operatori.
   *
   * @return string
   *   Il client ID per operatori.
   */
  public function operatorClientIdWso2(): string {
    return trim($this->config->get('operator.client_id') ?? '');
  }

  /**
   * Restituisce il client secret per operatori.
   *
   * @return string
   *   Il client secret per operatori.
   */
  public function operatorClientSecretWso2(): string {
    return trim($this->config->get('operator.client_secret') ?? '');
  }

  /**
   * Restituisce l'agEntityId per operatori.
   *
   * @return string
   *   L'agEntityId per operatori, o stringa vuota se non configurato.
   */
  public function operatorAgEntityIdWso2(): string {
    return trim($this->config->get('operator.agEntityId') ?? '');
  }

  /**
   * Restituisce l'app dell'operatore.
   *
   * @return string
   *   L'app dell'operatore.
   */
  public function operatorApp(): string {
    return trim($this->config->get('operator.app') ?? '');
  }

  /**
   * Restituisce l'ente dell'operatore.
   *
   * @return string
   *   L'ente dell'operatore.
   */
  public function operatorEnte(): string {
    return trim($this->config->get('operator.ente') ?? '');
  }

  /**
   * Restituisce le regole di popolazione dei ruoli per operatori.
   *
   * @return string
   *   Le regole di popolazione dei ruoli.
   */
  public function operatorRolePopulation(): string {
    return trim($this->config->get('operator.rolepopulation') ?? '');
  }

}

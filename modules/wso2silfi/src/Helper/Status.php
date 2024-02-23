<?php

namespace Drupal\wso2silfi\Helper;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CheckUserFieldExist.
 */
class Status {

  public static $stageUrl = 'https://id-staging.055055.it:9443/oauth2';

  public static $productionUrl = 'https://id.055055.it:9443/oauth2';

  public static $stageUrlBpo = 'http://baseprivilegioperatori-staging.cst:8080/baseprivilegioperatore/api';

  public static $productionUrlBpo = 'http://baseprivilegioperatore.cst:8080/baseprivilegioperatore/api';

  /**
   * A configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  protected $isEnabled;

  protected $stage;

  protected $endpoint;

  protected $endpointBpo;

  /**
   * {@inheritdoc}
   *
   * @param Client $http_client
   *   A GuzzleHttp client.
   *
   * @param ConfigFactoryInterface $config_factory
   *   The configuration factory.
   *
   */
  public function __construct(
    ConfigFactoryInterface $config_factory
  ) {
    $this->config = $config_factory->get('wso2silfi.settings');
    $this->isEnabled = $this->config->get('general.wso2silfi_enabled');
    $this->stage = $this->config->get('general.stage');
    $this->endpoint = $this->stage ? self::$stageUrl : self::$productionUrl;
    $this->endpointBpo = $this->stage ? self::$stageUrl : self::$productionUrl;
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
   * Restituisce valore bool se siamo o meno in ambiente di stage
   *
   * @return bool
   */
  public function isStage() : bool {
    return $this->stage;
  }

  public function isEnabled() : bool {
    return $this->isEnabled;
  }

  public function clientIdWso2() : string {
    return trim($this->config->get('citizen.client_id'));
  }

  public function clientSecretWso2() : string {
    return trim($this->config->get('citizen.client_secret'));
  }

  public function agEntityIdWso2() : string {
    return trim($this->config->get('general.agEntityId'));
  }

  public function comEntityIdWso2() : string {
    return trim($this->config->get('general.comEntityId'));
  }

  public function logoutUrlWso2() : string {
    return trim($this->endpoint) . '/oidc/logout';
  }

  public function authorizeUrlWso2() : string {
    return trim($this->endpoint) . '/authorize';
  }

  public function tokenUrlWso2() : string {
    return trim($this->endpoint) . '/token';
  }

  public function userInfoUrlWso2() : string {
    return trim($this->endpoint) . '/userinfo';
  }

  public function skipSslVerification() : bool {
    return $this->config->get('general.skip-ssl-verification');
  }

  public function citizenRoleToExclude() {
    return $this->config->get('citizen.roletoexclude');
  }

  public function citizenRole() {
    return $this->config->get('citizen.role');
  }

  public function credentialsBpo() : array {
    return [
      'username' => $this->config->get('operator.username'),
      'password' => $this->config->get('operator.password'),
    ];
  }

  public function operatorClientIdWso2() {
    return trim($this->config->get('operator.client_id'));
  }

  public function operatorClientSecretWso2() {
    return trim($this->config->get('operator.client_secret'));
  }

  public function operatorAgEntityIdWso2() {
    if ($this->config->get('operator.agEntityId')) {
      return trim($this->config->get('operator.agEntityId'));
    }
  }

  public function operatorApp() : string {
    return trim($this->config->get('operator.app'));
  }

  public function operatorEnte() : string {
    return trim($this->config->get('operator.ente'));
  }

  public function operatorRolePopulation() : string {
    return trim($this->config->get('operator.rolepopulation'));
  }

}

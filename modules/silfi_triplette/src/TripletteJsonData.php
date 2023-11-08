<?php

namespace Drupal\silfi_triplette;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Utility\Error;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use GuzzleHttp\ClientInterface;
use Guzzle\Exception\TransferException;
use Guzzle\Exception\RequestException;

/**
 * Triplette client for Json data.
 */
class TripletteJsonData  {

  /**
   * Config Factory service object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs a Triplette object.
   */
  public function __construct(ConfigFactoryInterface $configFactory, ClientInterface $http_client) {
    $this->configFactory = $configFactory;
    $this->httpClient = $http_client;
  }

  public function getTripletteData($test = FALSE) {
    return $this->getCountedJsonElements($test);
  }

  /**
   * Count triplette elements.
   *
   * @return int
   */
  public function countJsonData(): int {
    $data = $this->getJsonData(1);
    return (int)$data['totalElements'];
  }

  /**
   * Retrive all elements.
   *
   * @return array
   */
  public function getCountedJsonElements($test = FALSE): array {
    $count = $this->countJsonData();
    if ($test) {
      $count = $test;
    }
    return $this->getJsonData($count);
  }

  /**
   * Call Json endpoint.
   *
   * @param int $size
   * @return array
   */
  private function getJsonData(int $size = 1) {
    try {
      $baseUrl = $this->setting('silfi_triplette_url');
    } catch (\Throwable $e) {
      echo 'Caught exception: ',  $e->getMessage(), "\n";
      die();
    }

    try {
      $institutionCode = $this->setting('silfi_triplette_codice_ente');
    } catch (\Throwable $e) {
      echo 'Caught exception: ',  $e->getMessage(), "\n";
      die();
    }

    try {
      $request = $this->httpClient->get($baseUrl, [
        'query' => [
          'codiceEnte' => $institutionCode,
          'size' => $size,
        ]
      ]);
    }
    catch (TransferException $e) {
      Error::logException('silfi_triplette', $e->getMessage());
    }

    if ($request->getStatusCode() != 200) {
      return [];
    }

    return json_decode($request->getBody(), TRUE);
  }

  /**
   * Helper method to access the settings of this module.
   *
   * @param string $key
   *   The key of the configuration.
   *
   * @return string
   */
  protected function setting($key) {
    $setting = $this->configFactory->get('silfi_triplette.settings')->get($key);
    if (!$setting) {
      throw new \Exception('Configurazione mancante.');
    }

    return $setting;
  }

}

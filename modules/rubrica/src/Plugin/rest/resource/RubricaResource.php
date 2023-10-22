<?php

namespace Drupal\rubrica\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\rubrica\Helper\EntityQueries;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Rubrica Resource
 *
 * @RestResource(
 *   id = "rubrica_resource",
 *   label = @Translation("Rubrica Resource"),
 *   uri_paths = {
 *     "canonical" = "/rest/rubrica/api/v1/get/all"
 *   }
 * )
 */
class RubricaResource extends ResourceBase {

  /**
   * The entity type manager
   *
   * @var \Drupal\rubrica\Helper\EntityQueries
   */
  protected $entityQueries;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    EntityQueries $entityQueries) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityQueries = $entityQueries;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('rubrica.entity_queries')
    );
  }

  /**
   * Responds to entity GET requests.
   * @return \Drupal\rest\ResourceResponse
   */
  public function get() {
    // $data = ['message' => 'Hello, this is a rest service'];
    $data = $this->entityQueries->getData();
    $response = new ResourceResponse($data);
    // In order to generate fresh result every time (without clearing
    // the cache), you need to invalidate the cache.
    $response->addCacheableDependency($data);
    return $response;
  }
}

<?php

namespace Drupal\rubrica\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\rubrica\Helper\TemplateBuilder;
use Drupal\rubrica\Helper\RicercaPersonaUo;
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
   * The template builder
   *
   * @var \Drupal\rubrica\Helper\TemplateBuilder
   */
  protected $templateBuilder;

  /**
   * The persona search
   *
   * @var \Drupal\rubrica\Helper\RicercaPersonaUo
   */
  protected $ricercaPersonaUo;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    TemplateBuilder $templateBuilder,
    RicercaPersonaUo $ricercaPersonaUo
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->templateBuilder = $templateBuilder;
    $this->ricercaPersonaUo = $ricercaPersonaUo;
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
      $container->get('rubrica.templatebuilder'),
      $container->get('rubrica.ricercapersonauo')
    );
  }

  /**
   * Responds to entity GET requests.
   * @return \Drupal\rest\ResourceResponse
   */
  public function get() {
    // $data = ['message' => 'Hello, this is a rest service'];
    $data = $this->getData();
    $response = new ResourceResponse($data);
    // In order to generate fresh result every time (without clearing
    // the cache), you need to invalidate the cache.
    $response->addCacheableDependency($data);
    return $response;
  }

  /**
   * Get data from db
   *
   * @return array
   */
  private function getData(): array {
    $data['items'] = [];
    // Execute the query.
    if ($nids = $this->ricercaPersonaUo->queryByFields()) {
      // Load the nodes with the given NIDs.
      if ($nodes = $this->templateBuilder->loadNodes($nids, TRUE)) {
        $data['items'] = $this->templateBuilder->createArrays($nodes, TRUE);
      }
    }
    return $data;
  }
}

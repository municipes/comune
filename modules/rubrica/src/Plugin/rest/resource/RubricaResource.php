<?php

namespace Drupal\rubrica\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\rest\Attribute\RestResource;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\rubrica\Helper\RicercaPersonaUo;
use Drupal\rubrica\Helper\TemplateBuilder;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a Rubrica Resource.
 */
#[RestResource(
  id: 'rubrica_resource',
  label: new TranslatableMarkup('Rubrica Resource'),
  uri_paths: [
    'canonical' => '/rest/rubrica/api/v1/get/all',
  ],
)]
class RubricaResource extends ResourceBase {

  /**
   * The template builder.
   *
   * @var \Drupal\rubrica\Helper\TemplateBuilder
   */
  protected $templateBuilder;

  /**
   * The persona search helper.
   *
   * @var \Drupal\rubrica\Helper\RicercaPersonaUo
   */
  protected $ricercaPersonaUo;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

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
    RicercaPersonaUo $ricercaPersonaUo,
    RequestStack $requestStack,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->templateBuilder = $templateBuilder;
    $this->ricercaPersonaUo = $ricercaPersonaUo;
    $this->requestStack = $requestStack;
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
      $container->get('rubrica.ricercapersonauo'),
      $container->get('request_stack')
    );
  }

  /**
   * Responds to entity GET requests.
   *
   * Accepts optional query parameter ?callcenter=true to also include
   * persona nodes in the 'solo_contact_center' moderation state.
   *
   * @return \Drupal\rest\ResourceResponse
   *   La risposta JSON con i dati della rubrica.
   */
  public function get() {
    $callcenter = $this->requestStack->getCurrentRequest()->query->get('callcenter') === 'true';
    $data = $this->getData($callcenter);
    $response = new ResourceResponse($data);
    $cacheMeta = new CacheableMetadata();
    $cacheMeta->setCacheTags([
      'node_list:persona',
      'node_list:unita_organizzativa',
      'node_list:incarico',
      'node_list:punto_di_contatto',
      // Gli indirizzi arrivano da nodi luogo referenziati e i tipi di
      // contatto da taxonomy term: senza questi tag resterebbero stantii
      // (emendamento post-review task 5).
      'node_list:luogo',
      'taxonomy_term_list',
    ]);
    $cacheMeta->setCacheContexts(['url.query_args:callcenter']);
    $response->addCacheableDependency($cacheMeta);
    return $response;
  }

  /**
   * Raccoglie i dati della rubrica dal database.
   *
   * @param bool $callcenter
   *   Se TRUE include anche le persona in stato solo_contact_center.
   *
   * @return array
   *   Array con la chiave 'items' contenente i risultati.
   */
  private function getData(bool $callcenter = FALSE): array {
    $data['items'] = [];
    if ($nids = $this->ricercaPersonaUo->queryByFields('', '', 0, $callcenter)) {
      if ($nodes = $this->templateBuilder->loadNodes($nids, TRUE)) {
        $data['items'] = $this->templateBuilder->createArrays($nodes, TRUE, $callcenter);
      }
    }
    return $data;
  }

}

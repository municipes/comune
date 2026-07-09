<?php

namespace Drupal\rubrica\Helper;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\ParseMode\ParseModePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides entity queries for search form.
 */
class FullSearch {
  use DependencySerializationTrait;

  /**
   * The search by field manager.
   *
   * @var \Drupal\rubrica\Helper\TemplateBuilder
   */
  protected $templateBuilder;

  /**
   * The Search API parse mode plugin manager.
   *
   * @var \Drupal\search_api\ParseMode\ParseModePluginManager
   */
  protected ParseModePluginManager $parseModeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(TemplateBuilder $templateBuilder, ParseModePluginManager $parseModeManager) {
    $this->templateBuilder = $templateBuilder;
    $this->parseModeManager = $parseModeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('rubrica.templatebuilder'),
      $container->get('plugin.manager.search_api.parse_mode')
    );
  }

  /**
   * Ricerca fulltext tramite Search API.
   *
   * @param string|null $keys
   *   Parole chiave da cercare.
   *
   * @return mixed
   *   Array con i risultati della ricerca, o NULL se nessun risultato.
   */
  public function searchapiQuery(?string $keys = NULL): mixed {
    $form = NULL;
    $index = Index::load('rubrica');
    $query = $index->query();

    // Change the parse mode for the search.
    $parse_mode = $this->parseModeManager->createInstance('direct');
    $parse_mode->setConjunction('AND');
    $query->setParseMode($parse_mode);

    // Set fulltext search keywords and fields.
    $query->keys($keys);
    $query->setFulltextFields(['field_persona_1', 'field_cognome', 'field_nome', 'title']);

    // Set additional conditions.
    $query->addCondition('status', 1);
    $query->range(0, 10);
    $query->sort('search_api_relevance', 'DESC');

    // Set one or more tags for the query.
    // @see hook_search_api_query_TAG_alter()
    // @see hook_search_api_results_TAG_alter()
    $query->addTag('rubrica_search');

    // Execute the search.
    $results = $query->execute();
    $entities = [];
    foreach ($results->getResultItems() as $item) {
      $entity = $item->getOriginalObject()->getEntity();
      $entities[$entity->id()] = $entity;
    }

    if ($entities) {
      $items = $this->templateBuilder->createArrays($entities);
      $build = $this->templateBuilder->createBuildArray($items);

      $form['search_results']['result'][] = $build;
    }

    return $form;
  }

}

<?php

namespace Drupal\rubrica\Helper;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\search_api\Entity\Index;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\rubrica\Helper\TemplateBuilder;

/**
 * Provides entity queries for search form.
 */
class FullSearch {
  use DependencySerializationTrait;

  /**
   * The search by field manager
   *
   * @var \Drupal\rubrica\Helper\TemplateBuilder
   */
  protected $templateBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct(TemplateBuilder $templateBuilder) {
    $this->templateBuilder = $templateBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('rubrica.templatebuilder')
    );
  }

  /**
   * Ricerca fulltext tramite Search API
   *
   * @param string $keys
   * @return mixed
   */
  public function searchapiQuery(string $keys = null): mixed {
    $form = null;
    $index = Index::load('rubrica');
    $query = $index->query();

    // Change the parse mode for the search.
    $parse_mode = \Drupal::service('plugin.manager.search_api.parse_mode')
      ->createInstance('direct');
    $parse_mode->setConjunction('AND');
    $query->setParseMode($parse_mode);

    // Set fulltext search keywords and fields.
    $query->keys($keys);
    $query->setFulltextFields(['field_persona_1', 'rendered_item']);

    // Set additional conditions.
    $query->addCondition('status', 1);
    // ->addCondition('author', 1, '<>');

    // Add more complex conditions.
    // (In this case, a condition for a specific datasource).
    // $time = \Drupal::service('datetime.time')->getRequestTime();
    // $conditions = $query->createConditionGroup('OR');
    // $conditions->addCondition('search_api_datasource', 'entity:node', '=');
    // // ->addCondition('created', $time - 7 * 24 * 3600, '>=');
    // $query->addConditionGroup($conditions);

    // Restrict the search to specific languages.
    // $query->setLanguages(['de', 'it']);

    // Do paging.
    $query->range(0, 10);

    // Add sorting.
    $query->sort('search_api_relevance', 'DESC');

    // Set one or more tags for the query.
    // @see hook_search_api_query_TAG_alter()
    // @see hook_search_api_results_TAG_alter()
    $query->addTag('rubrica_search');

    // Execute the search.
    $results = $query->execute();
    // $count = $results->getResultCount();
    $items = $results->getResultItems();
    foreach ($items as $key => $item) {
      $entity = $item->getOriginalObject()->getEntity();
      $entities[$entity->id()] = $entity;
      $types[] = $entity->bundle();
    }

    if ($entities) {

    }

    return $form;
  }

}

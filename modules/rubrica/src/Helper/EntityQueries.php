<?php

namespace Drupal\rubrica\Helper;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\search_api\Entity\Index;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * Provides entity queries for search form.
 */
class EntityQueries {
  use DependencySerializationTrait;


  /**
   * The entity type manager
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Search with form fields.
   *
   * @param string $firstName
   * @param string $lastName
   * @param int $office
   * @param string $bundle
   * @return mixed
   */
  public function searchByFields(
    string $firstName = '',
    string $lastName = '',
    int $office = 0,
    string $bundle = 'incarico'
  ): mixed {
    $form = null;
    // Execute the query.
    if ($nids = $this->queryByFields()) {
      // Load the nodes with the given NIDs.
      // Get the node storage.
      $nodeStorage = $this->entityTypeManager->getStorage('node');
      if ($nodes = $nodeStorage->loadMultiple($nids)) {
        $newNodes = $this->createArray($nodes);
        foreach ($newNodes as $uoData) {
          $build = $this->createBuildArray($uoData);
          $form['search_results']['result'][$uoData['uo']->id()] = $build;
        }
      }
    }

    return $form;
  }

  public function getData() {
    $data['items'] = [];
    // Execute the query.
    if ($nids = $this->queryByFields()) {
      // Load the nodes with the given NIDs.
      // Get the node storage.
      $nodeStorage = $this->entityTypeManager->getStorage('node');
      if ($nodes = $nodeStorage->loadMultiple($nids)) {
        $newNodes = $this->createArray($nodes);
        foreach ($newNodes as $id => $uoData) {
          $uo = $uoData['uo'];
          $address = $uo->field_luogo->entity->field_indirizzo[0]->getValue();
          $pocs = $uo->field_punti_di_contatto->referencedEntities();
          $data['items'][$id]['uo']['uuid'] = $uo->uuid->value;
          $data['items'][$id]['uo']['title'] = $uo->label();
          $data['items'][$id]['uo']['address'] = $address['address_line1'] . ' - '. $address['locality'] .' '. $address['postal_code'];
          $this->createUoPocsArray($data, $pocs, $id);

          // $data['items'][$id]['node'] = $uo;

          foreach ($uoData['persone'] as $key => $personaData) {
            $persona = $personaData['persona'];
            $data['items'][$id]['persone'][$key]['uuid'] = $persona->uuid->value;
            $data['items'][$id]['persone'][$key]['title'] = $persona->title->value;
            $data['items'][$id]['persone'][$key]['incarico'] = $personaData['incarico'];
            $pPocs = $persona->field_punti_di_contatto->referencedEntities();
            $this->createPersonaPocsArray($data, $pPocs, $id, $key);
            $data['items'][$id]['persone'][$key]['uo'][] = $uo->title->value;

            // $data['items'][$id]['persone'][$key]['node'] = $persona;
          }

        }
      }
    }

    return $data;
  }

  /**
   * Create UO POC array
   *
   * @param array $data
   * @param array $pocs
   * @return array
   */
  private function createUoPocsArray(array &$data, array $pocs, int $id): void {
    foreach ($pocs as $key => $pocEntity) {
      $data['items'][$id]['pocs'][$key]['title'] = $pocEntity->label();
      $pocValues = $pocEntity->field_contatto->referencedEntities();

      foreach ($pocValues as $pkey => $pocParagraph) {
        $pocParTitle = $pocParagraph->field_tipo_punto_di_contatto->entity->label();
        $pocParValues = $pocParagraph->field_valore_punto_di_contatto->getValue();
        $pocParValuesToString = '';
        foreach ($pocParValues as $pocParvalue) {
          $pocParValuesToString .= $pocParvalue['value'] . ' ';
        }
        $data['items'][$id]['pocs'][$key]['values'][$pkey]['value'] = $pocParTitle . ': ' . $pocParValuesToString;
      }
    }
  }

  /**
   * Create Persona POC array
   *
   * @param array $data
   * @param array $pocs
   * @return array
   */
  private function createPersonaPocsArray(array &$data, array $pocs, int $id, int $key): void {
    foreach ($pocs as $ppkey => $pocEntity) {
      $data['items'][$id]['persone'][$key]['pocs'][$ppkey]['title'] = $pocEntity->label();
      $pocValues = $pocEntity->field_contatto->referencedEntities();

      foreach ($pocValues as $pkey => $pocParagraph) {
        $pocParTitle = $pocParagraph->field_tipo_punto_di_contatto->entity->label();
        $pocParValues = $pocParagraph->field_valore_punto_di_contatto->getValue();
        $pocParValuesToString = '';
        foreach ($pocParValues as $pocParvalue) {
          $pocParValuesToString .= $pocParvalue['value'] . ' ';
        }
        $data['items'][$id]['persone'][$key]['pocs'][$ppkey]['values'][$pkey]['value'] = $pocParTitle . ': ' . $pocParValuesToString;
      }
    }
  }

  /**
   * Query per i primi 3 campi
   *
   * @param string $firstName
   * @param string $lastName
   * @param int $office
   * @param string $bundle
   * @return mixed
   */
  private function queryByFields(
    string $firstName = '',
    string $lastName = '',
    int $office = 0,
    string $bundle = 'incarico'
  ): mixed {
    // Get the node storage.
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $query = $nodeStorage->getQuery()
      ->condition('field_persona.entity:node.field_nome', $firstName, 'CONTAINS')
      ->condition('field_persona.entity:node.field_cognome', $lastName, 'CONTAINS')
      ->condition('type', $bundle, '=')
      ->groupBy('nid')
      ->sort('title', 'ASC');
    if ($office != 0) {
      $query->condition('field_unita_organizzativa', $office, '=');
    }
    $query->accessCheck(TRUE);

    return $query->execute();
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
    $parse_mode->setConjunction('OR');
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
      // if ($entity->bundle() == 'incarico' || $entity->bundle() == 'unita_organizzativa') {
      $entities[$entity->id()] = $entity;
      // }

    }

    if ($entities) {
      $nodes = $this->createArray($entities);
      foreach ($nodes as $uoData) {
        $build = $this->createBuildArray($uoData);
        $form['search_results']['result'][$uoData['uo']->id()] = $build;
      }
    }

    return $form;
  }

  /**
   * Create nodes array
   *
   * @param array $nodes
   * @return array
   */
  private function createArray(array $nodes): array {
    $newNodes = [];
    foreach ($nodes as $id => $node) {
      $uo = $node->field_unita_organizzativa->entity;
      $incarico = $node->label();
      $persona = $node->field_persona->entity;
      $newNodes[$uo->id()]['uo'] = $uo;
      $newNodes[$uo->id()]['persone'][$persona->id()]['incarico'] = $incarico;
      $newNodes[$uo->id()]['persone'][$persona->id()]['persona'] = $persona;
    }

    return $newNodes;
  }

  /**
   * Costruisce render array di risposta
   *
   * @param array $uoData
   * @return array
   */
  private function createBuildArray(array $uoData): array {
    $build = [];
    $renderedUOContacts = $renderedPersone = null;
    $viewBuilder = $this->entityTypeManager->getViewBuilder('node');
    $uoAddress = $uoData['uo']->field_luogo->entity->field_indirizzo;
    $uoContacts = $uoData['uo']->field_punti_di_contatto->entity;
    if ($uoContacts) {
      $renderedUOContacts = $viewBuilder->view($uoContacts, 'teaser');
    }
    foreach ($uoData['persone'] as $key => $personaData) {
      $persona = $personaData['persona'];
      $renderedPersone[$key]['name'] = $persona->label();
      $renderedPersone[$key]['incarico'] = $personaData['incarico'];
      foreach ($persona->field_punti_di_contatto as $key => $puntoContatto) {
        $personaContacts = $puntoContatto->entity;
        $renderedPersone[$key]['text'] = $viewBuilder->view($personaContacts, 'teaser');
      }
    }

    $build['rubrica'] = [
      '#theme' => 'rubrica_item',
      '#content' => [
        'uo' => $uoData['uo']->label(),
        'uo_address' => $uoAddress->address_line1 . ' ' . $uoAddress->postal_code . ' ' . $uoAddress->locality,
        'uo_contacts' => $renderedUOContacts,
        'persone' => $renderedPersone,
      ],
    ];

    return $build;
  }

  private function createJsonArray(array $uoData): array {
    $build = [];
    $uoAddress = $uoData['uo']->field_luogo->entity->field_indirizzo;
    $uoContacts = $uoData['uo']->field_punti_di_contatto->entity;

    return $build;
  }


  private function getEntities(array $nodes): mixed {
    $form = null;
    // Get the node view builder. This is used to build the render array
    // to view the node.
    $viewBuilder = $this->entityTypeManager->getViewBuilder('node');
    // Loop through the found nodes.
    foreach ($nodes as $node) {
      // Build the render array for the node teaser and add to the
      // results.
      $content[$node->id()] = $viewBuilder->view($node, 'search_index');
      // $form['search_results']['result'][$node->id()] = $viewBuilder->view($node, 'teaser');
      // $form['search_results']['result'][$node->id()] = $node->label();
      $form['search_results']['result'] = $content;
    }

    return $form;
  }

  /**
   * Crea valori per la select del form di ricerca
   *
   * @return array
   */
  public function getUO(): array {
    $options = [0 => '--- Seleziona ---'];
    // Get the node storage.
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $query = $nodeStorage->getQuery()
      ->condition('type', 'unita_organizzativa', '=')
      ->groupBy('nid')
      ->sort('title', 'ASC');

    $query->accessCheck(TRUE);

    // Execute the query.
    if ($nids = $query->execute()) {
      // Load the nodes with the given NIDs.
      if ($nodes = $nodeStorage->loadMultiple($nids)) {
        foreach ($nodes as $nid => $node) {
          $options[$nid] = $node->label();
        }
      }
    }

    return $options;
  }

  /**
   * demo query
   *
   * @param [type] $nodeTitle
   * @return void
   */
  public function nodeQuery($nodeTitle) {
    $form = [];
    // Get the node storage.
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    // Get a list of Node IDs for nodes whose title contains the submitted
    // value. Note that instead of CONTAINS you can use STARTS_WITH or
    // or ENDS_WITH.
    $query = $nodeStorage->getQuery()
      ->condition('title', $nodeTitle, 'CONTAINS')
      ->groupBy('nid')
      ->sort('title', 'ASC');
    $query->accessCheck(TRUE);

    // Execute the query.
    if ($nids = $query->execute()) {
      // Load the nodes with the given NIDs.
      if ($nodes = $nodeStorage->loadMultiple($nids)) {
        // Get the node view builder. This is used to build the render array
        // to view the node.
        $viewBuilder = $this->entityTypeManager->getViewBuilder('node');
        // Loop through the found nodes.
        foreach ($nodes as $node) {
          // Build the render array for the node teaser and add to the
          // results.
          $form['search_results']['result'][$node->id()] = $viewBuilder->view($node, 'teaser');
        }
      }
    }

    return $form;
  }
}

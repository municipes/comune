<?php

namespace Drupal\rubrica\Helper;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\rubrica\Helper\TemplateBuilder;

/**
 * Provides Json and template data.
 */
class RicercaPersonaUo {
  use DependencySerializationTrait;

  /**
   * The entity type manager
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The search by field manager
   *
   * @var \Drupal\rubrica\Helper\TemplateBuilder
   */
  protected $templateBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, TemplateBuilder $templateBuilder) {
    $this->entityTypeManager = $entityTypeManager;
    $this->templateBuilder = $templateBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('rubrica.templatebuilder')
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
    int $office = 0
  ): mixed {
    $form = null;
    // Execute the query.
    if ($nids = $this->queryByFields($firstName, $lastName, $office)) {
      // Load the nodes with the given NIDs.
      if ($nodes = $this->templateBuilder->loadNodes($nids, TRUE)) {
        $items = $this->templateBuilder->createArrays($nodes);
      }

      $build = $this->templateBuilder->createBuildArray($items);

      $form['search_results']['result'][] = $build;
    }

    return $form;
  }



  /**
   * Query per i primi 3 campi
   *
   * @param string $firstName
   * @param string $lastName
   * @param int $office
   * @return mixed
   */
  public function queryByFields(
    string $firstName = '',
    string $lastName = '',
    int $office = 0
  ): mixed {
    // Get the node storage.
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $query = $nodeStorage->getQuery()
      ->condition('status', 1, '=')
      ->groupBy('nid')
      ->sort('title', 'ASC');

    if (!empty(trim($firstName))) {
      $query->condition('field_nome', $firstName, 'CONTAINS');
    }
    if (!empty(trim($lastName))) {
      $query->condition('field_cognome', $lastName, 'CONTAINS');
    }
    if ($office != 0 && empty(trim($lastName)) && empty(trim($firstName))) {
      $query->condition('nid', $office, '=');
    }

    $query->accessCheck(TRUE);

    return $query->execute();
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
      ->condition('status', 1, '=')
      ->groupBy('nid')
      ->sort('title', 'ASC');

    $query->accessCheck(TRUE);

    // Execute the query.
    if ($nids = $query->execute()) {
      $options = $this->templateBuilder->loadNodes($nids);
    }

    return $options;
  }
}

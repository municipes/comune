<?php

namespace Drupal\rubrica\Helper;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Json and template data.
 */
class RicercaPersonaUo {
  use DependencySerializationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The search by field manager.
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
   *   Nome da cercare (parziale).
   * @param string $lastName
   *   Cognome da cercare (parziale).
   * @param int $office
   *   NID dell'unità organizzativa, 0 per nessun filtro.
   *
   * @return mixed
   *   Array con i risultati per il render, o NULL se nessun risultato.
   */
  public function searchByFields(
    string $firstName = '',
    string $lastName = '',
    int $office = 0,
  ): mixed {
    $form = NULL;
    // Execute the query.
    if ($nids = $this->queryByFields($firstName, $lastName, $office)) {
      // Load the nodes with the given NIDs.
      if ($nodes = $this->templateBuilder->loadNodes($nids, TRUE)) {
        // Gli uffici figli (field_unita_organizzativa) vengono mostrati solo
        // per la ricerca per ufficio, non per nome/cognome.
        $items = $this->templateBuilder->createArrays($nodes, FALSE, FALSE, $office !== 0);
      }

      $build = $this->templateBuilder->createBuildArray($items);

      $form['search_results']['result'][] = $build;
    }

    return $form;
  }

  /**
   * Query per i primi 3 campi.
   *
   * @param string $firstName
   *   Nome da cercare (parziale).
   * @param string $lastName
   *   Cognome da cercare (parziale).
   * @param int $office
   *   NID dell'unità organizzativa, 0 per nessun filtro.
   * @param bool $callcenter
   *   Se TRUE aggiunge le persona in stato solo_contact_center.
   *
   * @return mixed
   *   Array di NID, o array vuoto se nessun risultato.
   */
  public function queryByFields(
    string $firstName = '',
    string $lastName = '',
    int $office = 0,
    bool $callcenter = FALSE,
  ): mixed {
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $query = $nodeStorage->getQuery()
      ->condition('status', 1, '=')
      ->groupBy('nid')
      ->sort('title', 'ASC')
      ->sort('nid', 'ASC');

    if (!empty(trim($firstName)) || !empty(trim($lastName))) {
      $query->condition('field_incarico.entity:node.field_tipo_di_incarico', 411);
      if (!empty(trim($firstName))) {
        $query->condition('field_nome', $firstName, 'CONTAINS');
      }
      if (!empty(trim($lastName))) {
        $query->condition('field_cognome', $lastName, 'CONTAINS');
      }
    }
    elseif ($office != 0) {
      $query->condition('type', 'unita_organizzativa', '=');
      $query->condition('nid', $office, '=');
    }
    else {
      // Nessun filtro dal form: percorso REST/elenco completo. Limita ai
      // bundle della rubrica invece di caricare tutti i nodi del sito.
      $query->condition('type', ['persona', 'unita_organizzativa'], 'IN');
    }

    $query->accessCheck(TRUE);
    $nids = $query->execute();

    if ($callcenter) {
      $nids += $this->queryCallCenterPersone();
    }

    return $nids ?: [];
  }

  /**
   * Restituisce i NID delle persona in stato solo_contact_center.
   *
   * Sono inclusi solo i nodi non pubblicati con questo stato di moderazione.
   *
   * @return array
   *   Array di NID indicizzato per NID.
   */
  private function queryCallCenterPersone(): array {
    $cmStorage = $this->entityTypeManager->getStorage('content_moderation_state');
    $cmIds = $cmStorage->getQuery()
      ->condition('content_entity_type_id', 'node')
      ->condition('moderation_state', 'solo_contact_center')
      ->accessCheck(FALSE)
      ->execute();

    if (empty($cmIds)) {
      return [];
    }

    $candidateNids = array_map(
      fn($e) => (int) $e->content_entity_id->value,
      $cmStorage->loadMultiple($cmIds)
    );

    // Verifica che siano effettivamente persona non pubblicati.
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    return $nodeStorage->getQuery()
      ->condition('nid', $candidateNids, 'IN')
      ->condition('type', 'persona', '=')
      ->condition('status', 0, '=')
      ->groupBy('nid')
      ->sort('nid', 'ASC')
      ->accessCheck(FALSE)
      ->execute();
  }

  /**
   * Crea valori per la select del form di ricerca.
   *
   * @return array
   *   Array di opzioni per la select delle unità organizzative.
   */
  public function getUo(): array {
    $options = [0 => '--- Seleziona ---'];
    // Get the node storage.
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $query = $nodeStorage->getQuery()
      ->condition('type', 'unita_organizzativa', '=')
      ->condition('status', 1, '=')
      ->condition('field_tipo_di_organizzazione', [300, 303, 304], 'IN')
      ->groupBy('nid')
      ->sort('title', 'ASC')
      ->sort('nid', 'ASC');

    $query->accessCheck(TRUE);

    // Execute the query.
    if ($nids = $query->execute()) {
      $options += $this->templateBuilder->loadNodes($nids);
    }

    return $options;
  }

}

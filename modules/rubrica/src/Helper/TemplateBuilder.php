<?php

namespace Drupal\rubrica\Helper;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides template arrays.
 */
class TemplateBuilder {
  use DependencySerializationTrait;

  /**
   * The entity type manager.
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
   * Build arrays for template or service.
   *
   * @param array $nodes
   *   Array di nodi indicizzato per NID.
   * @param bool $flat
   *   Se TRUE restituisce array piatti invece di render arrays.
   * @param bool $callcenter
   *   Se TRUE include i campi riservati per il call center.
   *
   * @return array
   *   Array di item strutturati per tipo (persona/unita_organizzativa).
   */
  public function createArrays(array $nodes, bool $flat = FALSE, bool $callcenter = FALSE): array {
    $items = [];
    foreach ($nodes as $nid => $node) {
      switch ($node->bundle()) {
        case 'persona':
          $items[$nid] = [
            'type' => 'persona',
            'value' => $this->getPersonaItem($node, $flat, TRUE, $callcenter),
          ];
          break;

        case 'unita_organizzativa':
          $items[$nid] = [
            'type' => 'unita_organizzativa',
            'value' => $this->getUoItem($node, $flat, $callcenter),
          ];
          break;

        default:
          // code...
          break;
      }
    }
    return $items;
  }

  /**
   * Get persona item with values.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   Il nodo persona da elaborare.
   * @param bool $flat
   *   Se TRUE restituisce array piatti invece di render arrays.
   * @param bool $whitUo
   *   Se TRUE include i dati dell'unità organizzativa collegata.
   * @param bool $callcenter
   *   Se TRUE include i campi riservati per il call center.
   *
   * @return array
   *   Array strutturato con i dati della persona.
   */
  private function getPersonaItem(EntityInterface $node, bool $flat = FALSE, $whitUo = FALSE, bool $callcenter = FALSE): array {
    $incarichiEntity = $this->getReferencedNode($node->id(), 'field_persona', 'incarico', TRUE);
    $contatti = $this->getFieldArray($node->field_punti_di_contatto);
    $uo = [];
    foreach ($contatti as $contatto) {
      if ($flat) {
        $tplContatto[] = $this->createContattiArray($contatto);
      }
      else {
        $tplContatto[] = $this->viewBuilder($contatto, 'teaser');
      }
    }
    foreach ($incarichiEntity as $key => $incaricoEntity) {
      $incarichi[] = $incaricoEntity->label();
      if ($whitUo) {
        $uoEntity = $incaricoEntity->field_unita_organizzativa->entity;
        if ($uoEntity) {
          $indirizzo = $uoEntity->field_luogo->entity->field_indirizzo ?? FALSE;
          // Callcenter uses UO NID as key for direct node path generation.
          $uoKey = $callcenter ? $uoEntity->id() : $key;
          $uo[$uoKey] = [
            'name' => $uoEntity->label(),
            'indirizzo' => $indirizzo->address_line1 . ' ' . $indirizzo->postal_code . ' ' . $indirizzo->locality,
          ];
        }
      }
    }
    $item = [
      'id' => $node->id(),
      'type' => $node->bundle(),
      'nome' => $node->label(),
      'desc' => $node->field_descrizione_breve->value,
      'incarichi' => $incarichi,
      'contatti' => $tplContatto,
      'indirizzo' => FALSE,
      'uo' => $uo,
    ];
    if ($callcenter) {
      $item['cognome'] = $node->field_cognome->value ?? '';
      $item['contatti_riservati'] = [
        'telefono_riservato' => $node->field_telefono_riservato->value,
        'cellulare_riservato' => $node->field_cellulare_riservato->value,
        'note_call_center' => $node->field_note_il_call_center->value,
      ];
    }
    return $item;
  }

  /**
   * Get uo item with values.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   Il nodo unita_organizzativa da elaborare.
   * @param bool $flat
   *   Se TRUE restituisce array piatti invece di render arrays.
   * @param bool $callcenter
   *   Se TRUE include i campi riservati per il call center nelle persone.
   * @param bool $withPersone
   *   Se TRUE include le persone afferenti all'unità.
   *
   * @return array
   *   Array strutturato con i dati dell'unità organizzativa.
   */
  private function getUoItem(EntityInterface $node, bool $flat = FALSE, bool $callcenter = FALSE, $withPersone = TRUE): array {
    $indirizzo = $node->field_luogo->entity->field_indirizzo ?? FALSE;
    $responsabile = FALSE;
    $contatti = [];
    $personeUo = [];
    $incaricoResponsabile = $this->getReferencedNode($node->id(), 'field_responsabile_struttura', 'incarico', TRUE);
    $incaricoResponsabile = reset($incaricoResponsabile);

    if (isset($incaricoResponsabile->field_persona->target_id)) {
      $responsabile = $incaricoResponsabile->field_persona->entity;
    }

    if ($withPersone) {
      $incarichi = $this->getReferencedNode($node->id(), 'field_unita_organizzativa', 'incarico', TRUE);
      foreach ($incarichi as $incarico) {
        if (isset($incarico->field_persona->target_id)) {
          $persona = $incarico->field_persona->entity;
          if ($persona !== NULL) {
            $personeUo[] = $persona;
          }
        }
      }
    }

    $contattiEntity = isset($node->field_punti_di_contatto) ? $this->getFieldArray($node->field_punti_di_contatto) : [];
    foreach ($contattiEntity as $contatto) {
      $contatti[] = $this->createContattiArray($contatto);
    }

    $record = [
      'id' => $node->id(),
      'type' => $node->bundle(),
      'nome' => $node->label(),
      // 'desc' => $node->field_descrizione_breve->value,
      'contatti' => $contatti ?? [],
    ];
    if ($callcenter) {
      // Callcenter uses 'pocs' as the canonical contacts list.
      $record['pocs'] = $contatti ?? [];
    }
    if ($indirizzo) {
      $record['indirizzo'] = $indirizzo->address_line1 . ' ' . $indirizzo->postal_code . ' ' . $indirizzo->locality;
    }
    if ($responsabile) {
      $record['responsabile'] = $this->getPersonaItem($responsabile, $flat, $callcenter, $callcenter);
    }
    if ($personeUo) {
      foreach ($personeUo as $key => $personaUo) {
        $record['persone'][$key] = $this->getPersonaItem($personaUo, $flat, $callcenter, $callcenter);
      }
    }
    return $record;
  }

  /**
   * Extract entities from reference field.
   *
   * @param object $field
   *   Il campo entity reference da cui estrarre le entità.
   *
   * @return array
   *   Array delle entità referenziate.
   */
  private function getFieldArray(object $field): array {
    return $field->referencedEntities();
  }

  /**
   * Costruisce render array di risposta.
   *
   * @param array $items
   *   Array di item strutturati da includere nel render array.
   *
   * @return array
   *   Render array Drupal con il tema rubrica_item.
   */
  public function createBuildArray(array $items): array {
    $build = [];

    $build['rubrica'] = [
      '#theme' => 'rubrica_item',
      '#content' => [
        'items' => $items,
      ],
    ];

    return $build;
  }

  /**
   * Create punto di contatto array.
   *
   * @param \Drupal\node\Entity\Node $contatto
   *   Il nodo punto_di_contatto da serializzare.
   *
   * @return array
   *   Array con titolo e valori del punto di contatto.
   */
  private function createContattiArray(Node $contatto): array {
    $pocValues = $contatto->field_contatto->referencedEntities();

    foreach ($pocValues as $pkey => $pocParagraph) {
      $pocParTitle = $pocParagraph->field_tipo_punto_di_contatto->entity->label();
      $pocParValues = $pocParagraph->field_valore_punto_di_contatto->getValue();
      $pocParValuesToString = '';
      foreach ($pocParValues as $pocParvalue) {
        $pocParValuesToString .= $pocParvalue['value'] . ' ';
      }
      $pocs[] = $pocParTitle . ': ' . $pocParValuesToString;
    }
    return [
      'title' => $contatto->label(),
      'value' => $pocs,
    ];
  }

  /**
   * Ritorna il nodo renderizzato.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   Il nodo da renderizzare.
   * @param string $display
   *   La modalità di visualizzazione (es. 'teaser', 'full').
   *
   * @return mixed
   *   Il render array del nodo nella modalità indicata.
   */
  private function viewBuilder(EntityInterface $node, string $display) {
    $viewBuilder = $this->entityTypeManager->getViewBuilder('node');
    return $viewBuilder->view($node, $display);
  }

  /**
   * Carica i nodi che referenziano un'entità tramite un campo specifico.
   *
   * @param int $id
   *   ID dell'entità referenziata.
   * @param string $field
   *   Nome del campo entity reference su cui filtrare.
   * @param string $type
   *   Bundle (tipo) dei nodi da cercare.
   * @param bool $full
   *   Se TRUE carica i nodi completi, altrimenti solo il titolo.
   *
   * @return array
   *   Array di nodi (o titoli) indicizzato per NID.
   */
  private function getReferencedNode(int $id, string $field, string $type, bool $full = FALSE): array {
    $items = [];
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $query = $nodeStorage->getQuery()
      ->condition($field, $id, '=')
      ->condition('status', 1, '=')
      ->condition('type', $type, '=')
      ->groupBy('nid')
      ->sort('title', 'ASC');

    $query->accessCheck(TRUE);

    // Execute the query.
    if ($nids = $query->execute()) {
      $items = $this->loadNodes($nids, $full);
    }
    return $items;
  }

  /**
   * Load nodes by nids.
   *
   * @param array $nids
   *   Array di NID da caricare.
   * @param bool $full
   *   Se TRUE carica i nodi completi, altrimenti solo il titolo.
   *
   * @return array
   *   Array di nodi (o titoli) indicizzato per NID.
   */
  public function loadNodes(array $nids, bool $full = FALSE): array {
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    // Load the nodes with the given NIDs.
    if ($nodes = $nodeStorage->loadMultiple($nids)) {
      foreach ($nodes as $nid => $node) {
        $items[$nid] = $full ? $node : $node->label();
      }
    }
    return $items;
  }

}

<?php

namespace Drupal\rubrica\Helper;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * Provides template arrays.
 */
class TemplateBuilder {
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
   * Build arrays for template or service
   *
   * @param array $nodes
   * @param bool $flat
   * @return array
   */
  public function createArrays(array $nodes, bool $flat = false): array {
    $items = [];
    foreach ($nodes as $nid => $node) {
      switch ($node->bundle()) {
        case 'persona':
          $items[$nid] = [
            'type' => 'persona',
            'value' => $this->getPersonaItem($node, $flat, true),
          ];
          break;

        case 'unita_organizzativa':
          $items[$nid] = [
            'type' => 'unita_organizzativa',
            'value' => $this->getUoItem($node, $flat),
          ];
          break;

        default:
          # code...
          break;
      }
    }
    return $items;
  }

  /**
   * Get persona item with values
   *
   * @param EntityInterface $node
   * @param bool $flat
   * @return array
   */
  private function getPersonaItem(EntityInterface $node, bool $flat = false, $whitUo = false): array {
    $incarichiEntity = $this->getReferencedNode($node->id(), 'field_persona', 'incarico', true);
    $contatti = $this->getFieldArray($node->field_punti_di_contatto);
    $uo = [];
    foreach ($contatti as $contatto) {
      if ($flat) {
        $tplContatto[] = $this->createContattiArray($contatto);
      } else {
        $tplContatto[] = $this->viewBuilder($contatto, 'teaser');
      }
    }
    foreach ($incarichiEntity as $key => $incaricoEntity) {
      $incarichi[] = $incaricoEntity->label();
      if ($whitUo) {
        $uoEntity = $incaricoEntity->field_unita_organizzativa->entity;
        if ($uoEntity) {
          $indirizzo = isset($uoEntity->field_luogo->entity->field_indirizzo) ? $uoEntity->field_luogo->entity->field_indirizzo : false;
          $uo[$key] = [
            'name' => $uoEntity->label(),
            'indirizzo' => $indirizzo->address_line1 . ' ' . $indirizzo->postal_code . ' ' . $indirizzo->locality,
          ];
        }
      }
    }
    return [
      'id' => $node->id(),
      'type' => $node->bundle(),
      'nome' => $node->label(),
      'desc' => $node->field_descrizione_breve->value,
      'incarichi' => $incarichi,
      'contatti' => $tplContatto,
      'indirizzo' => false,
      'contatti_riservati' => 'da fare',
      'uo' => $uo,
    ];
  }

  /**
   * Get uo item with values
   *
   * @param EntityInterface $node
   * @param bool $flat
   * @return array
   */
  private function getUoItem(EntityInterface $node, bool $flat = false, $withPersone = true): array {
    $indirizzo = isset($node->field_luogo->entity->field_indirizzo) ? $node->field_luogo->entity->field_indirizzo : false;
    $responsabile = false;
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
          $personeUo[] = $incarico->field_persona->entity;
        }
      }
    }

    $contattiEntity = isset($node->field_punti_di_contatto) ? $this->getFieldArray($node->field_punti_di_contatto) : [];
    foreach ($contattiEntity as $contatto) {
      $contatti = $this->createContattiArray($contatto);
    }

    $record = [
      'id' => $node->id(),
      'type' => $node->bundle(),
      'nome' => $node->label(),
      // 'desc' => $node->field_descrizione_breve->value,
      'contatti' => $contatti,
    ];
    if ($indirizzo) {
      $record['indirizzo'] = $indirizzo->address_line1 . ' ' . $indirizzo->postal_code . ' ' . $indirizzo->locality;
    }
    if ($responsabile) {
      $record['responsabile'] = $this->getPersonaItem($responsabile, $flat);
    }
    if ($personeUo) {
      foreach ($personeUo as $key => $personaUo) {
        $contatto = $this->getFieldArray($personaUo->field_punti_di_contatto);
        $contatto = reset($contatto);
        $record['persone'][$key] = $this->getPersonaItem($personaUo, $flat);
      }
    }
    return $record;
  }

  /**
   * Extract entities from reference field
   *
   * @param object $field
   * @return array
   */
  private function getFieldArray(object $field): array {
    return $field->referencedEntities();
  }

  /**
   * Costruisce render array di risposta
   *
   * @param array $data
   * @return array
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
   * Create punto di contatto array
   *
   * @param EntityTypeManagerInterface $contatto
   * @return array
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
   * Ritorna il nodo renderizzato
   *
   * @param Drupal\Core\Entity\EntityInterface $node
   * @param string $display
   * @return void
   */
  private function viewBuilder(EntityInterface $node, string $display) {
    $viewBuilder = $this->entityTypeManager->getViewBuilder('node');
    return $viewBuilder->view($node, $display);
  }

  /**
   * Resume incarichi
   *
   * @param int $idPersona
   * @return array
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
   * Load nodes by nids
   *
   * @param array $nids
   * @return array
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

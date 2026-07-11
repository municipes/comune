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
   * Incarichi pubblicati per persona (nid persona => incarichi per titolo).
   *
   * @var array
   */
  protected array $incarichiByPersona = [];

  /**
   * Incarichi di responsabile per UO (nid UO => incarichi per titolo).
   *
   * @var array
   */
  protected array $incarichiRespByUo = [];

  /**
   * Incarichi afferenti per UO (nid UO => incarichi per titolo).
   *
   * @var array
   */
  protected array $incarichiAfferentiByUo = [];

  /**
   * Uffici figli per UO genitore (nid genitore => nodi UO figli per nid).
   *
   * @var array
   */
  protected array $childrenUoByParent = [];

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
   * @param bool $withChildren
   *   Se TRUE include gli uffici figli (field_unita_organizzativa) delle UO
   *   di primo livello. Mai impostato dal percorso REST/call center.
   *
   * @return array
   *   Array di item strutturati per tipo (persona/unita_organizzativa).
   */
  public function createArrays(array $nodes, bool $flat = FALSE, bool $callcenter = FALSE, bool $withChildren = FALSE): array {
    $this->preloadRelations($nodes);
    if ($callcenter) {
      $unita_organizzative = [];
      $persone = [];
      foreach ($nodes as $nid => $node) {
        switch ($node->bundle()) {
          case 'persona':
            $persone[$nid] = $this->getPersonaItem($node, $flat, TRUE, TRUE);
            break;

          case 'unita_organizzativa':
            $unita_organizzative[$nid] = $this->getUoItem($node, $flat, TRUE);
            break;
        }
      }
      return ['unita_organizzative' => $unita_organizzative, 'persone' => $persone];
    }

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
            'value' => $this->getUoItem($node, $flat, $callcenter, TRUE, $withChildren),
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
   * @param bool $withUo
   *   Se TRUE include i dati dell'unità organizzativa collegata.
   * @param bool $callcenter
   *   Se TRUE include i campi riservati per il call center.
   *
   * @return array
   *   Array strutturato con i dati della persona.
   */
  private function getPersonaItem(EntityInterface $node, bool $flat = FALSE, $withUo = FALSE, bool $callcenter = FALSE): array {
    $incarichiEntity = $this->incarichiByPersona[$node->id()] ?? [];
    $contatti = $this->getFieldArray($node->field_punti_di_contatto);
    $uo = [];
    $incarichi = [];
    $tplContatto = [];
    foreach ($contatti as $contatto) {
      if ($flat) {
        $tplContatto[] = $this->createContattiArray($contatto);
      }
      else {
        // Nodo grezzo: il template lo renderizza raggruppato sotto un'unica
        // etichetta "Contatti", senza il markup del teaser condiviso con le
        // pagine nodo complete.
        $tplContatto[] = $contatto;
      }
    }
    foreach ($incarichiEntity as $key => $incaricoEntity) {
      $incarichi[] = $incaricoEntity->label();
      if ($withUo) {
        $uoEntity = $incaricoEntity->field_unita_organizzativa->entity;
        if ($uoEntity) {
          $indirizzo = $uoEntity->field_luogo->entity->field_indirizzo ?? FALSE;
          // Callcenter uses UO NID as key for direct node path generation.
          $uoKey = $callcenter ? $uoEntity->id() : $key;
          $uo[$uoKey] = [
            'name' => $uoEntity->label(),
            // I due spazi replicano l'output storico quando manca il luogo,
            // per mantenere identico il JSON dell'endpoint REST.
            'indirizzo' => $indirizzo
              ? $indirizzo->address_line1 . ' ' . $indirizzo->postal_code . ' ' . $indirizzo->locality
              : '  ',
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
   * @param bool $withChildren
   *   Se TRUE include gli uffici figli (relazione field_unita_organizzativa
   *   verso questa UO). I figli vengono elaborati con $withChildren=FALSE,
   *   quindi la profondità è sempre limitata a un livello.
   *
   * @return array
   *   Array strutturato con i dati dell'unità organizzativa.
   */
  private function getUoItem(EntityInterface $node, bool $flat = FALSE, bool $callcenter = FALSE, $withPersone = TRUE, bool $withChildren = FALSE): array {
    $indirizzo = $node->field_luogo->entity->field_indirizzo ?? FALSE;
    $responsabile = FALSE;
    $contatti = [];
    $personeUo = [];
    $incarichiResponsabile = $this->incarichiRespByUo[$node->id()] ?? [];

    // Possono esistere più incarichi di responsabile per la stessa unità
    // (es. incarichi storici senza persona collegata): usa il primo che
    // referenzia una persona valida.
    foreach ($incarichiResponsabile as $incaricoResponsabile) {
      if (isset($incaricoResponsabile->field_persona->target_id) && $incaricoResponsabile->field_persona->entity !== NULL) {
        $responsabile = $incaricoResponsabile->field_persona->entity;
        break;
      }
    }

    if ($withPersone) {
      $incarichi = $this->incarichiAfferentiByUo[$node->id()] ?? [];
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
      $contatti[] = $flat ? $this->createContattiArray($contatto) : $contatto;
    }

    $record = [
      'id' => $node->id(),
      'type' => $node->bundle(),
      'nome' => $node->label(),
      // 'desc' => $node->field_descrizione_breve->value,
      'contatti' => $contatti,
    ];
    if ($callcenter) {
      // Callcenter uses 'pocs' as the canonical contacts list.
      $record['pocs'] = $contatti;
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
    if ($withChildren) {
      $childrenNodes = $this->childrenUoByParent[$node->id()] ?? [];
      foreach ($childrenNodes as $childNid => $childNode) {
        // Un solo livello di profondità: i figli non ricevono a loro volta
        // i propri figli.
        $record['figli'][$childNid] = $this->getUoItem($childNode, $flat, $callcenter, $withPersone, FALSE);
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
      '#cache' => [
        'tags' => [
          'node_list:persona',
          'node_list:unita_organizzativa',
          'node_list:incarico',
          'node_list:punto_di_contatto',
          'node_list:luogo',
          'taxonomy_term_list',
        ],
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
    $pocs = [];

    foreach ($pocValues as $pocParagraph) {
      $pocParTitle = $pocParagraph->field_tipo_punto_di_contatto->entity?->label() ?? '';
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
   * Query batch degli incarichi pubblicati che referenziano più target.
   *
   * Replica la vecchia getReferencedNode() (status=1, type=incarico,
   * sort title ASC, accessCheck TRUE) ma con una sola query per campo.
   *
   * @param string $field
   *   Campo entity reference dell'incarico su cui filtrare.
   * @param array $ids
   *   NID target da cercare.
   *
   * @return array
   *   Mappa nid target => array di nodi incarico (keyed per nid incarico,
   *   in ordine di titolo).
   */
  private function queryIncarichiBatch(string $field, array $ids): array {
    if (empty($ids)) {
      return [];
    }
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $nids = $nodeStorage->getQuery()
      ->condition($field, $ids, 'IN')
      ->condition('status', 1, '=')
      ->condition('type', 'incarico', '=')
      ->sort('title', 'ASC')
      ->sort('nid', 'ASC')
      ->accessCheck(TRUE)
      ->execute();

    $map = [];
    foreach ($nodeStorage->loadMultiple($nids) as $nid => $incarico) {
      foreach ($incarico->get($field)->getValue() as $item) {
        $map[(int) $item['target_id']][$nid] = $incarico;
      }
    }
    return $map;
  }

  /**
   * Query batch degli uffici figli (field_unita_organizzativa) di più UO.
   *
   * Un ufficio figlio è una unita_organizzativa il cui campo
   * "Unità organizzativa genitore" (field_unita_organizzativa) referenzia
   * una delle UO indicate.
   *
   * @param array $uoNids
   *   NID delle UO genitore da cercare.
   *
   * @return array
   *   Mappa nid genitore => nodi UO figli (keyed per nid figlio, in ordine
   *   di titolo).
   */
  private function queryChildrenUoBatch(array $uoNids): array {
    if (empty($uoNids)) {
      return [];
    }
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $nids = $nodeStorage->getQuery()
      ->condition('type', 'unita_organizzativa', '=')
      ->condition('field_unita_organizzativa', $uoNids, 'IN')
      ->condition('status', 1, '=')
      ->sort('title', 'ASC')
      ->sort('nid', 'ASC')
      ->accessCheck(TRUE)
      ->execute();

    $map = [];
    foreach ($nodeStorage->loadMultiple($nids) as $nid => $child) {
      $parentNid = (int) $child->field_unita_organizzativa->target_id;
      $map[$parentNid][$nid] = $child;
    }
    return $map;
  }

  /**
   * Precarica in batch incarichi ed entità collegate ai nodi in ingresso.
   *
   * Sostituisce le query per-nodo (N+1) con una query per relazione e
   * scalda la cache statica dell'entity storage per i load annidati
   * (persone, UO, luoghi, punti di contatto).
   *
   * @param array $nodes
   *   Nodi persona/unita_organizzativa indicizzati per NID.
   */
  private function preloadRelations(array $nodes): void {
    $personaNids = [];
    $uoNids = [];
    foreach ($nodes as $nid => $node) {
      if ($node->bundle() === 'persona') {
        $personaNids[] = (int) $nid;
      }
      elseif ($node->bundle() === 'unita_organizzativa') {
        $uoNids[] = (int) $nid;
      }
    }

    // Gli uffici figli vanno cercati prima di precaricare incarichi/luoghi,
    // cosi' che le loro relazioni finiscano nello stesso batch dei genitori
    // invece di generare query aggiuntive in getUoItem().
    $this->childrenUoByParent = $this->queryChildrenUoBatch($uoNids);
    $childrenNodes = [];
    foreach ($this->childrenUoByParent as $children) {
      foreach ($children as $childNid => $childNode) {
        $uoNids[] = $childNid;
        $childrenNodes[$childNid] = $childNode;
      }
    }
    $uoNids = array_values(array_unique($uoNids));

    $this->incarichiRespByUo = $this->queryIncarichiBatch('field_responsabile_struttura', $uoNids);
    $this->incarichiAfferentiByUo = $this->queryIncarichiBatch('field_unita_organizzativa', $uoNids);

    // Anche le persone annidate nelle schede UO (responsabile e afferenti)
    // hanno bisogno dei propri incarichi per le label.
    foreach ([$this->incarichiRespByUo, $this->incarichiAfferentiByUo] as $map) {
      foreach ($map as $incarichi) {
        foreach ($incarichi as $incarico) {
          if (!empty($incarico->field_persona->target_id)) {
            $personaNids[] = (int) $incarico->field_persona->target_id;
          }
        }
      }
    }
    $personaNids = array_values(array_unique($personaNids));
    $this->incarichiByPersona = $this->queryIncarichiBatch('field_persona', $personaNids);

    // Warm-up della cache statica per i ->entity annidati: persone e UO
    // referenziate dagli incarichi, poi luoghi e punti di contatto.
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $warm = $personaNids;
    foreach ($this->incarichiByPersona as $incarichi) {
      foreach ($incarichi as $incarico) {
        if (!empty($incarico->field_unita_organizzativa->target_id)) {
          $warm[] = (int) $incarico->field_unita_organizzativa->target_id;
        }
      }
    }
    $referenced = $nodeStorage->loadMultiple(array_unique($warm));

    $secondLevel = [];
    foreach ($nodes + $referenced + $childrenNodes as $node) {
      if ($node->hasField('field_punti_di_contatto')) {
        foreach ($node->get('field_punti_di_contatto')->getValue() as $item) {
          $secondLevel[] = (int) $item['target_id'];
        }
      }
      if ($node->hasField('field_luogo') && !empty($node->field_luogo->target_id)) {
        $secondLevel[] = (int) $node->field_luogo->target_id;
      }
    }
    $secondLevelLoaded = $secondLevel
      ? $nodeStorage->loadMultiple(array_unique($secondLevel))
      : [];

    // Paragraph dei punti di contatto (entity_reference_revisions):
    // ERR EntityReferenceRevisions::getTarget() carica SEMPRE prima la
    // default revision per entity ID ($storage->load($id)) e usa
    // loadRevision() solo come fallback. Il warm-up efficace è quindi
    // loadMultiple() sui target_id: scalda la cache statica per ID e
    // azzera le query per-paragraph di createContattiArray().
    if ($this->entityTypeManager->hasDefinition('paragraph')) {
      $paragraphIds = [];
      foreach ($secondLevelLoaded as $loaded) {
        if ($loaded->hasField('field_contatto')) {
          // Iterare gli item leggendo target_id direttamente: getValue()
          // sugli item entity_reference_revisions computa la proprietà
          // 'entity' e caricherebbe i paragraph uno a uno proprio qui
          // (verificato: getValue() = 12 query, iterazione = 0 query).
          foreach ($loaded->get('field_contatto') as $item) {
            if (!empty($item->target_id)) {
              $paragraphIds[] = (int) $item->target_id;
            }
          }
        }
      }
      if ($paragraphIds) {
        $this->entityTypeManager->getStorage('paragraph')
          ->loadMultiple(array_unique($paragraphIds));
      }
    }
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
    $items = [];
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

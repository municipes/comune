<?php

namespace Drupal\silfi_services\Controller;

use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\silfi_services\Service\ServizioNodeFieldManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller per gestire i servizi REST relativi al bundle "servizio".
 */
class ServiziController extends ControllerBase {

  private $hashSalt = 'SiN$W$B';
  protected $servizioNodeFieldManager;

  /**
   * Constructs a ServiziController object.
   *
   * @param \Drupal\silfi_services\Service\ServizioNodeFieldManager $servizioNodeFieldManager
   *   The ServizioNodeFieldManager service.
   */
  public function __construct(ServizioNodeFieldManager $servizioNodeFieldManager) {
    // Assign the ServizioNodeFieldManager service.
    $this->servizioNodeFieldManager = $servizioNodeFieldManager;
  }

  /**
   * Creates a new instance of the class.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container, which stores instances of services.
   *
   * @return static
   *   The created instance of the class.
   */
  public static function create(ContainerInterface $container) {
    /**
     * @var \Drupal\silfi_services\Service\ServizioNodeFieldManager
     */

    // Create a new instance of the class, passing the two services as arguments to the constructor.
    return new static(
      $container->get('silfi_services.node_field_manager')
    );
  }

  public function getTriplette(Request $request) {
    if (!$this->isAuthenticated($request)) {
      return new Response('Unauthorized', 401);
    }
    // Definire il tipo di contenuto.
    $vocabolary = 'triplette';

    // Eseguire una entity query per ottenere i termini di vocabolario "triplette".
    $query = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', $vocabolary)
      ->condition('status', 1)
      ->sort('weight', 'ASC')
      ->accessCheck(TRUE);  // Aggiungere il controllo degli accessi.

    $tids = $query->execute();

    // Caricare i termini ottenuti dalla query.
    $terms = Term::loadMultiple($tids);

    // Preparare i dati per la risposta JSON.
    $response_data = [];
    foreach ($terms as $term) {
      $response_data[] = [
        'name' => $term->getName(),
        'parent_tid' => $term->get('parent')->getValue()[0]['target_id'],
        'tid' => $term->id(),
      ];
    }

    // Restituire i dati come JSON, inclusi i metadati di paginazione.
    return new JsonResponse([
      'data' => $response_data,
      'pagination' => [],
    ]);
  }

  /**
   * Restituisce un elenco di servizi (alcuni campi principali) con supporto per la paginazione.
   *
   * La chiamata GET /api/v1/contactcenter/servizi restituisce un elenco di servizi
   * (alcuni campi principali) con supporto per la paginazione.
   *
   * La chiamata accetta i seguenti parametri GET:
   * - page: il numero della pagina da restituire (default 1)
   * - limit: il numero di elementi da restituire per pagina (default 20)
   * - tid: l'ID del termine di vocabolario "triplette" da utilizzare come filtro
   *   (default 0, senza filtro)
   *
   * La risposta JSON contiene i seguenti campi:
   * - data: un array di oggetti contenenti i dati dei singoli servizi
   * - pagination: un oggetto contenente i metadati di paginazione
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   La richiesta HTTP.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Elenco dei nodi del tipo di contenuto "servizio" con paginazione.
   */
  public function getServizi(Request $request) {
    if (!$this->isAuthenticated($request)) {
      return new Response('Unauthorized', 401);
    }

    // Parametri per la paginazione: page (default 1) e limit (default 20).
    $page = (int) $request->query->get('page', 1);
    $limit = (int) $request->query->get('limit', 30);
    $triplette_tid = $request->query->get('tid', FALSE);

    // Calcolare l'offset per la query in base alla pagina corrente.
    $offset = ($page - 1) * $limit;

    // Definire il tipo di contenuto.
    $content_type = 'servizio';

    // Eseguire una entity query per ottenere i nodi di tipo "servizio".
    $query = \Drupal::entityQuery('node')
      ->condition('type', $content_type)
      ->condition('status', 1)
      ->sort('title', 'ASC')
      ->accessCheck(TRUE)  // Aggiungere il controllo degli accessi.
      ->range($offset, $limit);  // Impostare offset e limit per la paginazione.

    // Filtrare i risultati in base all'ID del termine di vocabolario "triplette".
    if ($triplette_tid) {
      $query->condition('field_triplette', (int) $triplette_tid);
    }

    $nids = $query->execute();

    // Caricare i nodi ottenuti dalla query.
    $nodes = Node::loadMultiple($nids);

    // Preparare i dati per la risposta JSON.
    $response_data = [];
    foreach ($nodes as $node) {
      // Preparare i dati del singolo servizio.
      $response_data[] = [
        'nid' => $node->id(),
        'tipo' => $node->bundle(),
        'title' => $node->getTitle(),
        'descrizione_breve' => $node->get('field_descrizione_breve')->value, // Campo principale
        'created' => date('Y-m-d', $node->getCreatedTime()),
        'updated' => date('Y-m-d', $node->getChangedTime()),
        'triplette' => $this->getFieldTriplette($node),
      ];
    }

    // Calcolare il numero totale di nodi del tipo di contenuto "servizio".
    $count_query = \Drupal::entityQuery('node')
      ->condition('type', $content_type)
      ->condition('status', 1)
      ->accessCheck(TRUE);  // Aggiungere il controllo degli accessi.

    // Filtrare i risultati in base all'ID del termine di vocabolario "triplette".
    if ($triplette_tid) {
      $count_query->condition('field_triplette', (int) $triplette_tid);
    }
    $total_count = $count_query->count()->execute();

    // Calcolare il numero totale di pagine.
    $total_pages = ceil($total_count / $limit);

    // Preparare i metadati di paginazione.
    $pagination = [
      'current_page' => $page,
      'total_pages' => $total_pages,
      'total_items' => $total_count,
      'limit' => $limit,
    ];

    // Restituire i dati come JSON, inclusi i metadati di paginazione.
    return new JsonResponse([
      'data' => $response_data,
      'pagination' => $pagination,
    ]);
  }

  /**
   * Restituisce i dettagli di un singolo nodo "servizio" dato il nid.
   *
   * @param int $nid
   *   Il Node ID del nodo richiesto.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   I dettagli del nodo, inclusi i campi relazionati.
   */
  public function getServizio($nid, Request $request) {
    if (!$this->isAuthenticated($request)) {
      return new Response('Unauthorized', 401);
    }

    // Inizializza con l'ID del nodo e il bundle
    if (!$this->servizioNodeFieldManager->initNode($nid, 'servizio')) {
      return new JsonResponse(['message' => 'Nodo non trovato o non è un servizio.'], Response::HTTP_NOT_FOUND);
    }

    $node = $this->servizioNodeFieldManager->getFullNode();
    $field_telefono_avanzato = $this->servizioNodeFieldManager->getTextField('field_telefono_riservato');
    $uffici = $this->servizioNodeFieldManager->getReferencedEntitiesIdLabelMap('field_unita_organizzative');
    foreach ($uffici as $unid => $utitle) {
      $ufficio = \Drupal::entityTypeManager()->getStorage('node')->load($unid);
      $uurl =$ufficio->toUrl('canonical', ['absolute' => TRUE])->toString();
      $field_nome_ufficio[$utitle] = $uurl;
    }
    $tempi_scadenze = [];
    $tempi_scadenze_text = $this->servizioNodeFieldManager->getReferencedEntitiesField('field_tempi_e_scadenze', 'field_text');
    $timeline = $node->get('field_tempi_e_scadenze')->entity;
    $tempi_scadenze_parag = $timeline->field_timeline_item->referencedEntities();

    $tempi_scadenze_items = [];
    foreach ($tempi_scadenze_parag as $paragraph) {
      if ($paragraph->bundle() == 'date_timeline_item') {
        $time = $paragraph->get('field_date')->value;
        $time = date('d M Y', strtotime($time));
      }
      else {
        $time = $paragraph->get('field_days')->value . ' giorni.';
      }
      $tempi_scadenze_items[] = [
        'time' => $time,
        'title' => $paragraph->get('field_title')->value,
      ];
    }

    $tempi_scadenze['intro'] = $tempi_scadenze_text[0];
    $tempi_scadenze['rows'] = $tempi_scadenze_items;

    $field_documenti = $this->servizioNodeFieldManager->getReferencedEntitiesIdLabelMap('field_documenti');
    foreach ($field_documenti as $dnid => $dtitle) {
      $documento = \Drupal::entityTypeManager()->getStorage('node')->load($dnid);
      $durl =$documento->toUrl('canonical', ['absolute' => TRUE])->toString();
      $documenti[$dtitle] = $durl;
    }

    $field_accedi_al_servizio[] = $this->servizioNodeFieldManager->getReferencedEntitiesField('field_accedi_al_servizio', 'field_link');
    $field_accedi_al_servizio[] = $this->servizioNodeFieldManager->getReferencedEntitiesField('field_accedi_al_servizio', 'field_indirizzo');

    $field_punti_di_contatto = $this->servizioNodeFieldManager->getReferencedEntitiesIdLabelMap('field_punti_di_contatto');
    $contatti = [];
    foreach ($field_punti_di_contatto as $pnid => $punto) {
      $contatti[] = [
        'nome' => $punto,
        'valore' => $this->getValorePuntoDiContatto($pnid),
      ];
    }

    $field_schede_collegate = $this->servizioNodeFieldManager->getReferencedEntitiesIdLabelMap('field_schede_collegate');
    foreach ($field_schede_collegate as $snid => $stitle) {
      $scheda = \Drupal::entityTypeManager()->getStorage('node')->load($snid);
      $surl =$scheda->toUrl('canonical', ['absolute' => TRUE])->toString();
      $schede_collegate[$stitle] = $surl;
    }


    // Preparare i dettagli del nodo.
    $response_data['data'][0] = [
      'pnrr' => TRUE,
      'nid' => $nid,
      'field_stato_del_servizio' => (bool) $this->servizioNodeFieldManager->getTextField('field_stato_del_servizio'),
      'field_motivo_dello_stato' => $this->servizioNodeFieldManager->getTextField('field_motivo_dello_stato'),
      'title' => $this->servizioNodeFieldManager->getLabel(),
      'breadcrumb' => $this->getFieldTriplette(),
      'field_telefono_avanzato' => $field_telefono_avanzato,
      'field_nome_ufficio' => $field_nome_ufficio,
      'field_descrizione' => $this->servizioNodeFieldManager->getTextField('field_descrizione_completa'),
      'field_a_chi_e_rivolto' => $this->servizioNodeFieldManager->getTextField('field_a_chi_e_rivolto'),
      'field_come_fare' => $this->servizioNodeFieldManager->getTextField('field_come_fare'),
      'field_cosa_serve'=> $this->servizioNodeFieldManager->getTextField('field_cosa_serve'),
      'field_cosa_si_ottiene' => $this->servizioNodeFieldManager->getTextField('field_cosa_si_ottiene'),
      'field_tempi_e_scadenze' => $tempi_scadenze,
      'field_costi' => $this->servizioNodeFieldManager->getTextField('field_costi'),
      'field_procedure_collegate_esito' => $this->servizioNodeFieldManager->getTextField('field_procedure_collegate_esito'),
      'field_condizioni_di_servizio' => $this->servizioNodeFieldManager->getMediaFilesAbsolutePaths('field_condizioni_di_servizio'),
      'field_documenti' => $documenti,
      'field_note_interne' => $this->servizioNodeFieldManager->getTextField('field_note_interne'),
      'field_accedi_al_servizio' => $field_accedi_al_servizio,
      'field_punti_di_contatto' => $contatti,
      'field_vincoli' => $this->servizioNodeFieldManager->getTextField('field_vincoli'),
      'field_casi_particolari' => $this->servizioNodeFieldManager->getTextField('field_casi_particolari'),
      'field_ulteriori_informazioni' => $this->servizioNodeFieldManager->getTextField('field_ulteriori_informazioni'),
      'field_normativa_di_riferimento' => $this->servizioNodeFieldManager->getTextField('field_normativa_di_riferimento'),
      'field_reclami_ricorsi_opposizioni' => $this->servizioNodeFieldManager->getTextField('field_reclami_ricorsi_opposizion'),
      'field_allegati' => $this->servizioNodeFieldManager->getMediaFilesAbsolutePaths('field_allegati'),
      'field_schede_collegate' => $schede_collegate,
      'field_link_esterni' => $node->get('field_link_esterni')->getValue(),
      'path' => $this->servizioNodeFieldManager->getPath(TRUE),
      'created' => date('Y-m-d', $this->servizioNodeFieldManager->getCreatedTime()),
    ];

    // Restituire i dettagli del nodo come JSON.
    return new JsonResponse($response_data);
  }

  public function getList($type, Request $request) {
    if (!$this->isAuthenticated($request)) {
      return new Response('Unauthorized', 401);
    }
    $response_data = [];
    $valid_lists = [
      'link_documenti',
      'servizi',
      'link_esterni',
    ];

    if (!in_array($type, $valid_lists)) {
      return new JsonResponse(['message' => 'Elenco non trovato o non è un servizio.'], Response::HTTP_NOT_FOUND);
    }

    switch ($type) {
      case 'servizi':
        // Definire il tipo di contenuto.
        $content_type = 'accesso_al_servizio';

        // Eseguire una entity query per ottenere i nodi di tipo "servizio".
        $query = \Drupal::entityQuery('node')
          ->condition('type', $content_type)
          ->condition('status', 1)
          ->condition('field_link.uri', '', '!=')
          ->sort('title', 'ASC')
          ->accessCheck(TRUE);  // Aggiungere il controllo degli accessi.

        $nids = $query->execute();

        // Caricare i nodi ottenuti dalla query.
        $nodes = Node::loadMultiple($nids);

        // Preparare i dati per la risposta JSON.
        $response_data['data'] = [];
        foreach ($nodes as $node) {
          // Genera il codice HTML del link
          $url = Url::fromUri(
            $node->get('field_link')->uri);
          $link_html = Link::fromTextAndUrl(
            $node->label(),
            $url
          )->toString();

          $response_data['data'][] = [
            'nid' => $node->id(),
            'title' => $node->getTitle(),
            'field_link' => $link_html, // Campo principale
            'created' => date('Y-m-d', $node->getCreatedTime()),
          ];
        }
        break;

      default:
        $response_data['data'] = [];
        break;
    }

    // // Aggiungere i campi relazionati (ad esempio, entità referenziate).
    // if ($node->hasField('field_related_entity') && !$node->get('field_related_entity')->isEmpty()) {
    //   foreach ($node->get('field_related_entity')->referencedEntities() as $related_entity) {
    //     $response_data['related_entities'][] = [
    //       'id' => $related_entity->id(),
    //       'title' => $related_entity->label(),
    //     ];
    //   }
    // }

    // Restituire i dettagli del nodo come JSON.
    return new JsonResponse($response_data);
  }

  /**
   * Verifica se la richiesta è autenticata tramite l'API Key.
   *
   * Questo metodo recupera la chiave API dalla configurazione e la confronta
   * con la chiave fornita nell'header della richiesta. Se le chiavi non coincidono
   * o se la chiave non è presente, restituisce un errore 403.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   L'oggetto richiesta HTTP.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|bool
   *   Restituisce TRUE se l'autenticazione ha successo, altrimenti una risposta
   *   JSON con un messaggio di accesso negato.
   */
  private function isAuthenticated($request) {
    // Recuperare la chiave API dalla configurazione.
    $config = $this->config('silfi_services.settings');
    $api_key = sha1($config->get('api_key') . $this->hashSalt);

    // Verificare se l'header X-API-Key è presente e valido.
    $provided_api_key = $request->headers->get('X-API-Key');

    if ($provided_api_key !== $api_key || NULL === $provided_api_key) {
      // Se la chiave API non è valida o mancante, restituire un errore 403.
      // return new JsonResponse(['message' => 'Access denied. Invalid API Key.'], Response::HTTP_FORBIDDEN);
      return FALSE;
    }

    // Restituire TRUE se l'autenticazione ha successo.
    return TRUE;
  }

  /**
   * Restituisce la lista dei termini genitori e del termine selezionato per il
   * campo "Tripletta" di un nodo di tipo "Servizio".
   *
   * @param \Drupal\node\NodeInterface $node
   *   Il nodo di tipo "Servizio" per cui restituire la lista dei termini genitori
   *   della Tripletta.
   *
   * @return array
   *   La lista dei termini genitori e del termine selezionato, ciascuno
   *   rappresentato da un array contenente le chiavi 'tid' e 'name'. Se il termine
   *   non esiste, restituisce un array vuoto.
   */
  private function getFieldTriplette(?NodeInterface $node = NULL): array {
    if ($node) {
      // Inizializza il servizio per la gestione dei campi del nodo.
      $this->servizioNodeFieldManager->initNode($node->id(), 'servizio');
    }

    $triplette = [];

    // Recupera il termine selezionato per il campo "Tripletta".
    if ($tripletta = $this->servizioNodeFieldManager->getReferencedEntity('field_triplette')) {
      // Recupera la lista dei termini genitori del termine selezionato.
      $breadcrumbs = $this->getTermParents($tripletta->id());

      // Aggiunge il termine selezionato alla lista dei termini genitori.
      $breadcrumb[] =  ['tid' => $tripletta->id(), 'name' => $tripletta->label()];

      // Restituisce la lista dei termini genitori e del termine selezionato.
      $triplette = array_merge($breadcrumbs, $breadcrumb);
    }

    return $triplette;
  }

  private function getValorePuntoDiContatto($nid) {
    $node = Node::load($nid);
    return $node->field_contatto->entity->field_valore_punto_di_contatto->value;
  }

  /**
   * Restituisce la lista dei termini genitori di un termine di vocabolario.
   *
   * @param int $tid
   *   L'ID del termine di vocabolario.
   *
   * @return array|null
   *   La lista dei termini genitori, ciascuno rappresentato da un array
   *   contenente le chiavi 'tid' e 'name'. Se il termine non esiste, restituisce
   *   NULL.
   */
  private function getTermParents($tid): ?array {
    /** @var TermStorageInterface $term_storage */
    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $term = $term_storage->load($tid);

    if (!$term) {
      return null;
    }

    $parents = $term_storage->loadParents($tid);

    $parent_terms = [];
    foreach ($parents as $parent) {
      $parent_terms[] = [
        'tid' => $parent->id(),
        'name' => $parent->getName(),
      ];
    }

    return array_reverse($parent_terms);
  }

}

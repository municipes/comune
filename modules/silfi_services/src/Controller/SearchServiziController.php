<?php

namespace Drupal\silfi_services\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\silfi_services\Service\ServizioNodeFieldManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Classe controller per gestire le ricerche in formato JSON.
 *
 * Questa classe fornisce un servizio REST per eseguire le ricerche su un
 * indice di ricerca specifico e restituire i risultati in formato JSON.
 */
class SearchServiziController extends ControllerBase {

  private $hashSalt = 'SiN$W$B';

  protected $servizioNodeFieldManager;

  public function __construct(ServizioNodeFieldManager $servizioNodeFieldManager) {
    $this->servizioNodeFieldManager = $servizioNodeFieldManager;
  }

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('silfi_services.node_field_manager')
    );
  }

  /**
   * Restituisce i risultati di ricerca in formato JSON.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   L'oggetto Request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   La risposta JSON con i risultati di ricerca.
   */
  public function searchResults(Request $request) {
    if (!$this->isAuthenticated($request)) {
      return new Response('Unauthorized', 401);
    }

    // Parametri per la paginazione: page (default 1) e limit (default 20).
    $page = (int) $this->getRequestParameter($request, 'page', 1);
    $limit = (int) $this->getRequestParameter($request, 'limit', 30);
    $search_term = $this->getRequestParameter($request, 'keywords', '');

    // Calcolare l'offset per la query in base alla pagina corrente.
    $offset = ($page - 1) * $limit;

    $response_data = [];
    $total_count = 0;
    $total_pages = 0;

    if (!empty(trim($search_term))) {
      if (!preg_match('/^[a-zA-Z0-9\s\-_\.]+$/', $search_term)) {
        return new JsonResponse(['error' => 'Input non valido'], 400);
      }

      // Ottieni l'indice di ricerca che vuoi utilizzare.
      $index = Index::load('servizi');
      $query = $index->query();
      $query->keys($search_term);
      $query->range($offset, $limit);

      $results = $this->executeQuery($query);
      $total_count = $results->getResultCount();
      $total_pages = (int) ceil($total_count / $limit);
      $response_data = $this->formatResultsAsJson($results);
    }

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
   * Esegue la query di ricerca e restituisce i risultati.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   L'oggetto Query da eseguire.
   *
   * @return \Drupal\search_api\Query\ResultSetInterface
   *   L'oggetto ResultSet contenente i risultati della ricerca.
   */
  protected function executeQuery(QueryInterface $query) {
    try {
      return $query->execute();
    } catch (\Exception $e) {
      // Gestisci gli errori durante l'esecuzione della query
      \Drupal::logger('silfi_services')->error($e->getMessage());
      return new ResultSetInterface();
    }
  }

  /**
   * Formatta i risultati della ricerca in un array di dati JSON.
   *
   * @param \Drupal\search_api\Query\ResultSetInterface $results
   *   L'oggetto ResultSet contenente i risultati della ricerca.
   *
   * @return array
   *   Un array di dati JSON.
   */
  protected function formatResultsAsJson(ResultSetInterface $results) {
    $data = [];
    $nids = [];
    foreach ($results as $result_item) {
      $nids[] = $result_item->getOriginalObject()->getValue()->id();
    }

    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadMultiple($nids);

    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    foreach ($nodes as $node) {
      $triplette = [];
      $triplette_field = $node->get('field_triplette');
      if (!$triplette_field->isEmpty() && $leaf = $triplette_field->entity) {
        // loadAllParents restituisce il termine stesso + tutti gli antenati
        // (ordine: foglia → radice). Escludiamo il termine radice del vocabolario
        // (parent_tid = 0) e invertiamo per ottenere l'ordine macrostruttura → foglia.
        $all = $term_storage->loadAllParents($leaf->id());
        $filtered = array_filter($all, function ($t) {
          return (int) ($t->get('parent')->getValue()[0]['target_id'] ?? 0) !== 0;
        });
        $triplette = array_map(
          fn($t) => ['tid' => $t->id(), 'name' => $t->getName()],
          array_reverse(array_values($filtered))
        );
      }

      $data[] = [
        'nid' => $node->id(),
        'tipo' => $node->bundle(),
        'title' => $node->getTitle(),
        'descrizione_breve' => $node->get('field_descrizione_breve')->value,
        'created' => date('Y-m-d', $node->getCreatedTime()),
        'updated' => date('Y-m-d H:i:s', $node->getChangedTime()),
        'triplette' => $triplette,
      ];
    }
    return $data;
  }

  /**
   * Restituisce un parametro di richiesta da un oggetto Request.
   *
   * Il parametro viene cercato prima tra i parametri della richiesta
   * (ad esempio, quelli passati nel corpo della richiesta) e poi tra
   * i parametri della query string. Se non viene trovato, viene
   * restituito il valore di default specificato.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   L'oggetto Request da cui ottenere il parametro.
   * @param string $key
   *   Il nome del parametro da ottenere.
   * @param mixed $default
   *   Il valore di default da restituire se il parametro non viene
   *   trovato.
   *
   * @return mixed
   *   Il valore del parametro richiesto.
   */
  private function getRequestParameter(Request $request, string $key, $default = NULL) {
    return $request->query->get($key) ?? $request->request->get($key) ?? $default;
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
}

<?php

namespace Drupal\silfi_services\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
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
    // Parametri per la paginazione: page (default 1) e limit (default 20).
    $page = (int) $this->getRequestParameter($request, 'page', 1);
    $limit = (int) $this->getRequestParameter($request, 'limit', 30);
    $search_term = $this->getRequestParameter($request, 'keywords', '');
    $debug = $request->query->get('page', 1);

    // Calcolare l'offset per la query in base alla pagina corrente.
    $offset = ($page - 1) * $limit;


    // Ottieni l'indice di ricerca che vuoi utilizzare.
    $index = Index::load('servizi');

    // Crea una nuova query
    $query = $index->query();

    // Ottieni i parametri di ricerca dalla richiesta.

    if (!empty(trim($search_term))) {
      if (preg_match('/^[a-zA-Z0-9\s\-_\.]+$/', $search_term)) {
        $keywords = $search_term;
      } else {
        throw new \InvalidArgumentException('Input non valido');
      }
      $query->keys($keywords);

      // Configura la paginazione.
      $query->range($offset, $limit);

      // Esegui la query e ottieni i risultati.
      $results = $this->executeQuery($query);
      $total_count = $results->getResultCount();

      // Converti i risultati in un array di dati JSON.
      $response_data = $this->formatResultsAsJson($results);

      $total_pages = ceil($results->getResultCount() / $limit);
    }
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

    foreach ($nodes as $node) {
      // Accedi ai campi indicizzati
      // $fields = $result_item->getFields();
      // Preparare i dati del singolo servizio.
      $data[] = [
        'nid' => $node->id(),
        'tipo' => $node->bundle(),
        'title' => $node->getTitle(),
        // 'descrizione_breve' => $node->get('field_descrizione_breve')->value, // Campo principale
        'created' => date('Y-m-d', $node->getCreatedTime()),
        'updated' => date('Y-m-d', $node->getChangedTime()),
        // 'triplette' => $triplette,
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

}

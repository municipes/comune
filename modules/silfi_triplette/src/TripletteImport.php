<?php

namespace Drupal\silfi_triplette;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\Error;

/**
 * Import triplette data in vocabulary.
 */
class TripletteImport {

  use LoggerChannelTrait;
  use StringTranslationTrait;

  /**
   * Config Factory service object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Current user ID.
   *
   * @var int
   */
  protected $currentUser;

  /**
   * Operation array.
   *
   * @var array
   */
  private $tripletteCount;

  /**
   * Vocabulay ID.
   *
   * @var string
   */
  protected $vid;

  /**
   * Constructs a Triplette object.
   */
  public function __construct(ConfigFactoryInterface $configFactory, MessengerInterface $messenger) {
    $this->configFactory = $configFactory;
    $this->messenger = $messenger;
    $this->currentUser = \Drupal::currentUser()->id();
    $this->tripletteCount = [
      'update' => [],
      'create' => [],
      'delete' => [],
      'error'  => [],
    ];
    $this->vid = SILFI_TRIPLETTE_VOC;
  }

  /**
   * Create new array from json array
   *
   * @param array $data
   * @return void
   */
  public function createTripletteArray(array $data) {
    $data = isset($data['content']) ? $data['content'] : $data;
    foreach ($data as $tripletta) {
      $triplette[$tripletta['ente']][$tripletta['macrostruttura']][$tripletta['id']]  = [
        'value' => $tripletta['servizio'],
        'desc'  => $tripletta['descServizio'],
      ];
    }

    return $triplette;
  }

  /**
   * Import triplette
   *
   * @param array $data
   * @return void
   */
  public function createAllTreeTerms(array $data) {
    $triplette = $this->createTripletteArray($data);
    $record = [];
    $idsServizi = [];
    foreach ($triplette as $ente => $tripletta) {
      $enteTerm = $this->checkOrCreateTerm($ente, null, 0);
      $record['ente'] = $ente;
      foreach ($tripletta as $macrostruttura => $servizi) {
        $macrostrutturaTerm = $this->checkOrCreateTerm(
          $macrostruttura,
          null,
          $enteTerm->id()
        );
        $record['macrostruttura'] = $macrostruttura;
        foreach ($servizi as $id => $servizio) {
          $servizioTerm = $this->checkOrCreateTerm(
            $servizio['value'],
            $servizio['desc'],
            $macrostrutturaTerm->id(),
            $id
          );
          $record['id'] = $id;
          $idsServizi[] = $id;
          $record['servizio'] = $servizio['value'];
          // record in module table;
          $exist = (bool)count($this->tripletteExistByTid($servizioTerm->id()));
          $op = $exist ? 'update' : 'insert';
          $sid = $this->executeRecordInDatabase($op, $record, $servizioTerm->id(), $id);
        }
      }
    }
    // delete non matching terms.
    $this->deleteNotExistentTriplette($idsServizi);
    // show messsages
    $this->showMessages();
  }

  /**
   * Delete all terms and custom table
   *
   * @return void
   */
  public function delete() {
    $connection = \Drupal::service('database');

    $numDeleted = $connection->delete('silfi_triplette')->execute();
    $this->messenger->addWarning($this->t('Cancellate %n triplette', ['%n' => $numDeleted]));
    try {
      $taxonomyStorage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
      $terms = $taxonomyStorage->loadByProperties(['vid' => $this->vid]);

      if (!empty($terms)) {
        foreach ($terms as $term) {
          $term->delete();
        }
      }
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException | EntityStorageException $e) {
      $logger = $this->getLogger('silfi_triplette');
      $logger->error($e->getMessage());
    }
  }

  /**
   * Check term existence or create new
   *
   * @param string $name
   * @param string $description
   * @param int $parent
   * @param int $id
   * @return Term
   */
  private function checkOrCreateTerm(string $name, string $description = null, int $parent = 0, int $id = null) : Term {
    $terms = [];
    if ($id) {
      if ($exist = $this->tripletteExist($id)) {
        $terms[] = Term::load($exist['tid']);
      }
    }
    else {
      $terms = $this->searchTermByName($name);
    }
    if (!empty($terms)) {
      if (count($terms) > 1) {
        throw new \Exception("Error Processing Request", 1);
      }
      foreach($terms AS $termFind) {
        if ($termFind) {
          $term = $this->updateTerm($termFind, $name, $description, $parent, $id);
        }
        else {
          $term = $this->createTerm($name, $description, $parent, $id);
        }
      }
    }
    else {
      $term = $this->createTerm($name, $description, $parent, $id);
    }

    return $term;
  }

  /**
   * Execute queries on custom db table
   *
   * @param string $op
   * @param array $record
   * @param int $tidServizio
   * @param int $sid
   * @return void
   */
  private function executeRecordInDatabase(string $op, array $record, int $tidServizio, $id = NULL) {
    $connection = \Drupal::service('database');
    $query = $connection->{$op}('silfi_triplette');

    switch ($op) {
      case 'insert':
      case 'update':
        $query->fields([
          'ente' => $record['ente'],
          'macrostruttura' => $record['macrostruttura'],
          'servizio' => $record['servizio'],
          'id' => $record['id'],
          'tid' => $tidServizio,
          'obj' => serialize($record),
          'changed' => \Drupal::time()->getRequestTime(),
          'changedby' => $this->currentUser,
        ]);

        if ($tidServizio && $op == 'update') {
          $query->condition('tid', $tidServizio,  '=');
        }
        if ($id && $op == 'update') {
          $query->condition('id', $id,  '=');
        }
        break;

      case 'delete':
        $query->condition('id', $id,  '=');
        break;
    }

    return $query->execute();
  }

  /**
   * Check if tripletta exist in custom db table by ID
   *
   * @param int $id
   * @return array
   */
  private function tripletteExist(int $id) : array {
    $connection = \Drupal::service('database');

    $query = $connection->query("
      SELECT sid, tid
      FROM {silfi_triplette}
      WHERE id = :id
    ", [':id' => $id]);

    $result = $query->fetchAssoc();

    $result = is_array($result) ? $result : [];

    return $result;
  }

  /**
   * Check if tripletta exist in custom db table by TID
   *
   * @param int $tid
   * @return array
   */
  private function tripletteExistByTid(int $tid) : array {
    $connection = \Drupal::service('database');

    $query = $connection->query("
      SELECT sid, tid
      FROM {silfi_triplette}
      WHERE tid = :tid
    ", [':tid' => $tid]);

    $result = $query->fetchAssoc();

    $result = is_array($result) ? $result : [];

    return $result;
  }

  /**
   * Delete non-existing terms
   *
   * @param array $triplette
   * @return void
   */
  private function deleteNotExistentTriplette(array $ids) {
    $connection = \Drupal::service('database');

    $result = $connection->query("
      SELECT sid, tid, id
      FROM {silfi_triplette}
      WHERE id NOT IN (:ids[])
    ", [':ids[]' => $ids]);

    if ($result) {
      while ($row = $result->fetchAssoc()) {
        $term = Term::load($row['tid']);
        if ($term) {
          $this->deleteTerm($term);
        }
        $this->executeRecordInDatabase('delete', [], $row['tid'], $row['id']);
      }
    }
  }

  /**
   * Search term by name
   *
   * @param string $name
   * @return array
   */
  private function searchTermByName(string $name) : array {
    $query = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', $this->vid)
      ->condition('name', $name)
      ->accessCheck(FALSE);
    $tids = $query->execute();

    return Term::loadMultiple($tids);
  }

  /**
   * Create taxonomy term
   *
   * @param string $termName
   * @param string $description
   * @param int $parent_term
   * @return Term
   */
  private function createTerm(string $termName, string $description = NULL, int $parent_term = 0, int $id = NULL) : Term {
    $term = Term::create([
      'name' => $termName,
      'description' => $description,
      'vid' => $this->vid,
      'parent' => $parent_term,
      'field_id' => $id,
    ]);
    $term->enforceIsNew(TRUE);
    $term->save();

    $this->tripletteCount['create'][] = $term->getName() . ' (' . $term->id() . ')';

    return $term;
  }

  /**
   * Update taxonomy term
   *
   * @param Term $term
   * @param string $termName
   * @param string $description
   * @param int $parent
   * @return Term
   */
  private function updateTerm(Term $term, string $termName, string $description = NULL, int $parent = 0, int $id = NULL) : Term {
    $term->setName($termName);
    if ($description) {
      $term->setDescription($description);
    }
    $term->parent->setValue($parent);
    $term->field_id->setValue($id);
    $this->tripletteCount['update'][] = $term->getName() . ' (' . $term->id() . ')';

    $term->save();

    return $term;
  }

  /**
   * Delete taxonomy term
   *
   * @param Term $term
   * @return int
   */
  private function deleteTerm(Term $term) {
    $this->tripletteCount['delete'][] = $term->getName() . ' (' . $term->id() . ')';
    return $term->delete();
  }

  /**
   * Show and log import triplette work
   *
   * @return void
   */
  private function showMessages() {
    $logger = $this->getLogger('silfi_triplette');
    foreach ($this->tripletteCount as $key => $value) {
      $this->messenger->addWarning($this->t('Termini in %mode: %count', ['%mode' => $key, '%count' => count($value)]));
      $logger->info('Termini in %mode: %count', ['%mode' => $key, '%count' => count($value)]);
      foreach ($value as $message) {
        $this->messenger->addWarning($this->t('I termini interessati in %mode: %term', ['%mode' => $key, '%term' => $message]));
        $logger->info('I termini interessati in %mode: %term', ['%mode' => $key, '%term' => $message]);
      }
    }
  }

  /**
   * Run the lightweight cron.
   *
   * The Scheduler part of the processing performed here is the same as in the
   * normal Drupal cron run. The difference is that only scheduler_cron() is
   * executed, no other modules hook_cron() functions are called.
   *
   * This function is called from the external crontab job via url
   * /scheduler/cron/{access key} or it can be run interactively from the
   * Scheduler configuration page at /admin/config/content/scheduler/cron.
   * It is also executed when running Scheduler Cron via drush.
   *
   * @param array $options
   *   Options passed from drush command or admin form.
   */
  public function runLightweightCron(array $options = []) {
    $logger = $this->getLogger('silfi_triplette');
    // When calling via drush the log messages can be avoided by using --nolog.
    $log = empty($options['nolog']);
    if ($log) {
      if (array_key_exists('nolog', $options)) {
        $trigger = 'drush command';
      }
      elseif (array_key_exists('admin_form', $options)) {
        $trigger = 'admin user form';
      }
      else {
        $trigger = 'url';
      }
      // This has to be 'notice' not 'info' so that drush can show the message.
      $logger->notice('Triplette Lightweight cron run activated by @trigger.', ['@trigger' => $trigger]);
    }
    silfi_triplette_cron();
    if (ob_get_level() > 0) {
      $handlers = ob_list_handlers();
      if (isset($handlers[0]) && $handlers[0] == 'default output handler') {
        ob_clean();
      }
    }
    if ($log) {
      $link = Link::fromTextAndUrl($this->t('settings'), Url::fromRoute('silfi_triplette.config'));
      $logger->notice('Triplette Lightweight cron run completed.', ['link' => $link->toString()]);
    }
  }

}

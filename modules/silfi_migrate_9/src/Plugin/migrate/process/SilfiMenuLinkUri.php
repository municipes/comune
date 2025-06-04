<?php

namespace Drupal\silfi_migrate_9\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\MigrateSkipProcessException;

/**
 * Converte gli URI dei menu link aggiornando i nid.
 *
 * @MigrateProcessPlugin(
 *   id = "silfi_menu_link_uri"
 * )
 */
class SilfiMenuLinkUri extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($value)) {
      return $value;
    }

    // Se è un link a un nodo, aggiorna il nid
    if (preg_match('/^entity:node\/(\d+)$/', $value, $matches)) {
      $old_nid = $matches[1];

      // Cerca prima nella tabella migrate_map_page_to_amministrazione_trasparente
      $new_nid = $this->lookupDestinationId('page_to_amministrazione_trasparente', $old_nid);

      // Se non trovato, cerca in migrate_map_page_to_page
      if (!$new_nid) {
        $new_nid = $this->lookupDestinationId('page_to_page', $old_nid);
      }

      if ($new_nid) {
        return 'entity:node/' . $new_nid;
      }

      // Se non trova il nodo mappato, salta questo menu item
      throw new MigrateSkipProcessException('Nodo non trovato nelle tabelle di migrazione: ' . $old_nid);
    }

    // Per altri tipi di link (esterni, route, ecc.) mantieni il valore originale
    return $value;
  }

  /**
   * Cerca il destination ID in una tabella di migrazione.
   */
  protected function lookupDestinationId($migration_id, $source_id) {
    $database = \Drupal::database();
    $table_name = 'migrate_map_' . $migration_id;

    // Verifica se la tabella esiste
    if (!$database->schema()->tableExists($table_name)) {
      return NULL;
    }

    $query = $database->select($table_name, 'm')
      ->fields('m', ['destid1'])
      ->condition('sourceid1', $source_id)
      ->range(0, 1);

    $result = $query->execute()->fetchField();

    return $result ?: NULL;
  }
}

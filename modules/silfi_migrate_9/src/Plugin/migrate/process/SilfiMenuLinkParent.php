<?php

namespace Drupal\silfi_migrate_9\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\MigrateSkipProcessException;

/**
 * Gestisce il parent dei menu link durante la migrazione.
 *
 * @MigrateProcessPlugin(
 *   id = "silfi_menu_link_parent"
 * )
 */
class SilfiMenuLinkParent extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($value)) {
      return '';
    }

    // Cerca il nuovo ID del parent basandosi sull'UUID
    $parent_uuid = $value;

    // Query per trovare il menu link content con questo UUID
    $query = \Drupal::entityQuery('menu_link_content')
      ->condition('uuid', $parent_uuid)
      ->condition('menu_name', 'amministrazione-trasparente')
      ->accessCheck(FALSE);

    $results = $query->execute();

    if (!empty($results)) {
      $parent_id = reset($results);
      return 'menu_link_content:' . $parent_uuid;
    }

    // Se non trova il parent, ritorna stringa vuota (sarà root level)
    return '';
  }
}

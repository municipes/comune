<?php

namespace Drupal\silfi_migrate_9\Plugin\migrate\source;

use Drupal\migrate_drupal_d8\Plugin\migrate\source\d8\ContentEntity;
use Drupal\migrate\Row;

/**
 * Source plugin per nodi Drupal 9 esclusi dal menu amministrazione-trasparente.
 *
 * @MigrateSource(
 *   id = "d9_node",
 *   source_module = "node"
 * )
 */
class D9Node extends ContentEntity {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();

    // Esclude i nodi che sono presenti nel menu amministrazione-trasparente
    $subquery = $this->select('menu_link_content_data', 'mlcd')
      ->fields('mlcd', ['link__uri']);
    $subquery->condition('mlcd.menu_name', 'amministrazione-trasparente');
    $subquery->where('mlcd.link__uri = CONCAT(\'entity:node/\', b.nid)');

    $query->notExists($subquery);

    return $query;
  }
}

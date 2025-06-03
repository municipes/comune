<?php

namespace Drupal\silfi_migrate_9\Plugin\migrate\source;

use Drupal\migrate_drupal_d8\Plugin\migrate\source\d8\ContentEntity;
use Drupal\migrate\Row;

/**
 * Source plugin per nodi Drupal 9 presenti in un menu specifico.
 *
 * @MigrateSource(
 *   id = "d9_node_with_menu",
 *   source_module = "migrate_drupal_d8"
 * )
 */
class D9NodeWithMenu extends ContentEntity {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();

    // Join con menu links per includere solo i nodi presenti nel menu specificato
    $query->innerJoin('menu_link_content_data', 'mlcd', 'mlcd.link__uri = CONCAT(\'entity:node/\', b.nid)');
    $query->addField('mlcd', 'menu_name');
    $query->addField('mlcd', 'title', 'menu_title');
    $query->addField('mlcd', 'weight', 'menu_weight');

    // Filtro per menu name
    if (isset($this->configuration['menu_name'])) {
      $query->condition('mlcd.menu_name', $this->configuration['menu_name']);
    }

    return $query;
  }

}

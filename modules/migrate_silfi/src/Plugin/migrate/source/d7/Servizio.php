<?php

namespace Drupal\migrate_silfi\Plugin\migrate\source\d7;

use Drupal\node\Plugin\migrate\source\d7\Node;
use Drupal\migrate\Row;

/**
 * Drupal 7 node source from database with CMIS attributes.
 *
 * Available configuration keys:
 * - node_type: The node_types to get from the source - can be a string or
 *   an array. If not declared then nodes of all types will be retrieved.
 *
 * @MigrateSource(
 *   id = "d7_node_servizio",
 *   source_module = "node"
 * )
 */
class Servizio extends Node {

  /**
   * {@inheritdoc}
   */
  // public function query() {
  //   $query = parent::query();
  //   $query->condition('n.nid', [11228, 11129], 'IN');

  //   return $query;
  // }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return parent::fields() + [
      'documenti' => $this->t('documenti'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $cmisId = [];
    $nid = $row->getSourceProperty('nid');
    if ($targetIds = $this->getFieldValues('node', 'field_allegati_documentale', $nid)) {
      foreach ($targetIds as $key => $value) {
        $cmisId[] = $this->getDocs($value['target_id']);
      }

      $row->setSourceProperty('documenti', $cmisId);
    }

    return parent::prepareRow($row);
  }

  /**
   * Prende il cmis id.
   *
   * @param int $targetId
   * @return array
   */
  private function getDocs(int $targetId): string {
    $cmisID = [];
    $query = $this->select('field_data_field_allegati_alfresco', 'af')
      ->fields('af', [
        'field_allegati_alfresco_path',
      ]);
    $query->condition('bundle', 'allegato_documentale')
      ->condition('entity_id', $targetId);

    $result = $query->execute();
    foreach ($result as $record) {
      $cmisID = $record['field_allegati_alfresco_path'];
    }

    return $cmisID;
  }
}

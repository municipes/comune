<?php

namespace Drupal\migrate_silfi\Plugin\migrate\source;

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
      'contatti' => $this->t('contatti'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // $nid = $row
    // ->getSourceProperty('nid');
    // $ufficio = $this->getFieldValues('node', 'field_nome_ufficio', $nid);
    // $ufficio = ucfirst(strtolower(trim($ufficio[0]['value'])));
    // $telefono = $this->getFieldValues('node', 'field_telefono', $nid);
    // $email = $this->getFieldValues('node', 'field_email', $nid);
    // $contatti = [
    //   0 => [
    //     'title' => $ufficio,
    //     'type' => ['target_id' => 63],
    //     'value' => $telefono,
    //   ],
    //   1 => [
    //     'title' => $ufficio,
    //     'type' => ['target_id' => 62],
    //     'value' => $email,
    //   ],

    // ];

    // $row->setSourceProperty('contatti', $contatti);

    return parent::prepareRow($row);
  }

}

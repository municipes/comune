<?php

namespace Drupal\migrate_silfi\Plugin\migrate\source\d7;

use Drupal\migrate\Annotation\MigrateSource;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * Custom SqlBase source plugin for UO.
 *
 * @MigrateSource(
 *   id = "d7_unita_organizzativa",
 *   source_module = "migrate_silfi",
 * )
 */
class UnitaOrganizzativa extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Source data is queried from 'curling_games' table.
    $query = $this->select('field_data_field_nome_ufficio', 'u')
      ->fields('u', [
          'entity_id',
          'field_nome_ufficio_value',
        ])
      ->groupBy('field_nome_ufficio_value');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'entity_id' => $this->t('entity_id'),
      'field_nome_ufficio_value'   => $this->t('field_nome_ufficio_value'),
      'title' => $this->t('title'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'entity_id' => [
        'type' => 'integer',
        'alias' => 'u',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $badTitle = $row->getSourceProperty('field_nome_ufficio_value');
    $lower = strtolower(trim($badTitle));
    $title = ucfirst($lower);
    $row->setSourceProperty('title', $title);

    return parent::prepareRow($row);
  }
}

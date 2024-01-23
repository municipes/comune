<?php

namespace Drupal\migrate_silfi\Plugin\migrate\source\d9;

use Drupal\migrate\Annotation\MigrateSource;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * Minimalistic example for a SqlBase source plugin.
 *
 * @MigrateSource(
 *   id = "d9_pocs",
 *   source_module = "migrate_silfi",
 * )
 */
class ParagraphsContacts extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Source data is queried from 'curling_games' table.
    $query = $this->select('paragraphs_item_field_data', 'p')
      ->fields('p', [
          'id',
          'type',
          'parent_id',
        ])
      ->condition('parent_field_name', 'field_contatti');
    $query->leftJoin('paragraph__field_indirizzo_mail', 'm', 'p.id = m.entity_id');
    $query->addField('m', 'field_indirizzo_mail_value', 'email');
    // $query->leftJoin('paragraph__field_numero_di_telefono', 't', 'p.id = t.entity_id');
    // $query->addField('t', 'field_numero_di_telefono_value', 'phone');
    $query->innerJoin('node_field_data', 'n', 'p.parent_id = n.nid');
    $query->addField('n', 'title');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'id'        => $this->t('paragraph_id' ),
      'type'      => $this->t('type' ),
      'parent_id' => $this->t('date'),
      'email'     => $this->t('email' ),
      'phone'     => $this->t('phone'),
      'title'     => $this->t('title'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
        'alias' => 'p',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  // public function prepareRow(Row $row) {
  //   // This example shows how source properties can be added in
  //   // prepareRow(). The source dates are stored as 2017-12-17
  //   // and times as 16:00. Drupal 8 saves date and time fields
  //   // in ISO8601 format 2017-01-15T16:00:00 on UTC.
  //   // We concatenate source date and time and add the seconds.
  //   // The same result could also be achieved using the 'concat'
  //   // and 'format_date' process plugins in the migration
  //   // definition.
  //   $date = $row->getSourceProperty('date');
  //   $time = $row->getSourceProperty('time');
  //   $datetime = $date . 'T' . $time . ':00';
  //   $row->setSourceProperty('datetime', $datetime);
  //   return parent::prepareRow($row);
  // }
}

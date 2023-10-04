<?php

 /**
  * @file
  * Contains \Drupal\migrate_silfi\Plugin\migrate\source\Article.
  */

namespace Drupal\migrate_silfi\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate\Plugin\migrate\source\SqlBase;


/**
 * Drupal 7 paragraph service online source plugin
 *
 * @MigrateSource(
 *   id = "paragraphs_sol",
 *   source_module = "migrate_silfi",
 * )
 */
class ParagraphsServiceOnline extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // this queries the built-in metadata, but not the body, tags, or images.
    // controllare tassonomie usate dal comune nel file .module
    // attenzione alla url del sito da cui prendere le img
    $query = $this->select('node', 'n');
    $query->innerJoin('field_data_field_servizi_on_line', 'sol', 'sol.entity_id = n.nid');
    $query->leftJoin('node', 'nsol', 'sol.field_servizi_on_line_target_id = nsol.nid');
    $query->leftJoin('field_data_field_link_servizio_online', 'l', 'l.entity_id = nsol.nid');

    $query->condition('n.type', 'scheda_servizio')
          ->fields('n', ['nid',])
          ->fields('l', ['field_link_servizio_online_url'])
          ->orderBy('n.nid', 'DESC')
          ->distinct();

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = array(
      'nid' => $this->t('Node ID'),
      'field_link_servizio_online_url' => $this->t('URL servizio'),
    );

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'nid' => [
        'type' => 'integer',
        'alias' => 'n',
      ],
      'field_link_servizio_online_url' => [
        'type' => 'string',
        'alias' => 'l',
      ],
    ];
  }

 }

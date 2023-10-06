<?php
/**
 * @file
 * Contains \Drupal\pagina_legacy\Plugin\migrate\source\d7\AmministrazioneTrasparente.
 */

namespace Drupal\pagina_legacy\Plugin\migrate\source\d7;

use Drupal\migrate\Row;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\node\Plugin\migrate\source\d7\Node;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Migration Source for pagina amministrativa nodes
 *
 * @MigrateSource(
 *   id = "d7_migrate_page_legacy",
 *   source_module = "node"
 * )
 */
class PageLegacy extends Node {

  private $mlid;

  private $not_at;

  private $from_date;

  private $context;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, StateInterface $state, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $state, $entity_type_manager, $module_handler);
    $this->mlid = $configuration['mlid'];
    $this->not_at = $configuration['not_at'];
    $this->from_date = $configuration['from_date'];
    $this->context = $configuration['context'];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    if ($this->mlid && !$this->not_at) {
      $query->condition('n.type', 'page');
      $query->innerJoin('menu_links', 'm', 'n.nid = SUBSTRING(m.link_path FROM 6)');
      $query->condition('m.router_path', 'node/%');
      $query->condition('m.menu_name', 'main-menu');
      $query->condition('m.p1', $this->mlid);
    }
    if ($this->from_date) {
      $query->condition('n.created', $this->from_date, '>');
    }
    if ($this->not_at && $this->mlid) {
      $query->leftJoin('menu_links', 'm', 'n.nid = SUBSTRING(m.link_path FROM 6)');
      $query->condition('m.p1', $this->mlid, '!=');
    }
    if ($this->context) {
      $query->addJoin('LEFT', 'field_data_field_sito', 'cx', 'n.nid = %alias.entity_id');
      $query->condition('cx.field_sito_value', $this->context);
    }
    $query->distinct();

    return $query;
  }

  /*
SELECT * FROM `node` n
INNER JOIN menu_links m ON n.nid = SUBSTRING(m.link_path FROM 6)
WHERE type = 'page' AND m.router_path = 'node/%' AND m.menu_name='menu-amministrazione-trasparente';

  */

  // public function prepareRow(Row $row) {
  //   $nid = $row->getSourceProperty('nid');
  //   //Body field with value, summary, and format
  //   $result = $this->select('field_data_body', 'fdb')
  //     ->fields('fdb', ['body_value', 'body_summary', 'body_format'])
  //     ->condition('fdb.entity_id', $nid)
  //     ->execute();
  //   while($record = $result->fetchObject()){
  //     $row->setSourceProperty('body_value', $record->body_value );
  //     $row->setSourceProperty('body_summary', $record->body_summary );
  //     $row->setSourceProperty('body_format', $record->body_format );
  //   }
  //   //Price field
  //   $field_price_value = $this->select('field_data_field_price', 'p')
  //     ->fields('p', ['field_price_value'])
  //     ->condition('p.entity_id', $nid)
  //     ->execute()->fetchField();
  //   if (!empty($field_price_value)) {
  //     $row->setSourceProperty('price', $field_price_value);
  //   }
  //   // Vitamin Terms Referrence Field
  //   $vitamin_terms = [];
  //   $result = $this->select('field_data_field_vitamins', 'fdv')
  //     ->fields('fdv', ['field_vitamins_tid'])
  //     ->condition('fdv.entity_id', $nid)
  //     ->execute();
  //   while($record = $result->fetchObject()){
  //     $vitamin_terms[] = $record->field_vitamins_tid;
  //   }
  //   if (!empty($vitamin_terms)) {
  //       $row->setSourceProperty('vitamin_terms', $vitamin_terms);
  //   }
  //   // Image field
  //   $images = [];
  //   $result = $this->select('field_data_field_fruit_image', 'fdi')
  //     ->fields('fdi', ['field_fruit_image_fid', 'field_fruit_image_alt', 'field_fruit_image_title', 'field_fruit_image_width', 'field_fruit_image_height'])
  //     ->condition('fdi.entity_id', $nid)
  //     ->execute();
  //   while($record = $result->fetchObject()){
  //     $images[] = [
  //       'target_id' => $record->field_fruit_image_fid,
  //       'alt' => $record->field_fruit_image_alt,
  //       'title' => $record->field_fruit_image_title,
  //       'width' => $record->field_fruit_image_width,
  //       'height' => $record->field_fruit_image_height,
  //     ];
  //   }
  //   if (!empty($images)) {
  //       $row->setSourceProperty('fruit_images', $images);
  //   }
  //   // Migrate URL alias.
  //   $alias = $this->select('url_alias', 'ua')
  //     ->fields('ua', ['alias'])
  //     ->condition('ua.source', 'node/' . $nid)
  //     ->execute()
  //     ->fetchField();
  //   if (!empty($alias)) {
  //     $row->setSourceProperty('alias', '/' . $alias);
  //   }
  //   return parent::prepareRow($row);
  // }
}
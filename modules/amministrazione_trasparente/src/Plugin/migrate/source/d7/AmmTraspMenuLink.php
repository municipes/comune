<?php

namespace Drupal\amministrazione_trasparente\Plugin\migrate\source\d7;

use Drupal\Component\Utility\Unicode;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;
use Drupal\migrate\Row;
use Drupal\menu_link_content\Plugin\migrate\source\MenuLink;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Drupal menu link source from database.
 *
 * @MigrateSource(
 *   id = "amm_trasp_menu_link",
 *   source_module = "menu"
 * )
 */
class AmmTraspMenuLink extends MenuLink {

  private $mlid;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, StateInterface $state, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $state, $entity_type_manager);
    // $this->entityTypeManager = $entity_type_manager;

    $this->mlid = $configuration['mlid'];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    $query->condition('ml.p1', $this->mlid);
    // $query->distinct();
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $new_nid = $this->getNewNid($row->getSourceProperty('link_path'));
    $row->setSourceProperty('link_path', 'node/' . $new_nid);

    return parent::prepareRow($row);
  }

  private function getNewNid($link_path) {
    $pieces = explode('/', $link_path);
    $database = \Drupal::database();
    $query = $database->select('migrate_map_d7_node_amm_trasp', 'g');
    $query->fields('g', ['destid1']);
    $query->condition('g.sourceid1', $pieces[1]);
    $result = $query->execute();
    return $result->fetchField();
  }
}

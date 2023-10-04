<?php

namespace Drupal\migrate_silfi\Plugin\migrate\process;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Transform a pre-Drupal 8 text in plain.
 *
 * @MigrateProcessPlugin(
 *   id = "silfi_abstract_text"
 * )
 */
class AbstractText extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {

    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $striped = strip_tags($value);
    $striped = html_entity_decode($striped, ENT_QUOTES);

    return $this->limitText(trim($striped), 40);
  }

  /**
   * Truncate string at limit
   *
   * @param string $text
   * @param int $limit
   * @return string
   */
  public function limitText(string $text, int $limit) : string {
    if (str_word_count($text, 0) > $limit) {
      $words = str_word_count($text, 2);
      $pos   = array_keys($words);
      $text  = substr($text, 0, $pos[$limit]) . '...';
    }
    return $text;
  }
}

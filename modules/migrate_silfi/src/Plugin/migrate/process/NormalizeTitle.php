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
 *   id = "silfi_normalize_title"
 * )
 */
class NormalizeTitle extends ProcessPluginBase {

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
    if (is_array($value)) {
      $value = $value['value'];
    }
    $lower = strtolower(trim($value));
    $lower = str_replace("aangrafe", "anagrafe", $lower);

    return ucfirst($lower);
  }

}

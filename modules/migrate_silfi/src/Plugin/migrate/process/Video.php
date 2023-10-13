<?php

namespace Drupal\migrate_silfi\Plugin\migrate\process;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Transform a pre-Drupal 8 formatted video for use in Drupal 8.
 *
 * Examples:
 *
 * Consider a video field migration, where you want to use https:// as the
 * prefix:
 *
 * @code
 * process:
 *   field_video:
 *     plugin: field_video
 *     source: field_video
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "silfi_field_video"
 * )
 */
class Video extends ProcessPluginBase {

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
    // Massage the values into the correct form for the link.
    $route['uri'] = $value['video_url'];
    $route['title'] = $value['description'];
    return $route;
  }

}

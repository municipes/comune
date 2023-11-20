<?php

namespace Drupal\silfi_migrate\Plugin\migrate\process;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Transform a pre-Drupal 8 text in plain.
 *
 * @MigrateProcessPlugin(
 *   id = "silfi_link_to_text"
 * )
 */
class LinkToText extends ProcessPluginBase {

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

    $url = Url::fromUri($value['url']);
    $link = new Link($value['title'], $url);
    $string = $link->toString();
    $string->getGeneratedLink();
    $newValue = ['value' => $string->getGeneratedLink()];

    return $newValue;
  }


}

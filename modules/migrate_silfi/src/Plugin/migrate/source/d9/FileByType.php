<?php

namespace Drupal\migrate_silfi\Plugin\migrate\source\d9;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Drupal 9 file source (optionally filtered by type) from database.
 *
 * @MigrateSource(
 *   id = "d9_file_by_type"
 * )
 */
class FileByType extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('file_managed', 'f')
      ->fields('f')
      ->condition('f.uri', 'temporary://%', 'NOT LIKE')
      ->orderBy('f.created');

    // Filter by type(s), if configured.
    if (isset($this->configuration['type'])) {
      $query->condition('f.filemime', $this->configuration['type'] . '%', 'LIKE');
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = parent::fields();
    $fields['type'] = $this->t('The type of file.');
    $fields['alt'] = $this->t('Alt text of the image (if present)');
    $fields['title'] = $this->t('Title text of the image (if present)');
    $fields['width'] = $this->t('Width of the image (if present)');
    $fields['height'] = $this->t('Height text of the image (if present)');
    $fields['description'] = $this->t('Description text of the file (if present)');
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'fid' => [
        'type' => 'integer',
        'alias' => 'f',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    if (isset($this->configuration['get_alt']) || isset($this->configuration['get_description'])) {
      $fid = (int)$row->getSourceProperty('fid');
      $fields = $this->configuration['fields'];
      foreach ($fields as $field) {
        if ($this->configuration['type'] == 'image') {
          if ($alt = $this->getAttr($field, $fid, '_alt')) {
            $row->setSourceProperty('alt', $alt);
          }
        }

        if ($this->configuration['type'] == 'application') {
          if ($desc = $this->getAttr($field, $fid, '_description')) {
            $row->setSourceProperty('description', $desc);
          }
        }
      }
    }
    if ($this->configuration['type'] == 'video') {
      $uri = str_replace('v/', 'v=', $row->getSourceProperty('uri'));
      $uri = str_replace('youtube://', 'https://www.youtube.com/watch?', $uri);
      $row->setSourceProperty('uri', $uri);
    }

    return parent::prepareRow($row);
  }

  /**
   * Get alt value.
   *
   * @param string $field
   * @param int $fid
   * @return string
   */
  private function getAttr(string $field, int $fid, string $attr, string $suffix = '_target_id') {
    $conn = \Drupal\Core\Database\Database::setActiveConnection('d9_source_site');
    $database = \Drupal\Core\Database\Database::getConnection();
    $query = $database->select('node__' . $field, 'f');
    $query->fields('f', [$field . $attr]);
    if ($suffix == 'entity_id') {
      $query->condition('f.' . $suffix, $fid);
    }
    else {
      $query->condition('f.' . $field . $suffix, $fid);
    }
    $result = $query->execute();
    return $result->fetchField();
  }

}

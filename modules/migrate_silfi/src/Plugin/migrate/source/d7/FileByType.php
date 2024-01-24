<?php

namespace Drupal\migrate_silfi\Plugin\migrate\source\d7;

use Drupal\file\Plugin\migrate\source\d7\File;
use Drupal\migrate\Row;

/**
 * Drupal 7 file source (optionally filtered by type) from database.
 *
 * @MigrateSource(
 *   id = "d7_file_by_type",
 *   source_module = "file"
 * )
 */
class FileByType extends File {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();

    // Filter by file type, if configured.
    if (isset($this->configuration['type'])) {
      $query->condition('f.type', $this->configuration['type']);
    }

    // foreach ($this->configuration['fields'] as $field) {
    //   // Get the alt text, if configured.
    //   if (isset($this->configuration['get_alt']) && isset($this->configuration['fields'])) {
    //     $alt_alias = $query->addJoin('LEFT', 'field_data_' . $field, 'alt', 'f.fid = %alias.' . $field . '_fid');
    //     $query->addField($alt_alias, $field . '_alt', 'alt');
    //   }
    //   // Get the title text, if configured.
    //   if (isset($this->configuration['get_title']) && isset($this->configuration['fields'])) {
    //     $title_alias = $query->addJoin('LEFT', 'field_data_' . $field . '', 'title', 'f.fid = %alias.entity_id');
    //     $query->addField($title_alias, $field . '_title', 'title');
    //   }
    //   // Get the description text, if configured.
    //   if (isset($this->configuration['get_description']) && isset($this->configuration['fields'])) {
    //     $desc_alias = $query->addJoin('LEFT', 'field_data_' . $field, 'description', 'f.fid = %alias.' . $field . '_fid');
    //     $query->addField($desc_alias, $field . '_description', 'description');
    //   }
    // }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = parent::fields();
    $fields['type'] = $this->t('The type of file.');
    $fields['alt'] = $this->t('Alt text of the file (if present)');
    $fields['title'] = $this->t('Title text of the file (if present)');
    $fields['description'] = $this->t('Description text of the file (if present)');
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    if (isset($this->configuration['get_alt']) || isset($this->configuration['get_description'])) {
      $fid = (int)$row->getSourceProperty('fid');
      $fields = $this->configuration['d7_fields'];
      foreach ($fields as $field) {
        if (isset($this->configuration['get_alt'])) {
          if ($alt = $this->getAttr($field, $fid, '_alt')) {
            $row->setSourceProperty('alt', $alt);
          }
        }

        if (isset($this->configuration['get_description'])) {
          if ($field != 'field_file_description') {
            if ($desc = $this->getAttr($field, $fid, '_description')) {
              $row->setSourceProperty('description', $desc);
            }
          }
          elseif ($field == 'field_file_description' && $desc = $this->getAttr($field, $fid, '_value', 'entity_id')) {
            $row->setSourceProperty('long_description', strip_tags(html_entity_decode($desc)));
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
  private function getAttr(string $field, int $fid, string $attr, string $suffix = '_fid') {
    $conn = \Drupal\Core\Database\Database::setActiveConnection('migrate');
    $database = \Drupal\Core\Database\Database::getConnection();
    $query = $database->select('field_data_' . $field, 'f');
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

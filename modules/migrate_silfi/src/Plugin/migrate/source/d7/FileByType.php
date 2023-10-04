<?php

namespace Drupal\silfi_migrate\Plugin\migrate\source;

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

    foreach ($this->configuration['fields'] as $field) {
      // Get the alt text, if configured.
      if (isset($this->configuration['get_alt']) && isset($this->configuration['fields'])) {
        $alt_alias = $query->addJoin('LEFT', 'field_data_' . $field, 'alt', 'f.fid = %alias.' . $field . '_fid');
        $query->addField($alt_alias, $field . '_alt', 'alt');
      }
      // Get the title text, if configured.
      if (isset($this->configuration['get_title']) && isset($this->configuration['fields'])) {
        $title_alias = $query->addJoin('LEFT', 'field_data_' . $field . '', 'title', 'f.fid = %alias.entity_id');
        $query->addField($title_alias, $field . '_title', 'title');
      }
      // Get the description text, if configured.
      if (isset($this->configuration['get_description']) && isset($this->configuration['fields'])) {
        $desc_alias = $query->addJoin('LEFT', 'field_data_' . $field, 'description', 'f.fid = %alias.' . $field . '_fid');
        $query->addField($desc_alias, $field . '_description', 'description');
      }
    }

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

  }

}

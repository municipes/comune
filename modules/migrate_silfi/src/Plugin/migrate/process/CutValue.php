<?php

namespace Drupal\migrate_silfi\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Perform custom value transformations.
 *
 * @MigrateProcessPlugin(
 *   id = "cut_value"
 * )
 *
 * To do custom value transformations use the following:
 *
 * @code
 * field_text:
 *   plugin: cut_value
 *   source: text
 * @endcode
 *
 */
class CutValue extends ProcessPluginBase {
  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    return $this->substrwords($value,159);
  }

  /**
   *
   */
  public function substrwords($text, $maxchar, $end='...') {
    if (strlen($text) > $maxchar || $text == '') {
      $words = preg_split('/\s/', $text);
      $output = '';
      $i      = 0;
      while (1) {
        $length = strlen($output)+strlen($words[$i]);
        if ($length > $maxchar) {
          break;
        }
        else {
          $output .= " " . $words[$i];
          ++$i;
        }
      }
      $output .= $end;
    }
    else {
      $output = $text;
    }
    return $output;
  }

}

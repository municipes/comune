<?php

namespace Drupal\wso2silfi\Helper;

use Drupal\field\Entity\FieldStorageConfig;

/**
 * Class CheckUserFieldExist.
 */
class CheckUserFieldExist {

  public static function exist($field_name) {
    $field_storage = FieldStorageConfig::loadByName('user', $field_name);
    if (!empty($field_storage)) {
      return TRUE;
    }
    return FALSE;
  }

}

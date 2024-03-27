<?php

declare(strict_types=1);

namespace Drupal\reconciliation\Commands;

use Drush\Commands\DrushCommands;
use Drupal\node\Entity\Node;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class ReconciliationCommands extends DrushCommands {

  /**
   * Drush command che sostituisce i link dei file.
   *
   * @param string $content_type
   *   Argument with message to be displayed. Only for test.
   * @command reconciliation:missedlinks
   * @aliases rcl-missedlinks rcl-mslinks
   * @option bundle
   *   Node bundle.
   *
   * @usage reconciliation:missedlinks page
   */
  public function missedlinks($content_type = 'none') {
    // entity query
    $query = \Drupal::entityQuery('node');
    if ("none" != $content_type) {
      $query->condition('type', $content_type, '=');
      $nids = $query->execute();
      $database = \Drupal::database();
      foreach ($nids as $nid) {
        // $nid = 25467;
        $db_query = $database->select('migrate_map_silfi_d7_node_page');
        $db_query->addField('migrate_map_silfi_d7_node_page', 'sourceid1');
        $db_query->condition('destid1', $nid);
        $result = $db_query->execute();
        $source_id = $result->fetchField(0);
        // $source_id = 9826;
        if ($source_id) {
          $links = $this->getOldDbValues((int)$source_id);
          if ($links) {
            $this->insertLinks((int)$nid, $links);
          }
        }
      }
      $this->output()->writeln('Finito!');
    } else {
      $this->output()->writeln('Errore! Tipo di contenuto obbligatorio.');
    }
  }

  /**
   * Prende i valori dal db D7.
   *
   * @param int $source_id
   * @return object
   */
  private function getOldDbValues(int $source_id) {
    // Switch to external database
    \Drupal\Core\Database\Database::setActiveConnection('migrate');
    // Get a connection going
    $db = \Drupal\Core\Database\Database::getConnection();
    $db_query = $db->select('field_data_field_link_esterni', 'l');
    $db_query->fields('l', ['entity_id', 'delta', 'field_link_esterni_url', 'field_link_esterni_title']);
    $db_query->condition('entity_id', $source_id);
    $result = $db_query->execute();
    foreach ($result as $record) {
      $links[$record->delta]['uri'] = $record->field_link_esterni_url;
      $links[$record->delta]['title'] = $record->field_link_esterni_title;
      $links[$record->delta]['options'] = [
        'attributes' => [
          'target' => '_blank',
        ],
      ];
    }
    return $links;
  }

  /**
   * Salva i link nel nodo.
   *
   * @param int $nid
   * @param array $links
   * @return void
   */
  private function insertLinks(int $nid, array $links) {
    $node = Node::load($nid);
    if ($node instanceof Node) {
      try {
        $node->set('field_link_utili', $links);
        $node->save();
      } catch (\Exception $e) {
        watchdog_exception('InsertLink', $e);
      }
    }
  }

  /**
   * Replaces links from old nodes.
   *
   * @param       $content_type
   *   Specify a content type for which the node links should be
   *   replaced.
   * @param       $mtable
   *   Specify a migration db table.
   *
   * @usage   reconciliation:replacelink article
   *
   * @command reconciliation:replacelink
   * @aliases rclrep
   */
  public function replacelink(
    $content_type,
    $mtable
  ) {
    // entity query
    $query = \Drupal::entityQuery('node');
    if ("none" != $content_type) {
      $query->condition('type', $content_type, '=');
      $query->accessCheck(FALSE);
      $nids = $query->execute();
      $database = \Drupal::database();
      foreach ($nids as $nid) {
        $db_query = $database->select('node__body');
        $db_query->condition('entity_id', $nid);
        $db_query->condition('body_value', "%node/%", "LIKE");
        $db_query->addField('node__body', 'body_value');
        $db_bodies = $db_query->execute();
        foreach ($db_bodies as $dbBody) {
          $body = $dbBody->body_value;
          $upd_body = $this->replaceLinks($body, $nid, $mtable);
          $db_upd = $database->update('node__body')
            ->fields(['body_value' => $body])
            ->condition('entity_id', $nid)
            ->execute();
        }
      }
      $this->output()->writeln('Finito!');
    } else {
      $this->output()->writeln('Errore! Tipo di contenuto obbligatorio.');
    }
  }

  /**
   * Cerca occorrenze di node/1234.
   */
  private function replaceLinks(&$body, $nid, $mtable) {
    // preg_match_all('/<a href="(\/*)node\/(\d+)"/', $value, $matches, PREG_SET_ORDER);
    $pattern = '/node\/[0-9]+/';
    $res = [];
    $is_match = preg_match_all($pattern, $body, $res);
    if ($is_match > 0) {
      $sourceNids = $this->sourceNids($res, 'node');
      $this->replaceNids($body, $sourceNids, $mtable, 'node');
    }
  }

  /**
   * Sostituisce vecchi nid con i nuovi.
   */
  private function replaceNids(&$body, array $sourceNids, string $mtable, string $command = 'node') {
    $destNids = $this->getDestNids($sourceNids, $mtable, $command);
    foreach ($sourceNids as $key => $sourceNid) {
      if (isset($destNids[$sourceNid])) {
        $body = str_replace($sourceNid, $destNids[$sourceNid], $body, $count);
        $this->output()->writeln('Modificate n. occorrenze: ' . $count);
      }
    }
  }

  /**
   * Cerca i nuovi nids dei nodi migrati.
   */
  private function getDestNids(array $sourceNids, string $mtable, string $command = 'node') {
    $database = \Drupal::database();
    switch ($command) {
      case 'node':
        $db_query = $database->select($mtable);
        $db_query->condition('sourceid1', $sourceNids, 'IN');
        $db_query->fields($mtable, ['sourceid1', 'destid1']);
        break;

      default:
        # code...
        break;
    }

    return $db_query->execute()->fetchAllKeyed();
  }

  /**
   * Ritorna un array di nids.
   */
  private function sourceNids(array $res, string $command = 'node') {
    $sourceNids = [];
    foreach ($res[0] as $key => $value) {
      $pieces = \explode('/', $value);
      switch ($command) {
        case 'node':
          $sourceNids[$pieces[1]] = $pieces[1];
          break;

        case 'file':
          $sourceNids[$pieces[2]] = $pieces[2];
          break;
      }
    }
    return $sourceNids;
  }

  /**
   * Drush command che sostituisce i link dei file.
   * Il comando prevede un parametro content type e due opzioni: url del sito e,
   * se diversa, il nome della cartella del sito D7.
   *
   * @param string $content_type
   *   Il content type da modificare.
   * @param string $field
   *   Il nome del campo di destinazione.
   *
   * @command reconciliation:filelink
   * @aliases rcl-filelink rcl-flink
   *
   * @option site Site URL.
   * @option folder Nome della cartella se diversa dal nome sito.
   *
   * @usage reconciliation:filelink
   */
  public function filelink(
      string $content_type,
      string $field,
      array $options = [
        'site' => null,
        'folder' => null,
      ]
    ) {
    // entity query
    $query = \Drupal::entityQuery('node');
    if ("none" != $content_type && $options['site']) {
      if (!$options['folder']) {
        $options['folder'] = $options['site'];
      }
      $query->condition('type', $content_type, '=');
      $query->accessCheck(FALSE);
      $nids = $query->execute();
      $database = \Drupal::database();
      $count = 0;
      foreach ($nids as $nid) {
        // $nid = 17128;
        $db_query = $database->select('node__' . $field);
        $db_query->condition('entity_id', $nid);
        $db_query->condition($field . '_value', "%/file/%/download%", "LIKE");
        $db_query->addField('node__' . $field, $field . '_value');
        $db_bodies = $db_query->execute();
        // $this->output()->writeln($db_query->__toString);

        $value_field = $field . '_value';
        foreach ($db_bodies as $dbBody) {
          $body = $dbBody->{$value_field};
          $this->replaceFiles($body, $options);
          $database->update('node__' . $field)
            ->fields([$field . '_value' => $body])
            ->condition('entity_id', $nid)
            ->execute();
          $count++;
        }
      }
      $this->output()->writeln('Finito con i link! Eseguiti: ' . $count);
    } else {
      $this->output()->writeln('Errore! Tipo di contenuto e site obbligatorio.');
    }
  }

  /**
   * Cerca occorrenze di /file/12345/download.
   */
  private function replaceFiles(&$body, $options) {
    // preg_match_all('/<a href="(\/*)node\/(\d+)"/', $value, $matches, PREG_SET_ORDER);
    $pattern = '/\/file\/[0-9]+\/download/';
    $res = [];
    $is_match = preg_match_all($pattern, $body, $res);
    if ($is_match > 0) {
      $this->output()->writeln('match!');
      $fids = $this->sourceNids($res, 'file');
      $i = 0;
      foreach ($fids as $fid => $value) {
        $destination = $this->getOldFileName($fid, $options);
        $body = str_replace($res[0][$i], '/' . $destination, $body);
        $i++;
      }
    }
    else {
      $this->output()->writeln('no match :-(');
    }
  }

  /**
   * Undocumented function
   *
   * @param [type] $fid
   * @return void
   */
  private function getOldFileName($fid, $options) {
    // Switch to external database
    \Drupal\Core\Database\Database::setActiveConnection('migrate');
    // Get a connection going
    $db = \Drupal\Core\Database\Database::getConnection();
    $db_query = $db->select('file_managed', 'f');
    $db_query->fields('f', ['uri', 'filename']);
    $db_query->condition('fid', $fid);
    $result = $db_query->execute();
    foreach ($result as $record) {
      $url = str_replace(
        'public://',
        'https://' . $options['site'] . '/sites/' . $options['folder'] . '/files/',
        $record->uri
      );

      $destination = 'sites/default/files/' . $record->filename;
      try {
        /** @var GuzzleHttp\Psr\Response $response */
        $response = \Drupal::httpClient()->get($url, ['sink' => $destination]);
        if ($response->getStatusCode() == 200) {
          return $destination;
        } else return;
      } catch (\Throwable $th) {
        $this->output()->writeln($th->__toString());
      }
    }
    return;
  }

  /**
   * Drush command that displays the given text.
   *
   * @param string $text
   *   Argument with message to be displayed. Only for test.
   * @command reconciliation:message
   * @aliases d9-message d9-msg
   * @option uppercase
   *   Uppercase the message.
   * @option reverse
   *   Reverse the message.
   * @usage reconciliation:message --uppercase --reverse drupal8
   */
  // public function message($text = 'Hello world!', $options = ['uppercase' => FALSE, 'reverse' => FALSE]) {
  //   if ($options['uppercase']) {
  //     $text = strtoupper($text);
  //   }
  //   if ($options['reverse']) {
  //     $text = strrev($text);
  //   }
  //   $this->output()->writeln($text);
  // }

}

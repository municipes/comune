<?php

namespace Drupal\silfi_migrate_9\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Determines the destination bundle based on menu link presence.
 *
 * @MigrateProcessPlugin(
 * id = "silfi9_amministrazione_bundle"
 * )
 */
class AmministrazioneBundle extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // $value è il nid (node ID) del nodo sorgente, come specificato nel file YAML.
    $nid = $value;

    // Ottieni la connessione al database sorgente.
    // Assicurati che il tuo migrate_drupal sia configurato correttamente
    // con la connessione al database Drupal 9.
    $source_database = \Drupal::database();

    try {
      // Query per verificare se il nodo è presente nel menu 'amministrazione-trasparente'.
      // La tabella 'menu_link_content_data' (o 'menu_link_content' in D8/D9)
      // contiene i link ai nodi.
      $query = $source_database->select('menu_link_content_data', 'mlcd');
      $query->fields('mlcd', ['link__uri']);
      $query->condition('mlcd.menu_name', 'amministrazione-trasparente');
      // I link ai nodi sono memorizzati come 'ientity:node/{nid}'.
      $query->condition('mlcd.link__uri', 'entity:node/' . $nid);
      $result = $query->execute()->fetchField();

      if ($result) {
        // Se un link al nodo è trovato nel menu specificato, usa il bundle 'amministrazione_trasparente'.
        return 'amministrazione_trasparente';
      }
      else {
        // Altrimenti, usa il bundle 'page'.
        return 'page';
      }
    }
    catch (\Exception $e) {
      // Gestione degli errori: registra un messaggio se la query fallisce.
      $migrate_executable->saveMessage(sprintf('Errore durante la verifica del menu per il nodo %s: %s', $nid, $e->getMessage()));
      // In caso di errore, ritorna il bundle predefinito 'page' per evitare blocchi.
      return 'page';
    }
  }

}

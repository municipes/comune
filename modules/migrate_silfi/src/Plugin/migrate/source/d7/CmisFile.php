<?php

namespace Drupal\migrate_silfi\Plugin\migrate\source\d7;

use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Annotation\MigrateSource;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;
use Drupal\Core\State\StateInterface;
use Drupal\migrate_silfi\CMISalfRepo;
use Drupal\migrate_silfi\CMISalfObject;


/**
 * Source plugin for importing AlFresco data.
 *
 * Available configuration options:
 * - host: The host and port of the AlFresco server.
 * - user: The username of the AlFresco instance.
 * - pass: The password of the AlFresco instance.
 *
 * Example with required options configured:
 * @code
 * source:
 *   plugin: cmis_file
 *   host: "http://192.168.97.41:8080/alfresco/api/-default-/public/cmis/versions/1.1/atom"
 *   user: "admin"
 *   pass: "admin"
 * @endcode
 *
 * @MigrateSource(
 *   id = "cmis_file",
 *   source_module = "migrate_silfi"
 * )
 */
class CmisFile extends SqlBase {

  /**
   * The AlFresco URL.
   *
   * @var string
   */
  protected $host = 'http://192.168.97.41:8080/alfresco/api/-default-/public/cmis/versions/1.1/atom';

  /**
   * The AlFresco username.
   *
   * @var string
   */
  protected $user = 'admin';

  /**
   * The AlFresco password.
   *
   * @var string
   */
  protected $pass = 'admin';

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, StateInterface $state) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $state);
    $this->host = $configuration['host'];
    $this->user = $configuration['user'];
    $this->pass = $configuration['pass'];
  }

  public function query() {
    // Source data is queried from 'curling_games' table.
    $query = $this->select('field_data_field_allegati_alfresco', 'c')
      ->fields('c', [
          'entity_id',
          'field_allegati_alfresco_path',
        ]);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'entity_id'  => $this->t('entity_id' ),
      'object_id'  => $this->t('object_id'),
      'filename'   => $this->t('filename'),
      'uri'        => $this->t('uri'),
      'filemime'   => $this->t('filemime'),
      'created'    => $this->t('created'),
      'changed'    => $this->t('changed'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $objId = $row->getSourceProperty('field_allegati_alfresco_path');
    // $objId = rtrim($objId, ';1.0');
    $document = $this->getCmisObject($objId);
    $created = $document['gd:creazione'] ?? $document['cmis:creationDate'];
    $created = date_create($created, new \DateTimeZone('Europe/Rome'));
    $created->setTimezone(new \DateTimeZone('UTC'));
    $changed = $document['gd:modifica'] ?? $document['cmis:lastModificationDate'];
    $changed = date_create($changed, new \DateTimeZone('Europe/Rome'));
    $changed->setTimezone(new \DateTimeZone('UTC'));

    $row->setSourceProperty('object_id', $objId);
    $row->setSourceProperty('filename', $document['cmis:name']);
    $row->setSourceProperty('uri', $document['cmis:contentStreamFileName']);
    $row->setSourceProperty('filemime', $document['cmis:contentStreamMimeType']);
    $row->setSourceProperty('created', $created->format('Y-m-d\TH:i:s'));
    $row->setSourceProperty('changed', $changed->format('Y-m-d\TH:i:s'));

    return parent::prepareRow($row);
  }

  private function getCmisObject(string $objId) : array {
    $document = new CMISalfObject($this->host, $this->user, $this->pass, $objId, null, null);
    //prnt document properties and aspects
    // print_r($document->properties);
    // print_r($document->aspects);

    return $document->properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'entity_id' => [
        'type' => 'integer',
        'alias' => 'c'
      ],
      'field_allegati_alfresco_path' => [
        'type' => 'string',
        'alias' => 'c'
      ],
    ];
  }
}

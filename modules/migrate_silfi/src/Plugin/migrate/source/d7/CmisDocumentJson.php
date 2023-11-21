<?php

namespace Drupal\migrate_silfi\Plugin\migrate\source\d7;

use Drupal\migrate_plus\Plugin\migrate\source\Url;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\migrate\Row;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\migrate_silfi\CMISalfRepo;
use Drupal\migrate_silfi\CMISalfObject;


/**
 * Drupal 7 node source from database with CMIS attributes.
 *
 * @MigrateSource(
 *   id = "cmis_document_json",
 *   source_module = "migration_plus"
 * )
 */
class CmisDocumentJson extends Url {

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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);

    $this->host = $configuration['host'];
    $this->user = $configuration['user'];
    $this->pass = $configuration['pass'];
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    $fields = parent::fields();
    $fields += [
      'title'          => $this->t('title'),
      'val_start'      => $this->t('val_start'),
      'val_end'        => $this->t('val_end'),
      'tipo_documento' => $this->t('tipo_documento'),
      'abstract'       => $this->t('abstract'),
      'description'    => $this->t('description'),
      'file_cmis_id'   => $this->t('file_cmis_id'),
      'author'         => $this->t('author'),
      'cmis_id'   => $this->t('cmis_id'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $objId = $row->getSourceProperty('object_id');
    // $objId = rtrim($objId, ';1.0');
    $document = $this->getCmisObject($objId);
    $valStart = $document['gd:dataInizioValidita'] ?? NULL;
    if ($valStart && !empty($valStart)) {
      $valStart = date_create($valStart, new \DateTimeZone('Europe/Rome'));
      $valStart->setTimezone(new \DateTimeZone('UTC'));
      $row->setSourceProperty('val_start', $valStart->format('Y-m-d'));
    }

    $valEnd = $document['gd:dataFineValidita'] ?? NULL;
    if ($valEnd && !empty($valEnd)) {
      $valEnd = date_create($valEnd, new \DateTimeZone('Europe/Rome'));
      $valEnd->setTimezone(new \DateTimeZone('UTC'));
      $row->setSourceProperty('val_end', $valEnd->format('Y-m-d'));
    }

    $tipo_documento = $document['gd:tipo'] ?? NULL;
    $row->setSourceProperty('tipo_documento', $tipo_documento);
    $abstract = $document['gd:oggetto'] ?? NULL;
    if ($abstract) {
      $abstract = html_entity_decode($abstract, ENT_QUOTES);
      $abstract = strtolower(trim($abstract));
      $row->setSourceProperty('abstract', ucfirst($abstract));
    }

    $description = $document['cmis:description'] ?? NULL;
    $title = ucfirst(strtolower($document['cm:title'] ? $document['cm:title'] : $description));
    $title = mb_substr($title, 0, 254);

    // if (!$title) {
    //   $debug = true;
    // }

    $row->setSourceProperty('title', $title);
    $row->setSourceProperty('description', $description);
    $row->setSourceProperty('file_cmis_id', $objId);
    $author = $document['cm:author'] ?? NULL;
    $author = strtolower(trim($author));
    $row->setSourceProperty('author', ucwords($author));

    return parent::prepareRow($row);
  }

  private function getCmisObject(string $objId): array {
    $document = new CMISalfObject($this->host, $this->user, $this->pass, $objId, null, null);
    //prnt document properties and aspects
    // print_r($document->properties);
    // print_r($document->aspects);

    return $document->properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    return parent::getIds();
  }
}

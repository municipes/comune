<?php

namespace Drupal\migrate_silfi\Plugin\migrate\source\d7;

use Drupal\node\Plugin\migrate\source\d7\Node;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\migrate_silfi\CMISalfRepo;
use Drupal\migrate_silfi\CMISalfObject;


/**
 * Drupal 7 node source from database with CMIS attributes.
 *
 * Available configuration keys:
 * - node_type: The node_types to get from the source - can be a string or
 *   an array. If not declared then nodes of all types will be retrieved.
 *
 * Examples:
 *
 * @code
 * source:
 *   plugin: cmis_document_7
 *   node_type: page
 * @endcode
 *
 * In this example nodes of type page are retrieved from the source database.
 *
 * @code
 * source:
 *   plugin: cmis_document_7
 *   node_type: [page, test]
 * @endcode
 *
 * In this example nodes of type page and test are retrieved from the source
 * database.
 *
 * For additional configuration keys, refer to the parent classes.
 *
 * @see \Drupal\migrate\Plugin\migrate\source\SqlBase
 * @see \Drupal\migrate\Plugin\migrate\source\SourcePluginBase
 *
 * @MigrateSource(
 *   id = "cmis_document_7",
 *   source_module = "node"
 * )
 */
class CmisDocument extends Node {

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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, StateInterface $state, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $state, $entity_type_manager, $module_handler);
    $this->host = $configuration['host'];
    $this->user = $configuration['user'];
    $this->pass = $configuration['pass'];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    $query->leftJoin('field_data_field_allegati_alfresco', 'af', '[n].[nid] = [af].[entity_id]');
    $query->addField('af', 'field_allegati_alfresco_path', 'cmis_id');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = parent::fields();
    $fields += [
      'val_start'      => $this->t('val_start' ),
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
    $objId = $row->getSourceProperty('cmis_id');
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

    $row->setSourceProperty('description', $description);
    $row->setSourceProperty('file_cmis_id', $objId);
    $author = $document['cm:author'] ?? NULL;
    $author = strtolower(trim($author));
    $row->setSourceProperty('author', ucwords($author));

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
    return parent::getIds();
  }
}

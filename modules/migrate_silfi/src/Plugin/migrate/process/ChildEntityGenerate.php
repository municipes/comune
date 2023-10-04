<?php

namespace Drupal\migrate_silfi\Plugin\migrate\process;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This plugin generates entities that only exist in context of their parent, eg. paragraphs.
 *
 * @MigrateProcessPlugin(
 *   id = "child_entity_generate",
 * )
 *
 * The more commonly used entity_generate plugin by the Migrate Plus module does not support generating an entity
 * by passing an array of values, and getting the entity fields from that array. This is mostly useful for generating
 * entities that don't need to be checked for previous existence because they only exist in context of their parent,
 * eg. paragraphs.
 *
 * Example usage:
 * @code
 * destination:
 *   plugin: 'entity:node'
 * source:
 *   # assuming we're using a source plugin that lets us define fields like this
 *   fields:
 *     -
 *       faq_items:
 *         -
 *           question: Some question
 *           answer: Some answer
 *         -
 *           question: Another question
 *           answer: Another answer
 * process:
 *   field_faqs:
 *     plugin: silfi_child_entity_generate
 *     source: faq_items
 *     entity_type: field_collection
 *     bundle: faq
 *     values:
 *       field_faq_question: question
 *       field_faq_answer/value: answer
 *     default_values:
 *       field_faq_answer/format: basic_html
 * @endcode
 *
 * If you want to store the whole value in a destination property instead of providing a mapping,
 * you can define the destination property name in the 'destination' key:
 * @code
 * destination:
 *   plugin: 'entity:node'
 * source:
 *   # assuming we're using a source plugin that lets us define fields like this
 *   fields:
 *     -
 *       questions:
 *         - Some question
 *         - Another question
 * process:
 *   field_faqs:
 *     plugin: silfi_child_entity_generate
 *     source: questions
 *     entity_type: field_collection
 *     bundle: question
 *     destination: field_question
 * @endcode
 *
 * If processing needs to happen on the values, you can pass them through the sub_process plugin first:
 * @code
 * destination:
 *   plugin: 'entity:node'
 * source:
 *   # assuming we're using a source plugin that lets us define fields like this
 *   fields:
 *     -
 *       faq_items:
 *         -
 *           id: 100
 *           isHighlighted: true
 *         -
 *           id: 101
 *           isHighlighted: false
 * process:
 *   field_faqs:
 *    -
 *      plugin: sub_process
 *      source: faq_items
 *      process:
 *        entity:
 *          plugin: migration_lookup
 *          migration: faqs
 *          source: id
 *        isHighlighted: isHighlighted
 *    -
 *      plugin: silfi_child_entity_generate
 *      entity_type: field_collection
 *      bundle: faq
 *      values:
 *        field_faq_entity: entity
 *        field_faq_is_highlighted: isHighlighted
 * @endcode
 */
class ChildEntityGenerate extends ProcessPluginBase implements ContainerFactoryPluginInterface {
  /** @var EntityTypeManagerInterface */
  protected $entityTypeManager;

  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration = null
  ) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');

    return $instance;
  }

  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $definition = $this->entityTypeManager
      ->getDefinition($this->configuration['entity_type']);
    $storage = $this->entityTypeManager
      ->getStorage($this->configuration['entity_type']);
    $values = [];

    if ($bundle = $this->configuration['bundle']) {
      $values[$definition->getKey('bundle')] = $bundle;
    }

    if (!empty($this->configuration['destination'])) {
      $values[$this->configuration['destination']] = $value;
    }

    foreach ($this->configuration['values'] ?? [] as $fieldName => $key) {
      $fieldValue = is_array($value)
        ? $value[$key] ?? null
        : $row->get($key);

      NestedArray::setValue($values, explode(Row::PROPERTY_SEPARATOR, $fieldName), $fieldValue, TRUE);
    }

    foreach ($this->configuration['default_values'] ?? [] as $fieldName => $fieldValue) {
      NestedArray::setValue($values, explode(Row::PROPERTY_SEPARATOR, $fieldName), $fieldValue, TRUE);
    }

    // Creates an entity if the lookup determines it doesn't exist.
    if (!($entity = parent::transform($value, $migrate_executable, $row, $destination_property))) {
      $entity = $storage->create($values);
      $entity->save();
    }



    return $entity;
  }
}

<?php

namespace Drupal\rubrica\Plugin\search_api\processor;

use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Excludes entities marked as 'excluded' from being indexes.
 *
 * @SearchApiProcessor(
 *   id = "search_api_exclude_items_from_index",
 *   label = @Translation("Search API Exclude Items From Index - Custom Processor"),
 *   description = @Translation("Excludes some Persone and UO from being indexed."),
 *   stages = {
 *     "alter_items" = -50
 *   }
 * )
 */
class SearchApiExcludeItemsFromIndex extends ProcessorPluginBase {

  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    return $processor;
  }

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items) {

    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item_id => $item) {
      $object = $item->getOriginalObject()->getValue();
      $bundle = $object->bundle();

      // Remove Persona and UO from indexed items.
      // We need to be sure that the field actually exists
      // on the bundle before fetching the value to avoid
      // InvalidArgumentException exceptions.
      switch ($bundle) {
        case 'persona':
          if ($object->hasField('field_incarico')) {
            $incarichi = $object->get('field_incarico')->referencedEntities();
            if (empty($incarichi)) {
              unset($items[$item_id]);
            }
            foreach ($incarichi as $incarico) {
              if ($incarico->hasField('field_tipo_di_incarico')) {
                $value = $incarico->get('field_tipo_di_incarico')->getValue();
                if ((int)$value[0]['target_id'] != 411) {
                  unset($items[$item_id]);
                }
              }
            }
          }
          break;

        case 'unita_organizzativa':
          if ($object->hasField('field_tipo_di_organizzazione')) {
            $value = $object->get('field_tipo_di_organizzazione')->getValue();
            if ((int)$value[0]['target_id'] != 303 && (int)$value[0]['target_id'] != 304) {
              unset($items[$item_id]);
            }
          }
          break;

        default:
          # code...
          break;
      }
    }
  }

}

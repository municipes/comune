<?php

namespace Drupal\silfi_meteo\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Silfi Meteo: Avviso' block.
 *
 * @Block(
 *   id = "silfi_avviso_meteo",
 *   admin_label = @Translation("Silfi Meteo: Avviso")
 * )
 */
class AvvisoMeteoBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The entity type manager
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Start unixtime date
   *
   * @var int
   */
  protected $start;

  /**
   * End unixtime date
   *
   * @var int
   */
  protected $end;

  /**
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $time
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TimeInterface $time, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->time = $time;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('datetime.time'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   *
   * The return value of the build() method is a renderable array. Returning an
   * empty array will result in empty block contents. The front end will not
   * display empty blocks.
   */
  public function build() {
    $build['content'] = [];
    $nids = $this->queryAvvisi();
    if (empty($nids)) {
      $build['#cache']['max-age'] = 0;
      return $build;
    }
    $nodes = Node::loadMultiple($nids);
    foreach ($nodes as $nid => $node) {
      if ($node->isPublished()) {
        $start = new DrupalDateTime();
        $this->end = $node->getFields()['unpublish_on']->getValue()[0];
        $alert = [
          'code' => $node->field_codice_allerta->value,
          'exadecimal' => $node->field_colore_esadecimale->color,
          // 'level' => $node->field_codice_allerta->value,
          'sector' => $node->field_settore->value,
          'area' => $node->field_area->value,
          'icon' => $node->field_immagine_settore->target_id,
          'info_link' => '/node/' . $nid,
        ];
        if ($this->evaluate()) {
          $build['content'][$nid] = [
            '#theme' => 'silfi_avviso_block',
            '#alert' => $alert,
          ];
        }
        else {
          $node->setUnpublished();
          $node->save;
        }
      }
    }
    $build['#cache']['max-age'] = 0;

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    $status = TRUE;
    if (empty($this->start) && empty($this->end)) {
      return TRUE;
    }

    if (!empty($this->start)) {
      $status = $status && time() >= $this->start;
    }

    if (!empty($this->end)) {
      $status = $status && time() <= $this->end;
    }

    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    $current_time = $this->time->getRequestTime();
    $max_age = Cache::PERMANENT;
    // $max_age = 0;

    // If the published on date is in the future, use that.
    if ($this->start > $current_time) {
      $max_age = $this->start - $current_time;
    } elseif ((int) $this->end > $current_time) {
      // If the unpublished time is in the future, use that.
      $max_age = $this->end - $current_time;
    }

    return Cache::mergeMaxAges(parent::getCacheMaxAge(), $max_age);
    // return 0;
  }

  /**
   * Recupera gli ultimi avvisi
   *
   * @return array
   */
  private function queryAvvisi() {
    $now = new DrupalDateTime('now');
    $now->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
    // Get the node storage.
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $query = $nodeStorage->getQuery()
      ->condition('type', 'notizia')
      ->condition('field_codice_allerta', NULL, 'IS NOT NULL')
      ->condition('unpublish_on', $now->format('U'), '>=')
      ->condition('publish_on', NULL, 'IS NULL')
      // ->condition('publish_on', $now->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT), '<=')
      ->sort('nid', 'DESC')

      ->accessCheck(TRUE);
    $results = $query->execute();

    return $results;
  }
}

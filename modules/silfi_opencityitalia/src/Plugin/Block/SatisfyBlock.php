<?php

namespace Drupal\silfi_opencityitalia\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an valutation block.
 *
 * @Block(
 *   id = "silfi_opencityitalia_satisfy",
 *   admin_label = @Translation("OpenCityItalia: Valutazione"),
 *   category = @Translation("Silfi OpenCityItalia")
 * )
 */
class SatisfyBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Config Factory service object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $configFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    // Instantiates this form class.
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      // Load the service required to construct this class.
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->configFactory->get('silfi_opencityitalia.settings');
    $debug = $config->get();
    if (Uuid::isValid($this->configuration['uuid'])) {
      $build['content'] = [
        '#theme' => 'oci_satisfy_block',
        '#data' => [
          'uuid' => $config->get('uuid'),
          'api' => $config->get('api'),
          'common_url' => $config->get('common_url'),
          'satisfy_path' => $config->get('satisfy_path'),
        ],
      ];
    } else {
      $build['content'] = [
        '#markup' => $this->t('Configurazione mancante o non valida'),
      ];
    }

    return $build;
  }
}

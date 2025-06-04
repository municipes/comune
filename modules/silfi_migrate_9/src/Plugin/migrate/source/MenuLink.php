<?php

namespace Drupal\silfi_migrate_9\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * Source plugin per i menu link di Drupal 9.
 *
 * @MigrateSource(
 *   id = "menu_link",
 *   source_module = "silfi_migrate_9"
 * )
 */
class MenuLink extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('menu_link_content_data', 'mlcd')
      ->fields('mlcd', [
        'id',
        'bundle',
        'langcode',
        'enabled',
        'title',
        'description',
        'menu_name',
        'link__uri',
        'link__title',
        'link__options',
        'external',
        'rediscover',
        'weight',
        'expanded',
        'parent',
        'changed',
        'default_langcode',
      ]);

    // Join con menu_link_content per ottenere l'UUID
    $query->leftJoin('menu_link_content', 'mlc', 'mlc.id = mlcd.id');
    $query->addField('mlc', 'uuid', 'uuid');

    // Filtra per il menu specificato
    if (isset($this->configuration['menu_name'])) {
      $query->condition('mlcd.menu_name', $this->configuration['menu_name']);
    }

    // Ordina per parent e weight per mantenere la gerarchia
    $query->orderBy('mlcd.parent');
    $query->orderBy('mlcd.weight');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('Menu link ID'),
      'uuid' => $this->t('UUID'),
      'bundle' => $this->t('Bundle'),
      'langcode' => $this->t('Language code'),
      'enabled' => $this->t('Enabled'),
      'title' => $this->t('Title'),
      'description' => $this->t('Description'),
      'menu_name' => $this->t('Menu name'),
      'link__uri' => $this->t('Link URI'),
      'link__title' => $this->t('Link title'),
      'link__options' => $this->t('Link options'),
      'external' => $this->t('External'),
      'rediscover' => $this->t('Rediscover'),
      'weight' => $this->t('Weight'),
      'expanded' => $this->t('Expanded'),
      'parent' => $this->t('Parent'),
      'parent_uuid' => $this->t('Parent UUID'),
      'changed' => $this->t('Changed'),
      'default_langcode' => $this->t('Default langcode'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // Processa le opzioni del link
    $options = $row->getSourceProperty('link__options');
    if ($options) {
      $row->setSourceProperty('options', unserialize($options));
    }

    // Processa l'URI del link
    $uri = $row->getSourceProperty('link__uri');
    $row->setSourceProperty('link', $uri);

    // Gestisce il parent UUID
    $parent = $row->getSourceProperty('parent');
    if ($parent && preg_match('/^menu_link_content:(.+)$/', $parent, $matches)) {
      $parent_uuid = $matches[1];
      $row->setSourceProperty('parent_uuid', $parent_uuid);
    }
    else {
      $row->setSourceProperty('parent_uuid', '');
    }

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
        'alias' => 'mlcd',
      ],
    ];
  }
}

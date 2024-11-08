<?php

namespace Drupal\silfi_services\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\file\Entity\File;
use Drupal\media\MediaInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;

class ServizioNodeFieldManager {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected FileUrlGeneratorInterface $fileUrlGenerator;
  protected StreamWrapperManagerInterface $streamWrapperManager;
  protected ?NodeInterface $node;
  protected string $bundle;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file URL generator.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    FileUrlGeneratorInterface $fileUrlGenerator,
    StreamWrapperManagerInterface $streamWrapperManager
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->fileUrlGenerator = $fileUrlGenerator;
    $this->streamWrapperManager = $streamWrapperManager;
  }

  /**
   * Inizializza il nodo su cui lavorare.
   *
   * @param int $nodeId
   * @param string $bundle
   * @return bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function initNode(int $nodeId, string $bundle): bool {
    $node = $this->entityTypeManager->getStorage('node')->load($nodeId);

    if (!$node instanceof NodeInterface || $node->bundle() !== $bundle) {
      return false;
    }

    $this->node = $node;
    $this->bundle = $bundle;
    return true;
  }

  public function getFullNode() {
    return $this->node;
  }

  /**
   * Recupera il valore di un campo di testo.
   *
   * @param string $fieldName
   * @return string|null
   */
  public function getTextField(string $fieldName): ?string {
    if (!$this->node || !$this->node->hasField($fieldName)) {
      return null;
    }

    return $this->node->get($fieldName)->value ?? null;
  }

  /**
   * Recupera tutti i valori di un campo multi-valore di testo.
   *
   * @param string $fieldName
   * @return array
   */
  public function getMultiTextField(string $fieldName): array {
    if (!$this->node || !$this->node->hasField($fieldName) || !$this->node->get($fieldName)->isEmpty()) {
      return [];
    }

    $values = [];
    $debug = $this->node->get($fieldName)->getValue();
    $debug2 = $this->node->get($fieldName);
    $text_values = array_column($debug, 'value');
    foreach ($this->node->get($fieldName)->getValue() as $item) {
      if ($value = $item->value) {
        $values[] = $value;
      }
    }
    return $values;
  }

  /**
   * Recupera l'entità referenziata da un campo entity reference.
   *
   * @param string $fieldName
   * @return \Drupal\Core\Entity\EntityInterface|null
   */
  public function getReferencedEntity(string $fieldName) {
    if (!$this->node || !$this->node->hasField($fieldName)) {
      return null;
    }

    return $this->node->get($fieldName)->entity ?? null;
  }

  /**
   * Recupera tutte le entità referenziate da un campo entity reference multi-valore.
   *
   * @param string $fieldName
   * @return array
   */
  public function getReferencedEntities(string $fieldName): array {
    if (!$this->node || !$this->node->hasField($fieldName)) {
      return [];
    }

    return $this->node->get($fieldName)->referencedEntities();
  }

  /**
   * Recupera un valore specifico da un'entità referenziata.
   *
   * @param string $referenceField
   * @param string $targetField
   * @return string|null
   */
  public function getReferencedEntityField(string $referenceField, string $targetField): ?string {
    $entity = $this->getReferencedEntity($referenceField);
    if (!$entity || !$entity->hasField($targetField)) {
      return null;
    }

    return $entity->get($targetField)->value ?? null;
  }

  /**
   * Recupera valori specifici da multiple entità referenziate.
   *
   * @param string $referenceField
   * @param string $targetField
   * @return array
   */
  public function getReferencedEntitiesField(string $referenceField, string $targetField): array {
    $values = [];
    $entities = $this->getReferencedEntities($referenceField);

    foreach ($entities as $entity) {
      if ($entity->hasField($targetField)) {
        $field_type = $entity->get($targetField)->getFieldDefinition()->getType();
        switch ($field_type) {
          case 'entity_reference_revisions':
            $value = $entity->get($targetField)->entity;
            break;

          case 'link':
            $link = $entity->get($targetField)->first();
            if ($link) {
              $value = [
                'uri' => $link->get('uri')->getValue(),
                'title' => $link->get('title')->getValue(),
                'options' => $link->get('options')->getValue(),
              ];
            }
            break;

          case 'address':
            $address = $entity->get($targetField)->first();
            if ($address) {
              $value = [
                'name' => $entity->label(),
                'address_line1' => $address->get('address_line1')->getValue(),
                'postal_code' => $address->get('postal_code')->getValue(),
                'locality' => $address->get('locality')->getValue(),
                'administrative_area' => $address->get('administrative_area')->getValue(),
              ];
            }
            break;

          default:
            $value = $entity->get($targetField)->value;
            break;
        }

        if ($value !== null) {
          $values[] = $value;
        }
      }
    }

    return $values;
  }

  /**
   * Recupera valori specifici da multiple entità referenziate.
   *
   * Questo metodo recupera i valori di un campo specifico da tutte le entità
   * referenziate da un campo entity reference multi-valore.
   *
   * @param string $referenceField
   *   Il nome del campo entity reference.
   * @param string $targetField
   *   Il nome del campo da cui recuperare il valore.
   *
   * @return array
   *   Un array contenente i valori recuperati.
   */
  public function getReferencedEntitiesEntities(string $referenceField, string $targetField): array {
    $values = [];
    $entities = $this->getReferencedEntities($referenceField);

    foreach ($entities as $entity) {
      if ($entity->hasField($targetField)) {
        $value = $entity->get($targetField)->entity;
        if ($value !== null) {
          $values[] = $value;
        }
      }
    }

    return $values;
  }

  /**
   * Returns the path of the node.
   *
   * @param bool $full
   *   Whether to return the absolute URL (including the scheme and host) or
   *   just the relative path.
   *
   * @return string|null
   *   The path of the node, or null if the node is not found.
   */
  public function getPath($full = FALSE): ?string {
    // If $full is TRUE, return the absolute URL (including the scheme and host)
    // otherwise return just the relative path.
    if ($full) {
      return \Drupal::request()->getSchemeAndHttpHost() . $this->node->toUrl()->toString();
    }
    return $this->node->toUrl()->toString();
  }

  /**
   * Retrieves the label of the current node.
   *
   * @return string|null
   *   The label of the node, or null if the node is not available.
   */
  public function getLabel(): ?string {
    // Return the label of the node.
    return $this->node->label();
  }

  /**
   * Returns the creation time of the node.
   *
   * @return string|null
   *   The creation time of the node, or null if the node is not found.
   */
  public function getCreatedTime(): ?string {
    return $this->node->getCreatedTime();
  }

  /**
   * Recupera la label di un'entità referenziata.
   *
   * @param string $fieldName
   * @return string|null
   */
  public function getReferencedEntityLabel(string $fieldName): ?string {
    $entity = $this->getReferencedEntity($fieldName);
    if (!$entity) {
      return null;
    }

    return $entity->label();
  }

  /**
   * Recupera le label di tutte le entità referenziate in un campo multi-valore.
   *
   * @param string $fieldName
   * @return array
   */
  public function getReferencedEntitiesLabels(string $fieldName): array {
    $labels = [];
    $entities = $this->getReferencedEntities($fieldName);

    foreach ($entities as $entity) {
      $labels[] = $entity->label();
    }

    return $labels;
  }

  /**
   * Recupera un array associativo di ID => Label per le entità referenziate.
   *
   * @param string $fieldName
   * @return array
   */
  public function getReferencedEntitiesIdLabelMap(string $fieldName): array {
    $map = [];
    $entities = $this->getReferencedEntities($fieldName);

    foreach ($entities as $entity) {
      $map[$entity->id()] = $entity->label();
    }

    return $map;
  }

  /**
   * Recupera il file da un campo di tipo file.
   *
   * @param string $fieldName
   * @return \Drupal\file\Entity\File|null
   */
  public function getFile(string $fieldName): ?File {
    if (!$this->node || !$this->node->hasField($fieldName)) {
      return null;
    }

    return $this->node->get($fieldName)->entity ?? null;
  }

  /**
   * Recupera il path assoluto di un file.
   *
   * @param string $fieldName
   * @return string|null
   */
  public function getFileAbsolutePath(string $fieldName): ?string {
    $file = $this->getFile($fieldName);
    if (!$file instanceof File) {
      return null;
    }

    // Ottiene il percorso URI del file (es: public://example.pdf)
    $uri = $file->getFileUri();

    // Converte l'URI in un percorso assoluto del filesystem
    return $this->streamWrapperManager->getViaUri($uri)->realpath();
  }

  /**
   * Recupera l'URL pubblico di un file.
   *
   * @param string $fieldName
   * @return string|null
   */
  public function getFileUrl(string $fieldName): ?string {
    $file = $this->getFile($fieldName);
    if (!$file instanceof File) {
      return null;
    }

    return $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
  }

  /**
   * Recupera tutte le informazioni rilevanti di un file.
   *
   * @param string $fieldName
   * @return array|null
   */
  public function getFileInfo(string $fieldName): ?array {
    $file = $this->getFile($fieldName);
    if (!$file instanceof File) {
      return null;
    }

    return [
      'fid' => $file->id(),
      'filename' => $file->getFilename(),
      'uri' => $file->getFileUri(),
      'absolute_path' => $this->getFileAbsolutePath($fieldName),
      'url' => $this->getFileUrl($fieldName),
      'mime_type' => $file->getMimeType(),
      'size' => $file->getSize(),
      'created' => $file->getCreatedTime(),
      'changed' => $file->getChangedTime(),
    ];
  }

  /**
   * Recupera un array di File da un campo multi-valore.
   *
   * @param string $fieldName
   * @return array
   */
  public function getFiles(string $fieldName): array {
    if (!$this->node || !$this->node->hasField($fieldName)) {
      return [];
    }

    return $this->node->get($fieldName)->referencedEntities();
  }

  /**
   * Recupera un array di paths assoluti da un campo file multi-valore.
   *
   * @param string $fieldName
   * @return array
   */
  public function getFilesAbsolutePaths(string $fieldName): array {
    $paths = [];
    $files = $this->getFiles($fieldName);

    foreach ($files as $file) {
      if ($file instanceof File) {
        $uri = $file->getFileUri();
        $paths[] = $this->streamWrapperManager->getViaUri($uri)->realpath();
      }
    }

    return $paths;
  }

    /**
   * Recupera l'entità Media da un campo media.
   *
   * @param string $fieldName
   * @return \Drupal\media\MediaInterface|null
   */
  public function getMedia(string $fieldName): ?MediaInterface {
    if (!$this->node || !$this->node->hasField($fieldName)) {
      return null;
    }

    $media = $this->node->get($fieldName)->entity;
    return $media instanceof MediaInterface ? $media : null;
  }

  /**
   * Recupera il File associato a un'entità Media.
   *
   * @param \Drupal\media\MediaInterface $media
   * @return \Drupal\file\Entity\File|null
   */
  protected function getFileFromMedia(MediaInterface $media): ?File {
    // Ottiene il nome del campo file in base al bundle media
    $sourceField = $media->getSource()->getConfiguration()['source_field'];

    if (!$media->hasField($sourceField)) {
      return null;
    }

    return $media->get($sourceField)->entity;
  }

  /**
   * Recupera il path assoluto del file da un campo media.
   *
   * @param string $fieldName
   * @return string|null
   */
  public function getMediaFileAbsolutePath(string $fieldName): ?string {
    $media = $this->getMedia($fieldName);
    if (!$media) {
      return null;
    }

    $file = $this->getFileFromMedia($media);
    if (!$file instanceof File) {
      return null;
    }

    $uri = $file->getFileUri();
    return $this->streamWrapperManager->getViaUri($uri)->realpath();
  }

  /**
   * Recupera l'URL del file da un campo media.
   *
   * @param string $fieldName
   * @return string|null
   */
  public function getMediaFileUrl(string $fieldName): ?string {
    $media = $this->getMedia($fieldName);
    if (!$media) {
      return null;
    }

    $file = $this->getFileFromMedia($media);
    if (!$file instanceof File) {
      return null;
    }

    return $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
  }

  /**
   * Recupera tutte le informazioni del file da un campo media.
   *
   * @param string $fieldName
   * @return array|null
   */
  public function getMediaFileInfo(string $fieldName): ?array {
    $media = $this->getMedia($fieldName);
    if (!$media) {
      return null;
    }

    $file = $this->getFileFromMedia($media);
    if (!$file instanceof File) {
      return null;
    }

    return [
      'mid' => $media->id(),
      'media_type' => $media->bundle(),
      'media_label' => $media->label(),
      'fid' => $file->id(),
      'filename' => $file->getFilename(),
      'uri' => $file->getFileUri(),
      'absolute_path' => $this->streamWrapperManager->getViaUri($file->getFileUri())->realpath(),
      'url' => $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri()),
      'mime_type' => $file->getMimeType(),
      'size' => $file->getSize(),
      'created' => $media->getCreatedTime(),
      'changed' => $media->getChangedTime(),
    ];
  }

  /**
   * Recupera un array di Media da un campo multi-valore.
   *
   * @param string $fieldName
   * @return array
   */
  public function getMediaItems(string $fieldName): array {
    if (!$this->node || !$this->node->hasField($fieldName)) {
      return [];
    }

    return array_filter(
      $this->node->get($fieldName)->referencedEntities(),
      fn($entity) => $entity instanceof MediaInterface
    );
  }

  /**
   * Recupera un array di paths assoluti da un campo media multi-valore.
   *
   * @param string $fieldName
   * @return array
   */
  public function getMediaFilesAbsolutePaths(string $fieldName): array {
    $paths = [];
    $mediaItems = $this->getMediaItems($fieldName);

    foreach ($mediaItems as $media) {
      $file = $this->getFileFromMedia($media);
      if ($file instanceof File) {
        $uri = $file->getFileUri();
        // $paths[] = $this->streamWrapperManager->getViaUri($uri)->realpath();
        $paths[$media->label()] = $this->fileUrlGenerator->generateAbsoluteString($uri);
      }
    }

    return $paths;
  }
}

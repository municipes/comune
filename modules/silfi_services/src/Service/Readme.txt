// Nel tuo controller o service
$nodeFieldManager = \Drupal::service('your_module.node_field_manager');

// Inizializza con l'ID del nodo e il bundle
if ($nodeFieldManager->initNode($nodeId, 'article')) {
  // Recupera un campo di testo semplice
  $title = $nodeFieldManager->getTextField('field_title');

  // Recupera un campo multi-valore
  $tags = $nodeFieldManager->getMultiTextField('field_tags');

  // Recupera un'entità referenziata
  $author = $nodeFieldManager->getReferencedEntity('field_author');

  // Recupera un campo specifico da un'entità referenziata
  $authorName = $nodeFieldManager->getReferencedEntityField('field_author', 'field_full_name');

  // Recupera campi da multiple entità referenziate
  $categoryNames = $nodeFieldManager->getReferencedEntitiesField('field_categories', 'name');

  // Recupera il path assoluto di un file da un campo media
  $filePath = $nodeFieldManager->getMediaFileAbsolutePath('field_image');
  // Output esempio: /var/www/html/drupal/sites/default/files/images/example.jpg

  // Recupera l'URL del file
  $fileUrl = $nodeFieldManager->getMediaFileUrl('field_image');
  // Output esempio: https://example.com/sites/default/files/images/example.jpg

  // Recupera tutte le informazioni del file e della media
  $mediaInfo = $nodeFieldManager->getMediaFileInfo('field_image');

  // Per campi multi-valore, recupera tutti i path
  $mediaPaths = $nodeFieldManager->getMediaFilesAbsolutePaths('field_gallery');
}


// Recupera il path assoluto di un singolo file
$filePath = $fileManager->getFileAbsolutePath('field_document');
// Esempio output: /var/www/html/drupal/sites/default/files/documents/example.pdf

// Recupera l'URL pubblico del file
$fileUrl = $fileManager->getFileUrl('field_document');
// Esempio output: https://example.com/sites/default/files/documents/example.pdf

// Recupera tutte le informazioni del file
$fileInfo = $fileManager->getFileInfo('field_document');

// Per campi multi-valore, recupera tutti i path assoluti
$filePaths = $fileManager->getFilesAbsolutePaths('field_documents');

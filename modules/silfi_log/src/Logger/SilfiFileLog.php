<?php

namespace Drupal\silfi_log\Logger;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Utility\Token;
use Drupal\silfi_log\FileLogException;
use Drupal\silfi_log\LogFileManagerInterface;
use Drupal\Core\Utility\Error;
use function file_exists;
use function fopen;
use function fwrite;

/**
 * File-based logger.
 */
class SilfiFileLog {

  use DependencySerializationTrait;

  /**
   * The silfi_log settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected Config $config;

  /**
   * The state system, for updating the silfi_log.rotation timestamp.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The token system, for formatting the log messages.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

  /**
   * The log message parser, for formatting the log messages.
   *
   * @var \Drupal\Core\Logger\LogMessageParserInterface
   */
  protected LogMessageParserInterface $parser;

  /**
   * The time system.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * The currently opened log file.
   *
   * @var resource
   */
  protected $logFile;

  /**
   * The STDERR fallback.
   *
   * @var resource
   */
  protected $stderr;

  /**
   * The log-file manager, providing file-handling methods.
   *
   * @var \Drupal\silfi_log\LogFileManagerInterface
   */
  protected LogFileManagerInterface $fileManager;

  /**
   * FileLog constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config.factory service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The datetime.time service.
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The logger.log_message_parser service.
   * @param \Drupal\silfi_log\LogFileManagerInterface $fileManager
   *   The silfi_log.file_manager service.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    StateInterface $state,
    TimeInterface $time,
    LogMessageParserInterface $parser,
    LogFileManagerInterface $fileManager
  ) {
    $this->config = $configFactory->get('silfi_log.settings');
    $this->state = $state;
    $this->time = $time;
    $this->parser = $parser;
    $this->fileManager = $fileManager;
  }

  /**
   * Open the logfile for writing.
   *
   * @return bool
   *   Returns TRUE if the log file is available for writing.
   *
   * @throws \Drupal\silfi_log\FileLogException
   */
  protected function openFile(): bool {
    if ($this->logFile) {
      return TRUE;
    }

    // When creating a new log file, save the creation timestamp.
    $filename = $this->fileManager->getFileName();
    $create = !file_exists($filename);
    if (!$this->fileManager->ensurePath()) {
      $this->logFile = $this->stderr();
      throw new FileLogException('The log directory has disappeared.');
    }
    if ($this->logFile = fopen($filename, 'ab')) {
      if ($create) {
        $this->fileManager->setFilePermissions();
        $this->state->set('silfi_log.rotation', $this->time->getRequestTime());
      }
      return TRUE;
    }

    // Log errors to STDERR until the end of the current request.
    $this->logFile = $this->stderr();
    throw new FileLogException('The logfile could not be opened for writing. Logging to STDERR.');
  }

  /**
   * {@inheritdoc}
   */
  public function log(array $entry): void {
    $log = $this->render($entry);
    try {
      $this->openFile();
      $this->write($log);
    } catch (FileLogException $error) {
      // Log the exception, unless we were already logging a silfi_log error.
      $logger = \Drupal::logger('silfi_log');
      Error::logException($logger, $error);
      // Write the message directly to STDERR.
      fwrite($this->stderr(), $entry . "\n");
    }
  }

  /**
   * Renders a message to a string.
   *
   * @param array $entry
   *   Array of the log message.
   *
   * @return string
   *   The formatted message.
   */
  protected function render(array $entry = []): string {
    $log = '';
    foreach ($entry as $key => $value) {
      $value = $key != 'visitors_date_time' ? '[' . $value . ']' : $value;
      $log .= $value . ' ';
    }

    return PlainTextOutput::renderFromHtml($log);
  }

  /**
   * Open STDERR resource, or use STDERR constant if available.
   *
   * The STDERR constant is not defined in all PHP environments.
   *
   * @return resource
   *   Reference to the STDERR stream resource.
   */
  protected function stderr() {
    if ($this->stderr === NULL) {
      $this->stderr = defined('STDERR') ? STDERR : fopen('php://stderr', 'wb');
    }
    return $this->stderr;
  }

  /**
   * Write an entry to the logfile.
   *
   * @param string $entry
   *   The value to write. This should contain no newline characters.
   *
   * @throws \Drupal\silfi_log\FileLogException
   */
  protected function write(string $entry): void {
    if (!fwrite($this->logFile, $entry . "\n")) {
      throw new FileLogException('The message could not be written to the logfile.');
    }
  }
}

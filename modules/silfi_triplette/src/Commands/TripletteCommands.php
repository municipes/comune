<?php

namespace Drupal\silfi_triplette\Commands;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\silfi_triplette\TripletteImport;
use Drush\Commands\DrushCommands;
use Drush\Utils\StringUtils;

/**
 * Drush 9 triplette commands for Drupal Core 8.4+.
 */
class TripletteCommands extends DrushCommands {

  /**
   * The Triplette manager service.
   *
   * @var \Drupal\TripletteCommands\TripletteImport
   */
  protected $tripletteImport;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * TripletteCommands constructor.
   *
   * @param \Drupal\silfi_triplette\TripletteImport $tripletteImport
   *   Triplette manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(TripletteImport $tripletteImport, MessengerInterface $messenger) {
    parent::__construct();
    $this->tripletteImport = $tripletteImport;
    $this->messenger = $messenger;
  }

  /**
   * Lightweight cron to process Scheduler module tasks.
   *
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @option nomsg
   *   to avoid the "cron completed" message being written to the terminal.
   * @option nolog
   *   to overide the site setting and not write 'started' and 'completed'
   *   messages to the dblog.
   *
   * @command triplette:cron
   * @aliases triplette-cron, trlp-cron
   */
  public function cron(array $options = ['nomsg' => NULL, 'nolog' => NULL]) {
    $this->tripletteImport->runLightweightCron($options);

    $options['nomsg'] ? NULL : $this->messenger->addMessage(dt('Triplette lightweight cron completed.'));
  }

}

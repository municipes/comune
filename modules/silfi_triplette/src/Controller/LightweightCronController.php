<?php

namespace Drupal\silfi_triplette\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\silfi_triplette\TripletteImport;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for the lightweight cron.
 *
 * @package Drupal\silfi_triplette\Controller
 */
class LightweightCronController extends ControllerBase {

  /**
   * The triplette import.
   *
   * @var \Drupal\silfi_triplette\TripletteImport
   */
  protected $tripletteImport;

  /**
   * LightweightCronController constructor.
   *
   * @param \Drupal\silfi_triplette\TripletteImport $tripletteImport
   *   The triplette import.
   */
  public function __construct(TripletteImport $tripletteImport) {
    $this->tripletteImport = $tripletteImport;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('silfi_triplette.import')
    );
  }

  /**
   * Index.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The http response.
   */
  public function index() {
    $this->tripletteImport->runLightweightCron();

    return new Response('', Response::HTTP_NO_CONTENT);
  }

  /**
   * Checks access.
   *
   * @param string $cron_key
   *   The cron key.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access($cron_key) {
    $valid_cron_key = $this->config('silfi_triplette.settings')
      ->get('lightweight_cron_access_key');
    return AccessResult::allowedIf($valid_cron_key == $cron_key);
  }

}

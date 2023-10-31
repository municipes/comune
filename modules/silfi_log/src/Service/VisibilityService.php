<?php

namespace Drupal\silfi_log\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcher;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\user\UserDataInterface;
use Drupal\silfi_log\VisitorsVisibilityInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service for checking visitors visibility.
 */
class VisibilityService implements VisitorsVisibilityInterface {

  /**
   * The config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $path;

  /**
   * The alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcher
   */
  protected $pathMatcher;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The status codes.
   *
   * @var array
   */
  protected $statusCodes;

  /**
   * Constructs a new VisibilityService.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Path\CurrentPathStack $path_current
   *   The current path.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The alias manager.
   * @param \Drupal\Core\Path\PathMatcher $path_matcher
   *   The path matcher.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    CurrentPathStack $path_current,
    AliasManagerInterface $alias_manager,
    PathMatcher $path_matcher,
    ModuleHandlerInterface $module_handler,
    RequestStack $request_stack) {

    $this->config = $config_factory->get('silfi_log.settings');
    $this->path = $path_current;
    $this->aliasManager = $alias_manager;
    $this->pathMatcher = $path_matcher;
    $this->moduleHandler = $module_handler;
    $this->request = $request_stack->getCurrentRequest();
    $this->statusCodes = [
      // "Forbidden" status code.
      '403',
      // "Not Found" status code.
      '404',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible(): bool {
    if ($this->config->get('disable_tracking')) {
      return FALSE;
    }

    // Get page http status code for visibility filtering.
    $status = NULL;
    $exception = $this->request->attributes->get('exception');
    if ($exception) {
      $status = $exception->getStatusCode();
    }

    // Early return if status matches configured values.
    $disabled_status_codes = $this->config->get('status_codes_disabled') ?? [];
    if (in_array($status, $disabled_status_codes)) {
      return FALSE;
    }

    if (in_array($status, $this->statusCodes)) {
      return TRUE;
    }

    return $this->page();
  }

  /**
   * {@inheritdoc}
   */
  public function user(AccountInterface $account): bool {
    $enabled = FALSE;
    return $enabled;
  }

  /**
   * {@inheritdoc}
   */
  public function page(): bool {
    static $page_match;

    // Cache visibility result if function is called more than once.
    if (!isset($page_match)) {
      $visibility_request_path_mode = $this->config->get('visibility.request_path_mode');
      $visibility_request_path_pages = $this->config->get('visibility.request_path_pages');

      // Match path if necessary.
      if (!empty($visibility_request_path_pages)) {
        // Convert path to lowercase. This allows comparison of the same path
        // with different case. Ex: /Page, /page, /PAGE.
        $pages = mb_strtolower($visibility_request_path_pages);
        if ($visibility_request_path_mode < 2) {
          // Compare the lowercase path alias (if any) and internal path.
          $path = $this->path->getPath();
          $path_alias = $this->aliasManager->getAliasByPath($path);
          if (empty($path_alias)) {
            $path_alias = mb_strtolower($path);
          }
          else {
            $path_alias = mb_strtolower($path_alias);
          }
          $page_match = $this->pathMatcher->matchPath($path_alias, $pages)
            || (
              ($path != $path_alias)
              && $this->pathMatcher->matchPath($path, $pages)
            );
          // When $visibility_request_path_mode has a value of 0, the tracking
          // code is displayed on all pages except those listed in $pages. When
          // set to 1, it is displayed only on those pages listed in $pages.
          $page_match = !($visibility_request_path_mode xor $page_match);
        }
        else {
          $page_match = FALSE;
        }
      }
      else {
        $page_match = TRUE;
      }

    }
    return $page_match;
  }

  /**
   * {@inheritdoc}
   */
  public function roles(AccountInterface $account): bool {
    $enabled = FALSE;
    return $enabled;
  }

}

<?php

namespace Drupal\silfi_log\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\SessionConfigurationInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\silfi_log\VisitorsScriptInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Javascript tracking service.
 */
class ScriptService implements VisitorsScriptInterface {
  use StringTranslationTrait;

  /**
   * The config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $path;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The session config.
   *
   * @var \Drupal\Core\Session\SessionConfigurationInterface
   */
  protected $sessionConfig;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRouteMatch;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The config object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Path\CurrentPathStack $path_current
   *   The current path.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Session\SessionConfigurationInterface $session_config
   *   The session config.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $current_route_match
   *   The current route match.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    AccountProxyInterface $current_user,
    RequestStack $request_stack,
    CurrentPathStack $path_current,
    ModuleHandlerInterface $module_handler,
    StateInterface $state,
    SessionConfigurationInterface $session_config,
    CurrentRouteMatch $current_route_match,
    EntityRepositoryInterface $entity_repository,
  ) {
    $this->config = $config_factory->get('silfi_log.settings');
    $this->currentUser = $current_user;
    $this->request = $request_stack->getCurrentRequest();
    $this->path = $path_current->getPath();
    $this->moduleHandler = $module_handler;
    $this->state = $state;
    $this->sessionConfig = $session_config;
    $this->currentRouteMatch = $current_route_match;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function script(): string {
    // Get page http status code for visibility filtering.
    $status = NULL;
    $exception = $this->request->attributes->get('exception');
    if ($exception) {
      $status = $exception->getStatusCode();
    }
    $id = $this->config->get('id_site') ?? '1';

    $url_http = $site_url = $this->request->getScheme() . '://' . $this->request->getHost();
    // $this->config->get('url_http');
    $url_https = $this->config->get('url_https');

    $set_custom_url = '';
    $set_document_title = '';

    // Add link tracking.
    $link_settings = [];
    $link_settings['disableCookies'] = TRUE;
    $link_settings['trackMailto'] = TRUE;

    $page['#attached']['drupalSettings']['silfi_log'] = $link_settings;
    // $page['#attached']['library'][] = 'silfi_log/matomo';
    // Matomo can show a tree view of page titles that represents the site
    // structure if setDocumentTitle() provides the page titles as a "/"
    // delimited list. This may makes it easier to browse through the statistics
    // of page titles on larger sites.

    // Track access denied (403) and file not found (404) pages.
    if ($status == '403') {
      $set_document_title = '"403/URL = " + encodeURIComponent(document.location.pathname+document.location.search) + "/From = " + encodeURIComponent(document.referrer)';
    }
    elseif ($status == '404') {
      $set_document_title = '"404/URL = " + encodeURIComponent(document.location.pathname+document.location.search) + "/From = " + encodeURIComponent(document.referrer)';
    }

    // #2693595: User has entered an invalid login and clicked on forgot
    // password link. This link contains the username or email address and may
    // get send to Matomo if we do not override it. Override only if 'name'
    // query param exists. Last custom url condition, this need to win.
    //
    // URLs to protect are:
    // - user/password?name=username
    // - user/password?name=foo@example.com
    if ($this->currentRouteMatch->getRouteName() == 'user.pass' && $this->request->query->has('name')) {
      $set_custom_url = Json::encode(Url::fromRoute('user.pass')->toString());
    }

    // Build tracker code.
    // @see https://matomo.org/docs/javascript-tracking/#toc-asynchronous-tracking
    $script = 'var _paq = _paq || [];';
    $script .= '(function(){';
    $script .= 'var u=(("https:" == document.location.protocol) ? "' . UrlHelper::filterBadProtocol($url_https) . '" : "' . UrlHelper::filterBadProtocol($url_http) . '");';
    $script .= '_paq.push(["setSiteId", ' . Json::encode($id) . ']);';
    $script .= '_paq.push(["setTrackerUrl", u+"/silfi_log/_track"]);';

    // // Track logged in users across all devices.
    // $user_id = 0;
    // if ($this->config->get('track.userid')) {
    //   $user_id = $this->currentUser->id();
    // }
    // $script .= '_paq.push(["setUserId", ' . $user_id . ']);';
    // Set custom url.
    if (!empty($set_custom_url)) {
      $script .= '_paq.push(["setCustomUrl", ' . $set_custom_url . ']);';
    }
    // Set custom document title.
    if (!empty($set_document_title)) {
      $script .= '_paq.push(["setDocumentTitle", ' . $set_document_title . ']);';
    }

    // Custom file download extensions.
    // if ($this->config->get('track.files') && !($this->config->get('track.files_extensions') == VisitorsScriptInterface::TRACKFILES_EXTENSIONS)) {
      $script .= '_paq.push(["setDownloadExtensions", ' . Json::encode($this->config->get('track.files_extensions')) . ']);';
    // }

    // Disable tracking cookies.
    // if ($this->config->get('privacy.disablecookies')) {
      $script .= '_paq.push(["disableCookies"]);';
    // }

    // // Domain tracking type.
    // $cookie_domain = $this->sessionConfig->getOptions($this->request)['cookie_domain'] ?? '';
    // $domain_mode = $this->config->get('domain_mode');

    // // Per RFC 2109, cookie domains must contain at least one dot other than the
    // // first. For hosts such as 'localhost' or IP Addresses we don't set a
    // // cookie domain.
    // if ($domain_mode == 1 && count(explode('.', $cookie_domain)) > 2 && !is_numeric(str_replace('.', '', $cookie_domain))) {
    //   $script .= '_paq.push(["setCookieDomain", ' . Json::encode($cookie_domain) . ']);';
    // }

    // Site search tracking support.
    // NOTE: It's recommended not to call trackPageView() on the Site Search
    // Result page.
    $keys = ($this->request->query->has('keys') ? trim($this->request->get('keys')) : '');
    if (
      $this->moduleHandler->moduleExists('search') &&
      $this->config->get('track.site_search') &&
      (strpos($this->currentRouteMatch->getRouteName(), 'search.view') === 0) &&
      $keys
      ) {
      // Parameters:
      // 1. Search keyword searched for. Example: "Banana"
      // 2. Search category selected in your search engine. If you do not need
      //    this, set to false. Example: "Organic Food"
      // 3. Number of results on the Search results page. Zero indicates a
      //    'No Result Search Keyword'. Set to false if you don't know.
      //
      // hook_preprocess_search_results() is not executed if search result is
      // empty. Make sure the counter is set to 0 if there are no results.
      $script .= '_paq.push(["trackSiteSearch", ' . Json::encode($keys) . ', false, (window.matomo_search_results) ? window.matomo_search_results : 0]);';
    }
    else {
      $script .= 'if (!window.matomo_search_results_active) {_paq.push(["trackPageView"]);}';
    }

    // Add link tracking.
    // if ($this->config->get('track.files')) {
      // Disable tracking of links with ".no-tracking" and ".colorbox" classes.
      $ignore_classes = [
        'no-tracking',
        'colorbox',
      ];
      // Disable the download & outbound link tracking for specific CSS classes.
      // Custom code snippets with 'setIgnoreClasses' will override the value.
      // @see https://developer.matomo.org/api-reference/tracking-javascript#disable-the-download-amp-outlink-tracking-for-specific-css-classes
      $script .= '_paq.push(["setIgnoreClasses", ' . Json::encode($ignore_classes) . ']);';

      // Enable download & outlink link tracking.
      $script .= '_paq.push(["enableLinkTracking"]);';
    // }

    $script .= 'var d=document,';
    $script .= 'g=d.createElement("script"),';
    $script .= 's=d.getElementsByTagName("script")[0];';
    $script .= 'g.type="text/javascript";';
    $script .= 'g.defer=true;';
    $script .= 'g.async=true;';

    // Should a local cached copy of the tracking code be used?
    // if ($this->config->get('cache')) {
    //   $url = $url_http . '/modules/custom/silfi_log/js/tracker.js';
    //   if ($url) {
    //     // A dummy query-string is added to filenames, to gain control over
    //     // browser-caching. The string changes on every update or full cache
    //     // flush, forcing browsers to load a new copy of the files, as the
    //     // URL changed.
    //     $query_string = '?' . ($this->state->get('system.css_js_query_string') ?: '0');

    //     $script .= 'g.src="' . $url . $query_string . '";';
    //   }
    // }
    // else {
      $script .= 'g.src=u+"/modules/custom/silfi_log/js/tracker.js";';
    // }

    $script .= 's.parentNode.insertBefore(g,s);';
    $script .= '})();';

    return $script;

  }

  /**
   * {@inheritdoc}
   */
  public function cacheTags(array $tags): array {
    $configTags = $this->config->getCacheTags();

    return Cache::mergeTags($tags, $configTags);
  }

}

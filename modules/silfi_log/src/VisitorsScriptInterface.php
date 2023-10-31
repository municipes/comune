<?php

namespace Drupal\silfi_log;

/**
 * Interface for javascript tracking service.
 */
interface VisitorsScriptInterface {

  /**
   * Define the default file extension list that should be tracked as download.
   */
  public const TRACKFILES_EXTENSIONS = '7z|aac|arc|arj|asf|asx|avi|bin|csv|doc(x|m)?|dot(x|m)?|exe|flv|gif|gz|gzip|hqx|jar|jpe?g|js|mp(2|3|4|e?g)|mov(ie)?|msi|msp|pdf|phps|png|ppt(x|m)?|pot(x|m)?|pps(x|m)?|ppam|sld(x|m)?|thmx|qtm?|ra(m|r)?|sea|sit|tar|tgz|torrent|txt|wav|wma|wmv|wpd|xls(x|m|b)?|xlt(x|m)|xlam|xml|z|zip';

  /**
   * Returns the script for the current page.
   *
   * @return string
   *   The script for the current page.
   */
  public function script(): string;

  /**
   * Returns the cache tags.
   *
   * @param string[] $tags
   *   The cache tags for the current page.
   *
   * @return string[]
   *   The new cache tags .
   */
  public function cacheTags(array $tags): array;

}

<?php

namespace Drupal\multisite_helper;

interface MultisiteHelperInterface {

  /**
   * Retrieves the current subsite.
   */
  public function getCurrentSite(): ?MhSubsiteInterface;

  /**
   * Retrieves the current subsite id.
   */
  public function getCurrentSiteId(): ?string;

  /**
   * Retrieves the other subsites' hostnames.
   *
   * @return \Drupal\multisite_helper\MhSubsiteInterface[]
   */
  public function getOtherSubsites(): array;

  /**
   * Sends data to the given set of sites.
   */
  public function sendToSites(string $method, string $plugin_id, array $data, array $sites): bool;

  /**
   * Retrieves the entity processor plugin.
   */
  public function getEntityProcessor(): MultisiteHelperEntityProcessorInterface;

  /**
   * Set the importing flag to the class.
   */
  public static function setImporting(bool $importing = TRUE): void;

  /**
   * Returns the importing flag value.
   */
  public static function isImporting(): bool;

  /**
   * Ping a subsite's URL.
   */
  public static function ping(string $url): ?bool;

}

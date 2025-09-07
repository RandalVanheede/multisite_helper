<?php

namespace Drupal\multisite_helper;

use Drupal\Core\Entity\ContentEntityInterface;

interface MultisiteHelperInterface {

  /**
   * Retrieves the main subsite name.
   */
  public static function getMainSiteName(): string;

  /**
   * Retrieves the current subsite name.
   */
  public static function getCurrentSiteName(): string;

  /**
   * Returns the hostname for a given sitename.
   */
  public static function getHostnameForSite(string $sitename): string|bool;

  /**
   * Retrieves the other subsites' hostnames.
   */
  public static function getOtherSiteHostnames(): array;

  /**
   * Retrieves the other subsites formatted as an options array.
   */
  public static function getOtherSitesAsOptions(): array;

  /**
   * Retrieves a specific setting.
   */
  public static function getSetting(string $key, $default = NULL): mixed;

  /**
   * Sends data to the given set of sites.
   */
  public function sendToSites(string $plugin_id, array $data, array $sites): bool;

  /**
   * REmoves data from the given set of sites.
   */
  public function removeFromSites(string $plugin_id, array $data, array $sites): bool;

  public function importEntity(array $data): bool;

  public function exportEntity(ContentEntityInterface $entity, array $extra_data = []): array;

  public function deleteEntity(array $data): bool;

  /**
   * Set the importing flag to the class.
   */
  public static function setImporting(bool $importing = TRUE): void;

  /**
   * Returns the importing flag value.
   */
  public static function isImporting(): bool;

}

<?php

declare(strict_types=1);

namespace Drupal\multisite_helper;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\multisite_helper\Entity\MhSubsite;

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
   * @param int $status
   *   The status to filter by.
   *
   * @return \Drupal\multisite_helper\MhSubsiteInterface[]
   */
  public function getOtherSubsites(int $status = MhSubsite::ENABLED): array;

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
   *
   * @param string $url
   *   The URL to ping.
   * @param string|null $authorization
   *   Optional base64-encoded authorization string.
   *
   * @return bool
   *   TRUE if the subsite is accessible, FALSE otherwise.
   */
  public function ping(string $url, ?string $authorization = NULL): bool;

}

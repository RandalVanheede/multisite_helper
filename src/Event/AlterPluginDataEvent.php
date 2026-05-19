<?php

declare(strict_types=1);

namespace Drupal\multisite_helper\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @see \Drupal\multisite_helper\Event\MultisiteHelperEvents::ALTER_PLUGIN_DATA
 */
class AlterPluginDataEvent extends Event {

  public function __construct(
    private readonly string $pluginId,
    private array $data,
    private array $sites,
  ) {}

  /**
   * Retrieves the plugin ID.
   */
  public function getPluginId(): string {
    return $this->pluginId;
  }

  /**
   * Retrieves the plugin data.
   */
  public function getPluginData(): array {
    return $this->data;
  }

  /**
   * Sets the plugin data.
   */
  public function setPluginData(array $data): void {
    $this->data = $data;
  }

  /**
   * Retrieves the sites list.
   */
  public function getSites(): array {
    return $this->sites;
  }

  /**
   * Sets the sites list.
   */
  public function setSites(array $sites): void {
    $this->sites = $sites;
  }

}

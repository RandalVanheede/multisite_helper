<?php

declare(strict_types=1);

namespace Drupal\multisite_helper;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Interface for multisite_helper_execution_method plugins.
 */
interface MultisiteHelperExecutionMethodInterface extends ContainerFactoryPluginInterface {

  /**
   * Returns the translated plugin label.
   */
  public function label(): string;

  /**
   * Process the POST/PUT-data.
   */
  public function send(string $plugin_id, array $data, array $sites = []): bool;

  /**
   * Process the DELETE-data.
   */
  public function remove(string $plugin_id, array $data, array $sites = []): bool;

}

<?php

declare(strict_types=1);

namespace Drupal\multisite_helper;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface for multisite_helper_plugin plugins.
 */
interface MultisiteHelperPluginInterface extends PluginFormInterface, ConfigurableInterface, ContainerFactoryPluginInterface {

  /**
   * Returns the translated plugin label.
   */
  public function label(): string;

  /**
   * Returns whether this plugin is enabled.
   */
  public function isEnabled(): bool;

  /**
   * Call this method to create a send job for this plugin with the necessary data.
   *
   * You can optionally provide an array of hostnames to which the data should
   * be sent. If this is left empty, the data will be sent to all other sites.
   */
  public function send(array $data, ?array $sites = NULL): bool;

  /**
   * Call this method to create a remove job for this plugin with the necessary data.
   *
   * You can optionally provide an array of hostnames to which the data should
   * be sent. If this is left empty, the data will be sent to all other sites.
   */
  public function remove(array $data, ?array $sites = NULL): bool;

  /**
   * Processes the incoming data for this plugin.
   */
  public function receive(array $data, string $action): bool;

  /**
   * This flag tells the form to add fields for the processing method choice.
   * If this is set to false, the 'getProcessingMethod' should probably be overridden.
   */
  public function allowProcessingMethodChoice(): bool;

  /**
   * Retrieves the method of processing for this plugin.
   */
  public function getProcessingMethod(): string;

}

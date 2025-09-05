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
   * This flag tells the form to add fields for the processing method choice.
   * If this is set to false, the 'getProcessingMethod' should probably be overridden.
   */
  public function allowProcessingMethodChoice(): bool;

  /**
   * Retrieves the method of processing for this plugin.
   */
  public function getProcessingMethod(): string;

  /**
   * Processes the incoming data for this plugin.
   */
  public function receive(array $data, string $action): bool;

}

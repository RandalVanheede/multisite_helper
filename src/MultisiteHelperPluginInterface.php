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
   * This flag tells the form to add fields for the execution method choice.
   * If this is set to false, the 'getExecutionMethod' should probably be overridden.
   */
  public function allowExecutionMethodChoice(): bool;

  /**
   * Retrieves the method of execution for this plugin.
   */
  public function getExecutionMethod(): string;

  /**
   * Processes the incoming data for this plugin.
   */
  public function receive(array $data, string $action): bool;

}

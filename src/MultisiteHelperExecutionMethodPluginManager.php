<?php

declare(strict_types=1);

namespace Drupal\multisite_helper;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\multisite_helper\Attribute\MultisiteHelperExecutionMethod;

/**
 * MultisiteHelperExecutionMethod plugin manager.
 */
final class MultisiteHelperExecutionMethodPluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/MultisiteHelperExecutionMethod', $namespaces, $module_handler, MultisiteHelperExecutionMethodInterface::class, MultisiteHelperExecutionMethod::class);
    $this->alterInfo('multisite_helper_execution_method_info');
    $this->setCacheBackend($cache_backend, 'multisite_helper_execution_method_plugins');
  }

}

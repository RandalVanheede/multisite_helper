<?php

declare(strict_types=1);

namespace Drupal\multisite_helper;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\multisite_helper\Attribute\MultisiteHelperEntityProcessor;

/**
 * MultisiteHelperEntityProcessor plugin manager.
 */
final class MultisiteHelperEntityProcessorPluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/MultisiteHelperEntityProcessor', $namespaces, $module_handler, MultisiteHelperEntityProcessorInterface::class, MultisiteHelperEntityProcessor::class);
    $this->alterInfo('multisite_helper_entity_processor_info');
    $this->setCacheBackend($cache_backend, 'multisite_helper_entity_processor_plugins');
  }

}

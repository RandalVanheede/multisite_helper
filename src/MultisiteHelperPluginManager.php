<?php

declare(strict_types=1);

namespace Drupal\multisite_helper;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\multisite_helper\Attribute\MultisiteHelperPlugin;

/**
 * MultisiteHelperPlugin plugin manager.
 */
final class MultisiteHelperPluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, private readonly ConfigFactoryInterface $configFactory) {
    parent::__construct('Plugin/MultisiteHelperPlugin', $namespaces, $module_handler, MultisiteHelperPluginInterface::class, MultisiteHelperPlugin::class);
    $this->alterInfo('multisite_helper_plugin_info');
    $this->setCacheBackend($cache_backend, 'multisite_helper_plugins');
  }

  /**
   * Retrieve a configured plugin easily.
   */
  public function getPlugin(string $plugin_id): bool|MultisiteHelperPluginInterface {
    return $this->createInstance($plugin_id, $this->getPluginConfig($plugin_id) ?: []) ?: FALSE;
  }

  /**
   * Retrieve plugin configuration.
   */
  public function getPluginConfig(string $plugin_id): mixed {
    $config = $this->configFactory->get('multisite_helper.settings');
    return $config->get('plugins.' . $plugin_id);
  }

}

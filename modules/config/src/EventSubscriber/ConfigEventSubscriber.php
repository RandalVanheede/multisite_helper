<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_config\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\multisite_helper\MultisiteHelper;
use Drupal\multisite_helper\MultisiteHelperPluginManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ConfigEventSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly MultisiteHelperPluginManager $pluginManager,
  ) {}

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::SAVE => 'onConfigSave',
    ];
  }

  /**
   * Syncs config objects to subsites when they are saved.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    if (MultisiteHelper::isImporting()) {
      return;
    }

    /** @var \Drupal\multisite_helper_config\Plugin\MultisiteHelperPlugin\ConfigSync $plugin */
    $plugin = $this->pluginManager->getPlugin('config_sync');
    if (!$plugin->isEnabled()) {
      return;
    }

    $config = $event->getConfig();
    $config_name = $config->getName();

    if (!$plugin->isConfigAllowed($config_name)) {
      return;
    }

    $plugin->send([
      'config_name' => $config_name,
      'config_data' => $config->getRawData(),
    ]);
  }

}

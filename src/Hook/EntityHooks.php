<?php

namespace Drupal\multisite_helper\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\multisite_helper\MultisiteHelperInterface;
use Drupal\multisite_helper\MultisiteHelperPluginManager;

class EntityHooks {

  public function __construct(
    private readonly MultisiteHelperPluginManager $pluginManager,
    private readonly MultisiteHelperInterface $helper,
  ) {}

  #[Hook('entity_insert')]
  #[Hook('entity_update')]
  public function save(EntityInterface $entity): void {
    $this->doSend($entity, 'send');
  }

  #[Hook('entity_delete')]
  public function delete(EntityInterface $entity): void {
    $this->doSend($entity, 'remove');
  }

  /**
   * Sends the required data to the send/remove endpoint.
   */
  public function doSend(EntityInterface $entity, string $action): void {
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }

    /** @var \Drupal\multisite_helper\Plugin\MultisiteHelperPlugin\ContentEntitySync $plugin */
    $plugin = $this->pluginManager->getPlugin('content_entity_sync');
    $plugin_config = $plugin->getConfiguration();
    if (empty($plugin_config['enabled'])) {
      return;
    }

    if (!in_array($entity->getEntityTypeId(), $plugin_config['entity_types'])) {
      return;
    }

    $entity_values = $this->helper->getEntityProcessor()->exportEntity($entity);
    $plugin->{$action}($entity_values);
  }

}

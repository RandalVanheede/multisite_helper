<?php

namespace Drupal\multisite_helper\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\multisite_helper\MultisiteHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class MultisiteHelperQueueWorkerBase extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  protected readonly MultisiteHelperInterface $helper;

  protected readonly EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->helper = $container->get('multisite_helper');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * Loads multiple subsite IDs.
   *
   * @return \Drupal\multisite_helper\MhSubsiteInterface[]
   */
  protected function loadSubsiteEntities(array $site_ids): array {
    return $this->entityTypeManager
      ->getStorage('mh_subsite')
      ->loadMultiple($site_ids);
  }

}

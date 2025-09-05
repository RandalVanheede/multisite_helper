<?php

declare(strict_types=1);

namespace Drupal\multisite_helper\Plugin\MultisiteHelperProcessingMethod;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\multisite_helper\Attribute\MultisiteHelperProcessingMethod;
use Drupal\multisite_helper\MultisiteHelperProcessingMethodPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[MultisiteHelperProcessingMethod(
  id: 'queue',
  label: new TranslatableMarkup('Queue (Drush)'),
  description: new TranslatableMarkup('Create queue items which need to be processed by executing the "multisite_helper:process-queue-items"-drush command.'),
)]
final class Queue extends MultisiteHelperProcessingMethodPluginBase {

  private readonly QueueFactory $queueFactory;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->queueFactory = $container->get('queue');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function send(string $plugin_id, array $data, array $sites = []): bool {
    $this->queueFactory
      ->get('multisite_helper_send_data')
      ->createItem([
        'plugin_id' => $plugin_id,
        'plugin_data' => $data,
        'sites' => $sites,
      ]);

    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function remove(string $plugin_id, array $data, array $sites = []): bool {
    $this->queueFactory
      ->get('multisite_helper_remove_data')
      ->createItem([
        'plugin_id' => $plugin_id,
        'plugin_data' => $data,
        'sites' => $sites,
      ]);

    return TRUE;
  }

}


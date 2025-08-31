<?php

declare(strict_types=1);

namespace Drupal\multisite_helper\Plugin\MultisiteHelperExecutionMethod;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\multisite_helper\Attribute\MultisiteHelperExecutionMethod;
use Drupal\multisite_helper\MultisiteHelperExecutionMethodPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[MultisiteHelperExecutionMethod(
  id: 'cron',
  label: new TranslatableMarkup('Cron (queue)'),
  description: new TranslatableMarkup('Create queue items and process them on queue.'),
)]
final class Cron extends MultisiteHelperExecutionMethodPluginBase {

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
      ->get('multisite_helper_send_data_cron')
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
      ->get('multisite_helper_remove_data_cron')
      ->createItem([
        'plugin_id' => $plugin_id,
        'plugin_data' => $data,
        'sites' => $sites,
      ]);

    return TRUE;
  }

}


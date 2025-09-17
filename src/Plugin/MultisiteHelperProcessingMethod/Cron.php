<?php

declare(strict_types=1);

namespace Drupal\multisite_helper\Plugin\MultisiteHelperProcessingMethod;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\multisite_helper\Attribute\MultisiteHelperProcessingMethod;
use Drupal\multisite_helper\MhSubsiteInterface;
use Drupal\multisite_helper\MultisiteHelperProcessingMethodPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[MultisiteHelperProcessingMethod(
  id: 'cron',
  label: new TranslatableMarkup('Cron (queue)'),
  description: new TranslatableMarkup('Create queue items and process them on every cron run.'),
)]
final class Cron extends MultisiteHelperProcessingMethodPluginBase {

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
    $sites = array_map(static function (MhSubsiteInterface $site) {
      return $site->id();
    }, $sites);

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
    $sites = array_map(static function (MhSubsiteInterface $site) {
      return $site->id();
    }, $sites);

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


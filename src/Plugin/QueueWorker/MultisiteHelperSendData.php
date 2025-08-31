<?php

namespace Drupal\multisite_helper\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\multisite_helper\MultisiteHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[QueueWorker(
  id: 'multisite_helper_send_data',
  title: new TranslatableMarkup('Send Multisite Helper plugin data'),
)]
class MultisiteHelperSendData extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly MultisiteHelperInterface $helper,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('multisite_helper'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function processItem($data) {
    assert(
      isset($data['plugin_id'], $data['plugin_data'], $data['sites']),
      'Not all required parameters for this queue worker are available.',
    );

    $plugin_id = $data['plugin_id'];
    $plugin_data = $data['plugin_data'];
    $sites = $data['sites'];

    $this->helper->sendToSites($plugin_id, $plugin_data, $sites);
  }

}

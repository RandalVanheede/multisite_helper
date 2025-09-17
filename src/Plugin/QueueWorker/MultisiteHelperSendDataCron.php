<?php

namespace Drupal\multisite_helper\Plugin\QueueWorker;

use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[QueueWorker(
  id: 'multisite_helper_send_data_cron',
  title: new TranslatableMarkup('Send Multisite Helper plugin data'),
  cron: ['time' => 60],
)]
class MultisiteHelperSendDataCron extends MultisiteHelperQueueWorkerBase {

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
    $sites = $this->loadSubsiteEntities($data['sites']);

    $this->helper->sendToSites('POST', $plugin_id, $plugin_data, $sites);
  }

}

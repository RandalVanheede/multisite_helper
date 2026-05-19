<?php

declare(strict_types=1);

namespace Drupal\multisite_helper\Plugin\QueueWorker;

use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[QueueWorker(
  id: 'multisite_helper_send_data',
  title: new TranslatableMarkup('Send Multisite Helper plugin data'),
)]
class MultisiteHelperSendData extends MultisiteHelperQueueWorkerBase {

  /**
   * {@inheritDoc}
   */
  public function processItem($data) {
    $this->validateData($data);

    $plugin_id = $data['plugin_id'];
    $plugin_data = $data['plugin_data'];
    $sites = $this->loadSubsiteEntities($data['sites']);

    $this->helper->sendToSites('POST', $plugin_id, $plugin_data, $sites);
  }

}

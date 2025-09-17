<?php

namespace Drupal\multisite_helper\Plugin\QueueWorker;

use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[QueueWorker(
  id: 'multisite_helper_remove_data',
  title: new TranslatableMarkup('Remove Multisite Helper plugin data'),
)]
class MultisiteHelperRemoveData extends MultisiteHelperQueueWorkerBase {

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

    $this->helper->sendToSites('DELETE', $plugin_id, $plugin_data, $sites);
  }

}

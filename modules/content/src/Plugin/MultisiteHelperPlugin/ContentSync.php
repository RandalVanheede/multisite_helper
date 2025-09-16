<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_content\Plugin\MultisiteHelperPlugin;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\multisite_helper\Attribute\MultisiteHelperPlugin;
use Drupal\multisite_helper\MultisiteHelperPluginBase;

/**
 * Plugin implementation of the multisite_helper_plugin.
 */
#[MultisiteHelperPlugin(
  id: 'content_sync',
  label: new TranslatableMarkup('Content synchronization'),
  description: new TranslatableMarkup('Configure the content synchronization settings.'),
  handles_entity_type: 'node',
)]
final class ContentSync extends MultisiteHelperPluginBase {

  /**
   * {@inheritDoc}
   */
  public function receive(array $data, string $action): bool {
    ['uuid' => $uuid] = $this->helper->getEntityBaseInformation($data);

    $node = $this->entityRepository->loadEntityByUuid('node', $uuid);
    if ($node) {
      // If sync checkbox is turned off, we can't process this item.
      if (!$node->get('mh_sync')->value) {
        return FALSE;
      }
    }

    return parent::receive($data, $action);
  }

}

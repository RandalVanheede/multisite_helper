<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_content\Plugin\MultisiteHelperPlugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\multisite_helper\Attribute\MultisiteHelperPlugin;
use Drupal\multisite_helper\MultisiteHelperPluginBase;
use Drupal\user\RoleInterface;

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
    /** @var \Drupal\node\NodeStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $nodes = $storage->loadByProperties(['uuid' => $data['uuid']]);
    if ($nodes && ($node = reset($nodes))) {
      // If sync checkbox is turned off, we can't process this item.
      if (!$node->get('mh_sync')->value) {
        return FALSE;
      }
    }

    return parent::receive($data, $action);
  }

}

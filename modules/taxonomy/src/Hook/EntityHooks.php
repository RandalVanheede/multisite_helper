<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_taxonomy\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\multisite_helper\MultisiteHelperInterface;
use Drupal\multisite_helper\MultisiteHelperPluginManager;
use Drupal\taxonomy\TermInterface;

class EntityHooks {

  public function __construct(
    private readonly MultisiteHelperPluginManager $pluginManager,
    private readonly MultisiteHelperInterface $helper,
  ) {}

  #[Hook('taxonomy_term_insert')]
  #[Hook('taxonomy_term_update')]
  public function save(TermInterface $term): void {
    $this->doSend($term, 'send');
  }

  #[Hook('taxonomy_term_delete')]
  public function delete(TermInterface $term): void {
    $this->doSend($term, 'remove');
  }

  /**
   * Sends the required data to the send/remove endpoint.
   */
  public function doSend(TermInterface $term, string $action): void {
    /** @var \Drupal\multisite_helper_taxonomy\Plugin\MultisiteHelperPlugin\TermSync $plugin */
    $plugin = $this->pluginManager->getPlugin('term_sync');
    if (!$plugin->isEnabled()) {
      return;
    }

    // Check if the term is part of the configured vocabularies.
    $plugin_config = $plugin->getConfiguration();
    $configured_vids = array_filter($plugin_config['vids']);
    if (!empty($configured_vids) && !in_array($term->bundle(), $configured_vids)) {
      return;
    }

    $extra_data = [
      'status' => [['value' => (int) $term->isPublished()]],
    ];
    $term_values = $this->helper->getEntityProcessor()->exportEntity($term, $extra_data);
    $plugin->{$action}($term_values);
  }

}

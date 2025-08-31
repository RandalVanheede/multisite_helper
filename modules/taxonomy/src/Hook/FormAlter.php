<?php

namespace Drupal\multisite_helper_taxonomy\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\multisite_helper\MultisiteHelperPluginManager;
use Drupal\single_content_sync\ContentExporterInterface;

class FormAlter {

  public function __construct(
    private readonly MultisiteHelperPluginManager $pluginManager,
    private readonly ContentExporterInterface $contentExporter,
  ) {}

  #[Hook('form_taxonomy_term_form_alter')]
  public function termFormAlter(array &$form, FormStateInterface $form_state): void {
    $form['actions']['submit']['#submit'][] = [$this, 'termFormSubmit'];
    if (isset($form['actions']['overview'])) {
      $form['actions']['overview']['#submit'][] = [$this, 'termFormSubmit'];
    }
  }

  /**
   * Send this node's data to other subsites.
   */
  public function termFormSubmit(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\multisite_helper_taxonomy\Plugin\MultisiteHelperPlugin\TermSync $plugin */
    $plugin = $this->pluginManager->getPlugin('term_sync');
    $plugin_config = $plugin->getConfiguration();
    if (empty($plugin_config['enabled'])) {
      return;
    }

    /** @var \Drupal\taxonomy\TermInterface $term */
    $term = $form_state->getFormObject()->getEntity();

    // Check if the term is part of the configured vocabularies.
    $configured_vids = array_filter($plugin_config['vids']);
    if (!empty($configured_vids) && !in_array($term->bundle(), $configured_vids)) {
      return;
    }

    $term_values = $this->contentExporter->doExportToArray($term);
    $term_values['custom_fields']['status'] = [['value' => (int) $term->isPublished()]];
    $plugin->send($term_values);
  }

}

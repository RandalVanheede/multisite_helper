<?php

namespace Drupal\multisite_helper_content\Hook;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\multisite_helper\MultisiteHelper;
use Drupal\multisite_helper\MultisiteHelperPluginManager;
use Drupal\single_content_sync\ContentExporterInterface;

class FormAlter {

  use StringTranslationTrait;

  public function __construct(
    private readonly MultisiteHelperPluginManager $pluginManager,
    private readonly ContentExporterInterface $contentExporter,
  ) {}

  #[Hook('form_node_form_alter')]
  public function nodeFormAlter(array &$form, FormStateInterface $form_state): void {
    $form['actions']['submit']['#submit'][] = [$this, 'nodeFormSubmit'];
    $form['#validate'][] = [$this, 'nodeFormValidate'];
    $this->addSyncFields($form, $form_state);
  }

  public function nodeFormValidate(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $form_state->getFormObject()->getEntity();
    $current_sites = array_column($node->get('mh_sites')->getValue(), 'value');
    $new_sites = array_column($form_state->getValues()['mh_sites'] ?? [], 'value');

    $deleted_sites = array_diff($current_sites, $new_sites);
    $form_state->setValue('mh_sites_deleted', $deleted_sites);
  }

  /**
   * Send this node's data to other subsites.
   */
  public function nodeFormSubmit(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\multisite_helper_content\Plugin\MultisiteHelperPlugin\ContentEntitySync $plugin */
    $plugin = $this->pluginManager->getPlugin('content_sync');
    $plugin_config = $plugin->getConfiguration();
    if (empty($plugin_config['enabled'])) {
      return;
    }

    /** @var \Drupal\node\NodeInterface $node */
    $node = $form_state->getFormObject()->getEntity();

    // Check if this website is the source website of the item, or if the sync
    // is turned off for this item.
    if (!$node->get('mh_source')->isEmpty() || !$node->get('mh_sync')->value) {
      return;
    }

    $form_values = $form_state->getValues();
    $sites = array_map(static function ($item) {
      return MultisiteHelper::getHostnameForSite($item['value']);
    }, $form_values['mh_sites']);
    $deleted_sites = array_map(static function ($item) {
      return MultisiteHelper::getHostnameForSite($item);
    }, $form_values['mh_sites_deleted']);

    $node_values = $this->contentExporter->doExportToArray($node);
    $node_values['custom_fields']['mh_sync'] = [['value' => 1]];
    $node_values['custom_fields']['mh_source'] = [['value' => MultisiteHelper::getCurrentSiteName()]];
    $plugin->send($node_values, $sites);
    if ($deleted_sites) {
      $plugin->remove($node_values, $deleted_sites);
    }
  }

  /**
   * Adds the relevant sync fields to the node form.
   */
  private function addSyncFields(array &$form, FormStateInterface $form_state): void {
    if (!isset($form['mh_sync'])) {
      unset($form['mh_sites']);
      return;
    }

    /** @var \Drupal\node\NodeInterface $node */
    $node = $form_state->getFormObject()->getEntity();

    $form['mh_settings'] = [
      '#type' => 'details',
      '#title' => (string) $this->t('Multisite synchronization'),
      '#group' => 'advanced',
    ];

    $is_synced = $node->get('mh_sync')->value;
    if ($is_synced) {
      $form['mh_settings']['#title'] .= $this->t(' (Synchronized)');
    }

    $form['mh_sync']['#group'] = 'mh_settings';
    $form['mh_sites']['#group'] = 'mh_settings';
    $form['mh_source']['#group'] = 'mh_settings';
    $form['mh_source']['#access'] = FALSE;

    $form['mh_sites']['#states'] = [
      'visible' => [
        ':input[name="mh_sync[value]"]' => ['checked' => TRUE],
      ],
    ];

    if (!$node->get('mh_source')->isEmpty() && ($source_site = $node->get('mh_source')->getString())) {
      $form['mh_sync']['widget']['value']['#title'] = $this->t('Lock to source website');
      $form['mh_sync']['widget']['value']['#description'] = $this->t('Uncheck this box to unlock this content from its main site. You will not be able to send this item to other subsites after unlocking, you <strong>can</strong> lock it again to be synchronized again if needed.');
      $form['mh_sites']['#access'] = FALSE;

      $form['mh_source_label'] = [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => new FormattableMarkup(
          'Edit this item on the source website: <a href=":link">:title</a>',
          [
            // @todo : redirect to node edit page?
            ':link' => 'https://' . MultisiteHelper::getHostnameForSite($source_site),
            ':title' => $source_site,
          ],
        ),
      ];

      // Remove all irrelevant fields while the node is locked.
      if ($is_synced) {
        $skip_fields = ['advanced', 'actions', 'footer', 'meta'];
        foreach (Element::children($form) as $field_name) {
          if (str_starts_with($field_name, 'mh_') || str_starts_with($field_name, 'form_')) {
            continue;
          }

          if (in_array($field_name, $skip_fields)) {
            continue;
          }

          $form[$field_name]['#access'] = FALSE;
        }
      }
    }
  }

}

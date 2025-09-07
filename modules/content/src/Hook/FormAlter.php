<?php

namespace Drupal\multisite_helper_content\Hook;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\multisite_helper\MultisiteHelper;
use Drupal\multisite_helper\MultisiteHelperPluginManager;

class FormAlter {

  use StringTranslationTrait;

  public function __construct(
    private readonly MultisiteHelperPluginManager $pluginManager,
  ) {}

  #[Hook('form_node_form_alter')]
  public function nodeFormAlter(array &$form, FormStateInterface $form_state): void {
    $form['#validate'][] = [$this, 'nodeFormValidate'];
    $this->addSyncFields($form, $form_state);
  }

  /**
   * Validate the node entity.
   */
  public function nodeFormValidate(array &$form, FormStateInterface $form_state): void {
    if (!($form_state->getValue('mh_sync')['value'] ?? NULL)) {
      $form_state->setValue('mh_sites', []);
      $form_state->setValue('mh_sync_menu_link', []);
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
      $form['mh_settings']['#open'] = TRUE;
    }

    $form['mh_sync']['#group'] = 'mh_settings';
    $form['mh_sites']['#group'] = 'mh_settings';
    $form['mh_source']['#group'] = 'mh_settings';
    $form['mh_sync_menu_link']['#group'] = 'mh_settings';
    $form['mh_source']['#access'] = FALSE;

    $form['mh_sites']['#states'] = [
      'visible' => [
        ':input[name="mh_sync[value]"]' => ['checked' => TRUE],
      ],
    ];

    $form['mh_sync_menu_link']['#states'] = [
      'visible' => [
        ':input[name="mh_sync[value]"]' => ['checked' => TRUE],
        'and',
        ':input[name="menu[enabled]"]' => ['checked' => TRUE],
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
        if (!$node->get('mh_sync_menu_link')->value) {
          $skip_fields[] = 'menu';
        }
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

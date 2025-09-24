<?php

namespace Drupal\multisite_helper_content\Hook;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\multisite_helper\Entity\MhSubsite;
use Drupal\multisite_helper\MhSubsiteInterface;
use Drupal\multisite_helper\MultisiteHelperInterface;
use Drupal\multisite_helper\MultisiteHelperPluginManager;
use GuzzleHttp\RequestOptions;

class FormAlter {

  private const BUNDLE_CACHE_PREFIX = 'mh_subsite_content_bundles.';

  use StringTranslationTrait;

  use DependencySerializationTrait;

  private ImmutableConfig $config;

  public function __construct(
    private readonly MultisiteHelperPluginManager $pluginManager,
    private readonly MultisiteHelperInterface $helper,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly CacheBackendInterface $cache,
  ) {
    $this->config = $this->configFactory->get('multisite_helper.settings');
  }

  #[Hook('form_node_form_alter')]
  public function nodeFormAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\multisite_helper_content\Plugin\MultisiteHelperPlugin\ContentSync $plugin */
    $plugin = $this->pluginManager->getPlugin('content_sync');
    $plugin_config = $plugin->getConfiguration();
    if (empty($plugin_config['enabled'])) {
      foreach (['mh_sync', 'mh_sites', 'mh_source', 'mh_sync_menu_link'] as $field_name) {
        if (isset($form[$field_name])) {
          $form[$field_name]['#access'] = FALSE;
        }
      }
      return;
    }

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

    // Don't allow deploying to the current subsite, and list the options by weight.
    $form['mh_sites']['widget']['#options'] = [];
    $current_site = $this->helper->getCurrentSiteId();
    $storage = $this->entityTypeManager->getStorage('mh_subsite');
    $subsite_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', MhSubsite::ENABLED)
      ->condition('id', $current_site, '<>')
      ->sort('weight')
      ->execute();

    foreach ($subsite_ids as $subsite_id) {
      /** @var \Drupal\multisite_helper\MhSubsiteInterface $subsite */
      $subsite = $storage->load($subsite_id);
      $form['mh_sites']['widget']['#options'][$subsite_id] = $subsite->label();
      if (!in_array($node->bundle(), $this->getBundlesForSite($subsite))) {
        $form['mh_sites']['widget'][$subsite_id]['#description'] =
          $this->t('The content type is disabled for this subsite.');
        $form['mh_sites']['widget'][$subsite_id]['#disabled'] = TRUE;
      }
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

    if ($source_site = $node->get('mh_source')->entity) {
      $form['mh_sync']['widget']['value']['#title'] = $this->t('Lock to source website');
      $form['mh_sync']['widget']['value']['#description'] = $this->t('Uncheck this box to unlock this content from its main site. You will not be able to send this item to other subsites after unlocking, you <strong>can</strong> lock it again to be synchronized again if needed.');
      $form['mh_sites']['#access'] = FALSE;

      $relative_url = Url::fromRoute('multisite_helper_content.uuid_redirect', [
        'uuid' => $node->uuid(),
        'operation' => 'edit',
      ])->toString();
      $form['mh_source_label'] = [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => new FormattableMarkup(
          'Edit this item on the source website: <a href=":link">:title</a>',
          [
            ':link' => $source_site->url() . $relative_url,
            ':title' => $source_site->label(),
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

  /**
   * Retrieves the active content types for a given subsite.
   */
  private function getBundlesForSite(MhSubsiteInterface $subsite): array {
    if (!$cached_bundles = $this->cache->get(self::BUNDLE_CACHE_PREFIX . $subsite->id())) {
      $bundles_path = Url::fromRoute('multisite_helper_content.bundles')->toString();

      $request = \Drupal::httpClient()->get($subsite->url() . $bundles_path, [
        RequestOptions::HEADERS => array_filter([
          'X-Api-Key' => $this->config->get('api_key'),
          'Authorization' => ($auth = $subsite->authorization())
            ? 'Basic ' . $auth
            : NULL,
        ]),
      ]);

      $bundle_info = json_decode($request->getBody()->getContents(), TRUE);
      // Cache the bundles for the next hour.
      $this->cache->set(self::BUNDLE_CACHE_PREFIX . $subsite->id(), array_keys($bundle_info), time() + 3600);

      $cached_bundles = new \stdClass();
      $cached_bundles->data = array_keys($bundle_info);
    }
    return $cached_bundles->data;
  }

}

<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_content\Plugin\MultisiteHelperPlugin;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\multisite_helper\Attribute\MultisiteHelperPlugin;
use Drupal\multisite_helper\MultisiteHelperPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

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

  private readonly EntityTypeBundleInfoInterface $bundleInfo;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->bundleInfo = $container->get('entity_type.bundle.info');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + [
        'content_types' => [],
      ];
  }

  /**
   * @inheritDoc
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $bundles = array_map(static function ($bundle) {
      return $bundle['label'] ?? NULL;
    }, $this->bundleInfo->getBundleInfo('node'));

    $form['content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Vocabularies to synchronize'),
      '#description' => $this->t('Select the content types that need to be synchronized, leave empty to synchronize content of all types.'),
      '#default_value' => $this->configuration['content_types'],
      '#options' => $bundles,
      '#states' => [
        'visible' => [
          ':input[name="plugins[content_sync][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function receive(array $data, string $action): bool {
    [
      'uuid' => $uuid,
      'bundle' => $bundle,
    ] = $this->helper->getEntityProcessor()->getEntityBaseInformation($data);

    // Check if this content type is allowed to synchronize.
    if (!$this->isBundleAllowed($bundle)) {
      return FALSE;
    }

    $node = $this->entityRepository->loadEntityByUuid('node', $uuid);
    if ($node) {
      // If sync checkbox is turned off, we can't process this item.
      if (!$node->get('mh_sync')->value) {
        return FALSE;
      }
    }

    return parent::receive($data, $action);
  }

  /**
   * Checks whether the given bundle is allowed to synchronize.
   */
  public function isBundleAllowed(string $bundle): bool {
    $allowed_bundles = array_filter($this->configuration['content_types'] ?? []);
    return empty($allowed_bundles) || in_array($bundle, $allowed_bundles);
  }

}

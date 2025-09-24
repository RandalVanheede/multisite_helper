<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_taxonomy\Plugin\MultisiteHelperPlugin;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\multisite_helper\Attribute\MultisiteHelperPlugin;
use Drupal\multisite_helper\MultisiteHelperPluginBase;
use Drupal\taxonomy\VocabularyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the multisite_helper_plugin.
 */
#[MultisiteHelperPlugin(
  id: 'term_sync',
  label: new TranslatableMarkup('Taxonomy term synchronization'),
  description: new TranslatableMarkup('Configure the taxonomy term synchronization settings.'),
  handles_entity_type: 'taxonomy_term',
)]
final class TermSync extends MultisiteHelperPluginBase {

  private readonly EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + [
      'vids' => [],
    ];
  }

  /**
   * @inheritDoc
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $vids = array_map(static function (VocabularyInterface $vocabulary) {
      return $vocabulary->label();
    }, $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple());

    $form['vids'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Vocabularies to synchronize'),
      '#description' => $this->t('Select the vocabularies that need to be synchronized, leave empty to synchronize terms from all vocabularies.'),
      '#default_value' => $this->configuration['vids'],
      '#options' => $vids,
      '#states' => [
        'visible' => [
          ':input[name="plugins[term_sync][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

}

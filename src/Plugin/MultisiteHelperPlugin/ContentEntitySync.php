<?php

declare(strict_types=1);

namespace Drupal\multisite_helper\Plugin\MultisiteHelperPlugin;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\multisite_helper\Attribute\MultisiteHelperPlugin;
use Drupal\multisite_helper\MultisiteHelperPluginBase;
use Drupal\multisite_helper\MultisiteHelperPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the multisite_helper_plugin.
 */
#[MultisiteHelperPlugin(
  id: 'content_entity_sync',
  label: new TranslatableMarkup('Content entity synchronization'),
  description: new TranslatableMarkup('Configure the content synchronization settings.'),
  weight: 10,
)]
final class ContentEntitySync extends MultisiteHelperPluginBase {

  private readonly EntityTypeManagerInterface $entityTypeManager;
  private readonly MultisiteHelperPluginManager $pluginManager;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->pluginManager = $container->get('plugin.manager.multisite_helper_plugin');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'entity_types' => [],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $definitions = array_filter($this->entityTypeManager->getDefinitions(), static function ($entity_type) {
      return $entity_type instanceof ContentEntityTypeInterface;
    });
    $entity_type_options = array_map(static function (ContentEntityTypeInterface $entity_type) {
      return $entity_type->getLabel() . ' <small>(module: ' . $entity_type->getProvider() . ')</small>';
    }, $definitions);

    asort($entity_type_options);

    $form['entity_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Entity types'),
      '#description' => $this->t("Choose which content entity types should be synchronized to all subsites.<br>
It's possible a more specific plugin exists for some entity types that provides more in depth functionality, please check the <a href=\":link\" target=\"_blank\">module list.</a><br>
<strong>Attention:</strong> entities of other entity types might be synchronized if they're referenced by one of the selected entity types.", [
        ':link' => Url::fromRoute('system.modules_list')->setOption('fragment', 'edit-modules-multisite')->toString(),
      ]),
      '#description_display' => 'before',
      '#options' => $entity_type_options,
      '#default_value' => $this->configuration['entity_types'],
      '#states' => [
        'visible' => [
          ':input[name="plugins[content_entity_sync][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $plugin_definitions = array_filter($this->pluginManager->getDefinitions(), static function ($plugin_definition) {
      return !empty($plugin_definition['handles_entity_type']);
    });
    $plugin_provided_entity_types = [];
    foreach ($plugin_definitions as $plugin_definition) {
      $plugin_provided_entity_types[$plugin_definition['handles_entity_type']] = $plugin_definition['provider'];
    }

    foreach ($plugin_provided_entity_types as $entity_type_id => $provider) {
      $form['entity_types'][$entity_type_id] = [
        '#disabled' => TRUE,
        '#description' => $this->t('Specific plugin provided by module: @provider', ['@provider' => $provider]),
      ];
      // Remove from active content types.
      unset($form['entity_types']['#default_value'][$entity_type_id]);
    }


    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    $values = &$form_state->getValues();

    if (empty($values['enabled'])) {
      return;
    }

    $values['entity_types'] = $values['entity_types'] ?? [];
    foreach ($values['entity_types'] as $entity_type) {
      if (!empty($form['plugins'][$this->getPluginId()]['entity_types']['#disabled'])) {
        unset($values['entity_types'][$entity_type]);
      }
    }

    if (empty(array_filter($values['entity_types'] ?? []))) {
      $form_state->setError(
        $form['plugins'][$this->getPluginId()]['entity_types'],
        $this->t('You must select at least one entity type.'),
      );
    }
  }

}

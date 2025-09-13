<?php

declare(strict_types=1);

namespace Drupal\multisite_helper;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\Plugin\PluginFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for multisite_helper_plugin plugins.
 */
abstract class MultisiteHelperPluginBase extends PluginBase implements MultisiteHelperPluginInterface {

  use StringTranslationTrait;

  use PluginFormTrait;

  const DEFAULT_PROCESSING_METHOD = 'cron';

  /**
   * Constructs a \Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected QueueFactory $queueFactory,
    protected MultisiteHelperInterface $helper,
    protected EntityRepositoryInterface $entityRepository,
    protected MultisiteHelperProcessingMethodPluginManager $processingMethodPluginManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('queue'),
      $container->get('multisite_helper'),
      $container->get('entity.repository'),
      $container->get('plugin.manager.multisite_helper_processing_method'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritDoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritDoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritDoc}
   */
  public function allowProcessingMethodChoice(): bool {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function getProcessingMethod(): string {
    return $this->configuration['processing_method'] ?? static::DEFAULT_PROCESSING_METHOD;
  }

  /**
   * {@inheritDoc}
   */
  public function defaultConfiguration() {
    return array_filter([
      'enabled' => FALSE,
      'processing_method' => $this->allowProcessingMethodChoice() ? static::DEFAULT_PROCESSING_METHOD : NULL,
    ]);
  }

  /**
   * {@inheritDoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable "@plugin"', ['@plugin' => $this->pluginDefinition['label']]),
      '#default_value' => $this->configuration['enabled'] ?? FALSE,
    ];

    if ($this->allowProcessingMethodChoice()) {
      $processing_methods = $this->processingMethodPluginManager->getDefinitions();

      $form['processing_method'] = [
        '#type' => 'radios',
        '#title' => $this->t('Processing method'),
        '#description' => $this->t('Choose how the plugin should send its data to the selected subsites.'),
        '#description_display' => 'before',
        '#options' => array_map(static function ($definition) {
          return $definition['label'];
        }, $processing_methods),
        '#default_value' => static::getProcessingMethod(),
        '#required' => TRUE,
        '#states' => [
          'visible' => [
            ':input[name="plugins[' . $this->getPluginId() . '][enabled]"]' => ['checked' => TRUE],
          ],
        ],
      ];

      foreach ($processing_methods as $plugin_id => $plugin_definition) {
        $form['processing_method'][$plugin_id]['#description'] = $plugin_definition['description'];
      }
    }

    return $form;
  }

  /**
   * Call this method to create a send job for this plugin with the necessary data.
   *
   * You can optionally provide an array of hostnames to which the data should
   * be sent. If this is left empty, the data will be sent to all other sites.
   */
  public function send(array $data, ?array $sites = NULL): bool {
    if (MultisiteHelper::isImporting()) {
      return FALSE;
    }

    $sites = $sites === NULL
      ? $this->helper->getOtherSiteHostnames()
      : $sites;

    /** @var \Drupal\multisite_helper\MultisiteHelperProcessingMethodInterface $processing_method */
    $processing_method = $this->processingMethodPluginManager
      ->createInstance($this->getProcessingMethod());

    return $processing_method->send($this->getPluginId(), $data, $sites);
  }

  /**
   * Call this method to create a remove job for this plugin with the necessary data.
   *
   * You can optionally provide an array of hostnames to which the data should
   * be sent. If this is left empty, the data will be sent to all other sites.
   */
  public function remove(array $data, ?array $sites = NULL): bool {
    if (MultisiteHelper::isImporting()) {
      return FALSE;
    }

    $sites = $sites === NULL
      ? $this->helper->getOtherSiteHostnames()
      : $sites;

    /** @var \Drupal\multisite_helper\MultisiteHelperProcessingMethodInterface $processing_method */
    $processing_method = $this->processingMethodPluginManager
      ->createInstance($this->getProcessingMethod());

    return $processing_method->remove($this->getPluginId(), $data, $sites);
  }

  /**
   * {@inheritDoc}
   */
  public function receive(array $data, string $action): bool {
    switch ($action) {
      case 'PUT':
      case 'POST':
        if ($this->helper->importEntity($data)) {
          return TRUE;
        }
        return FALSE;

      case 'DELETE':
        // Check if there is an existing entity with the identical uuid.
        $entity = $this->entityRepository->loadEntityByUuid($data['entity_type'], $data['uuid']);
        if ($entity?->delete()) {
          return TRUE;
        }
        return FALSE;
    }

    return FALSE;
  }

}

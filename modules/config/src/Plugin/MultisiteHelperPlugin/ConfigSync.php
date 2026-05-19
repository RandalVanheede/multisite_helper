<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_config\Plugin\MultisiteHelperPlugin;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\multisite_helper\Attribute\MultisiteHelperPlugin;
use Drupal\multisite_helper\MultisiteHelperPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the multisite_helper_plugin.
 */
#[MultisiteHelperPlugin(
  id: 'config_sync',
  label: new TranslatableMarkup('Configuration synchronization'),
  description: new TranslatableMarkup('Synchronizes selected Drupal configuration objects to all subsites.'),
  weight: 50,
)]
final class ConfigSync extends MultisiteHelperPluginBase {

  private ConfigFactoryInterface $configFactory;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->configFactory = $container->get('config.factory');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'config_names' => [],
    ];
  }

  /**
   * Returns whether the given config name is allowed to sync.
   */
  public function isConfigAllowed(string $config_name): bool {
    $allowed = array_filter($this->configuration['config_names'] ?? []);
    if (empty($allowed)) {
      return FALSE;
    }
    // Support wildcard patterns like 'system.menu.*'
    foreach ($allowed as $pattern) {
      $pattern = trim($pattern);
      if ($pattern === $config_name) {
        return TRUE;
      }
      // Convert glob pattern to regex.
      if (str_contains($pattern, '*')) {
        $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
        if (preg_match($regex, $config_name)) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritDoc}
   *
   * Overrides the base implementation to handle raw config data instead of
   * entities. Both sites must have config_sync enabled and watching the same
   * config names for a sync loop to occur — use MultisiteHelper::isImporting()
   * in the event subscriber to prevent this.
   */
  public function receive(array $data, string $action): bool {
    if ($action === 'DELETE') {
      return FALSE;
    }

    if (empty($data['config_name']) || !isset($data['config_data'])) {
      return FALSE;
    }

    $config = $this->configFactory->getEditable($data['config_name']);
    $config->setData($data['config_data']);
    $config->save();

    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['config_names'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Configuration objects to synchronize'),
      '#description' => $this->t(
        'Enter configuration object names to sync, one per line. Supports wildcards (e.g. <code>system.menu.*</code>). Examples: <code>system.site</code>, <code>system.menu.main</code>.'
      ),
      '#default_value' => implode("\n", $this->configuration['config_names'] ?? []),
      '#rows' => 8,
      '#states' => [
        'visible' => [
          ':input[name="plugins[config_sync][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Convert textarea to array before parent saves configuration.
    $raw = $form_state->getValue('config_names', '');
    $config_names = array_values(array_filter(array_map('trim', explode("\n", $raw))));
    $form_state->setValue('config_names', $config_names);
    parent::submitConfigurationForm($form, $form_state);
  }

}

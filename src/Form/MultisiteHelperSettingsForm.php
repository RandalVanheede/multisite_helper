<?php

declare(strict_types=1);

namespace Drupal\multisite_helper\Form;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\multisite_helper\MultisiteHelperEntityProcessorPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MultisiteHelperSettingsForm extends ConfigFormBase {

  private readonly MultisiteHelperEntityProcessorPluginManager $entityProcessorManager;

  private readonly ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityProcessorManager = $container->get('plugin.manager.multisite_helper_entity_processor');
    $instance->moduleHandler = $container->get('module_handler');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId(): string {
    return 'multisite_helper_settings';
  }

  /**
   * {@inheritDoc}
   */
  public function getEditableConfigNames(): array {
    return ['multisite_helper.settings'];
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#tree'] = TRUE;
    $config = $this->config('multisite_helper.settings');

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The API key'),
      '#description' => $this->t('The API key to be used across all subsites, this checks the validity of the calls between subsites.'),
      '#default_value' => $config->get('api_key') ?: $form_state->getValue('api_key') ?: \Drupal::service('uuid')->generate(),
      '#required' => TRUE,
      '#config_target' => 'multisite_helper.settings:api_key',
    ];

    $form['concurrent_calls'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of concurrent calls'),
      '#description' => $this->t('The amount of concurrent calls to send asynchronously other subsites.<br><strong>Important:</strong> This applies to whichever processing method is picked for the plugins.'),
      '#default_value' => $config->get('concurrent_calls') ?: $form_state->getValue('concurrent_calls') ?: 5,
      '#min' => 1,
      '#required' => TRUE,
      '#config_target' => 'multisite_helper.settings:concurrent_calls',
    ];

    $entity_processors = $this->entityProcessorManager->getDefinitions();
    ksort($entity_processors);
    $form['entity_processor'] = [
      '#type' => 'radios',
      '#title' => $this->t('Entity processor plugin'),
      '#description' => $this->t('The processor to import, export or delete entities.'),
      '#default_value' => $config->get('entity_processor') ?: $form_state->getValue('entity_processor') ?: 'basic',
      '#options' => array_map(static function ($plugin_definition) {
        return $plugin_definition['label'];
      }, $entity_processors),
      '#empty_option' => $this->t('- Select entity processor -'),
      '#required' => TRUE,
      '#config_target' => 'multisite_helper.settings:entity_processor',
    ];
    foreach ($entity_processors as $entity_processor_id => $entity_processor) {
      $form['entity_processor'][$entity_processor_id]['#description'] = $entity_processor['description'];
      foreach ($entity_processor['module_dependencies'] ?? [] as $module_dependency) {
        if (!$this->moduleHandler->moduleExists($module_dependency)) {
          $form['entity_processor'][$entity_processor_id]['#disabled'] = TRUE;
          $form['entity_processor'][$entity_processor_id]['#description'] .= '<br><strong>';
          $form['entity_processor'][$entity_processor_id]['#description'] .=
            $this->t('This processor depends on the following modules of which one or multiple are not installed: @modules', [
              '@modules' => implode(', ', $entity_processor['module_dependencies']),
            ]);
          $form['entity_processor'][$entity_processor_id]['#description'] .= '</strong>';
          break;
        }
      }
    }

    return parent::buildForm($form, $form_state);
  }

}

<?php

namespace Drupal\multisite_helper\Form;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\multisite_helper\MultisiteHelperEntityProcessorPluginManager;
use Drupal\multisite_helper\MultisiteHelperPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MultisiteHelperSettingsForm extends ConfigFormBase {

  private readonly MultisiteHelperPluginManager $pluginManager;

  private readonly MultisiteHelperEntityProcessorPluginManager $entityProcessorManager;

  private readonly ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->pluginManager = $container->get('plugin.manager.multisite_helper_plugin');
    $instance->entityProcessorManager = $container->get('plugin.manager.multisite_helper_entity_processor');
    $instance->moduleHandler = $container->get('module_handler');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId(): string {
    return 'multisite_helper_plugin';
  }

  /**
   * {@inheritDoc}
   */
  public function getEditableConfigNames(): array {
    return array_values(array_merge(['multisite_helper.settings'], array_map(static function ($plugin_definition) {
      return 'multisite_helper.plugin.' . $plugin_definition['id'];
    }, $this->pluginManager->getDefinitions())));
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#tree'] = TRUE;
    $config = $this->config('multisite_helper.settings');
    $values = $form_state->getValues();

    $entity_processors = $this->entityProcessorManager->getDefinitions();
    $form['entity_processor'] = [
      '#type' => 'radios',
      '#title' => $this->t('Number of concurrent calls'),
      '#description' => $this->t('The processor to import, export or delete entities.'),
      '#default_value' => $config->get('entity_processor') ?: $form_state->getValue('entity_processor') ?: 'single_content_sync',
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

    $form['concurrent_calls'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of concurrent calls'),
      '#description' => $this->t('The amount of concurrent calls to send asynchronously other subsites.<br><strong>Important:</strong> This applies to whichever processing method is picked for the plugins.'),
      '#default_value' => $config->get('concurrent_calls') ?: $form_state->getValue('concurrent_calls') ?: 5,
      '#min' => 1,
      '#required' => TRUE,
      '#config_target' => 'multisite_helper.settings:concurrent_calls',
    ];

    $form['plugins'] = [
      '#type' => 'vertical_tabs',
    ];

    $definitions = $this->pluginManager->getDefinitions();
    if (empty($definitions)) {
      $this->messenger()->addWarning($this->t('There are no plugins yet, please <a href=":link" target="_blank">install some</a> before configuring this form.', [
        ':link' => Url::fromRoute('system.modules_list')->setOption('fragment', 'edit-modules-multisite')->toString(),
      ]));
    }

    uasort($definitions, static function ($a, $b) {
      return ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0);
    });

    $open = TRUE;
    foreach ($definitions as $plugin_id => $plugin_definition) {
      $plugin_values = $values['plugins'][$plugin_id] ?? [];
      $plugin_values += $this->configFactory()->get('multisite_helper.plugin.' . $plugin_id)->getRawData() ?? [];
      /** @var \Drupal\multisite_helper\MultisiteHelperPluginInterface $plugin */
      $plugin = $this->pluginManager->createInstance($plugin_id, $plugin_values);

      $form['plugins'][$plugin_id] = [
        '#type' => 'details',
        '#title' => $plugin_definition['label'],
        '#description' => $plugin_definition['description'],
        '#group' => 'plugins',
      ];

      // Make sure the first tab is always open.
      if ($open) {
        $form['plugins'][$plugin_id]['#open'] = TRUE;
        $open = FALSE;
      }

      $plugin_form_state = new FormState();
      $plugin_form_state->setValues($plugin_values);
      $form['plugins'][$plugin_id] += $plugin->buildConfigurationForm([], $plugin_form_state);

      foreach (Element::children($form['plugins'][$plugin_id]) as $key) {
        $form['plugins'][$plugin_id][$key]['#config_target']
          = 'multisite_helper.plugin.' . $plugin_id . ':' . $key;
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $config = $this->config('multisite_helper.settings');
    $definitions = $this->pluginManager->getDefinitions();
    foreach ($definitions as $plugin_id => $plugin_definition) {
      $plugin_values = $values['plugins'][$plugin_id] ?? [];
      $plugin_values += $this->configFactory()->get('multisite_helper.plugin.' . $plugin_id)->getRawData() ?? [];
      /** @var \Drupal\multisite_helper\MultisiteHelperPluginInterface $plugin */
      $plugin = $this->pluginManager->createInstance($plugin_id, $plugin_values);
      $plugin_form_state = new FormState();
      $plugin_form_state->setValues($plugin_values);
      $plugin->validateConfigurationForm($form, $plugin_form_state);

      foreach ($plugin_form_state->getErrors() as $field_name => $error) {
        $form_state->setErrorByName($field_name, $error);
      }
    }
  }

}

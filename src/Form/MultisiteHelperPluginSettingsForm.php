<?php

declare(strict_types=1);

namespace Drupal\multisite_helper\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\multisite_helper\MultisiteHelperPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MultisiteHelperPluginSettingsForm extends ConfigFormBase {

  private readonly MultisiteHelperPluginManager $pluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->pluginManager = $container->get('plugin.manager.multisite_helper_plugin');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId(): string {
    return 'multisite_helper_plugin_settings';
  }

  /**
   * {@inheritDoc}
   */
  public function getEditableConfigNames(): array {
    return array_values(array_map(static function ($plugin_definition) {
      return 'multisite_helper.plugin.' . $plugin_definition['id'];
    }, $this->pluginManager->getDefinitions()));
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#tree'] = TRUE;
    $config = $this->config('multisite_helper.settings');
    $values = $form_state->getValues();

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

    $definitions = $this->pluginManager->getDefinitions();
    $values = $form_state->getValues();
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

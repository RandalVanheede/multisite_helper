<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_ui_translation\Plugin\MultisiteHelperPlugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\locale\StringStorageInterface;
use Drupal\multisite_helper\Attribute\MultisiteHelperPlugin;
use Drupal\multisite_helper\MultisiteHelperPluginBase;
use Drupal\taxonomy\VocabularyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the multisite_helper_plugin.
 */
#[MultisiteHelperPlugin(
  id: 'ui_translation_sync',
  label: new TranslatableMarkup('UI translation synchronization'),
  description: new TranslatableMarkup('Configure the UI translation synchronization settings.'),
)]
final class UiTranslationSync extends MultisiteHelperPluginBase {

  private StringStorageInterface $stringStorage;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->stringStorage = $container->get('locale.storage');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + [
      'changed' => FALSE,
    ];
  }

  /**
   * @inheritDoc
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['changed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Also synchronize customized translations'),
      '#description' => $this->t('By enabling this setting, customized translation strings will also be imported on other subsites.'),
      '#default_value' => $this->configuration['changed'],
      '#states' => [
        'visible' => [
          ':input[name="plugins[ui_translation_sync][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function remove(array $data, ?array $sites = NULL): bool {
    // Do nothing, UI translations are not to be deleted.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function receive(array $data, string $action): bool {
    return match ($action) {
      'PUT', 'POST' => $this->importTranslation($data),
      default => FALSE,
    };
  }

  /**
   * Import the data as a translated string.
   */
  private function importTranslation(array $data): bool {
    // Find or create the source string.
    if (!$string = $this->stringStorage->findString([
      'source' => $data['source'],
    ])) {
      $string = $this->stringStorage
        ->createString(['source' => $data['source']])
        ->save();
    }

    // Find or create the new translation string.
    if (!$translation = $this->stringStorage->findTranslation([
      'lid' => $string->getId(),
      'language' => $data['language'],
    ])) {
      $translation = $this->stringStorage->createTranslation([
        'lid' => $string->getId(),
        'language' => $data['language'],
        'source' => $data['source'],
      ]);
    }

    // Check whether this translation is customized and we're allowed to import customized strings.
    if ($translation->customized && !$this->importCustomized()) {
      return FALSE;
    }

    // Overwrite the translation values and save the translation.
    $translation->setValues($data);
    $translation->save();

    return TRUE;
  }

  /**
   * Returns whether this site also imports customized strings.
   */
  public function importCustomized(): bool {
    return (bool) ($this->configuration['changed'] ?? FALSE);
  }

}

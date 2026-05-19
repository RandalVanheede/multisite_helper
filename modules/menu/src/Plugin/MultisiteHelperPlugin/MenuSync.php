<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_menu\Plugin\MultisiteHelperPlugin;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\multisite_helper\Attribute\MultisiteHelperPlugin;
use Drupal\multisite_helper\MultisiteHelperPluginBase;
use Drupal\system\MenuInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the multisite_helper_plugin.
 */
#[MultisiteHelperPlugin(
  id: 'menu_sync',
  label: new TranslatableMarkup('Menu link synchronization'),
  description: new TranslatableMarkup('Synchronizes menu link content entities between subsites.'),
  handles_entity_type: 'menu_link_content',
  weight: 40,
)]
final class MenuSync extends MultisiteHelperPluginBase {

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
      'menus' => [],
    ];
  }

  /**
   * Returns whether the given menu is allowed to sync.
   */
  public function isMenuAllowed(string $menu_name): bool {
    $allowed = array_filter($this->configuration['menus'] ?? []);
    // Empty means all menus are allowed.
    if (empty($allowed)) {
      return TRUE;
    }
    return isset($allowed[$menu_name]);
  }

  /**
   * {@inheritDoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $menus = $this->entityTypeManager->getStorage('menu')->loadMultiple();
    $menu_options = array_map(static fn(MenuInterface $menu) => $menu->label(), $menus);
    asort($menu_options);

    $form['menus'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Menus to synchronize'),
      '#description' => $this->t('Select which menus should be synchronized. Leave all unchecked to sync all menus.'),
      '#options' => $menu_options,
      '#default_value' => $this->configuration['menus'] ?? [],
      '#states' => [
        'visible' => [
          ':input[name="plugins[menu_sync][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

}

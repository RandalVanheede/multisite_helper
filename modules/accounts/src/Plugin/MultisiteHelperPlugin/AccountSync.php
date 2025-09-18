<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_accounts\Plugin\MultisiteHelperPlugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\multisite_helper\Attribute\MultisiteHelperPlugin;
use Drupal\multisite_helper\MultisiteHelperPluginBase;
use Drupal\user\RoleInterface;

/**
 * Plugin implementation of the multisite_helper_plugin.
 */
#[MultisiteHelperPlugin(
  id: 'account_sync',
  label: new TranslatableMarkup('Account synchronization'),
  description: new TranslatableMarkup('Configure the account synchronization settings.'),
  handles_entity_type: 'user',
)]
final class AccountSync extends MultisiteHelperPluginBase {

  /**
   * {@inheritDoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + [
      'roles' => ['administrator'],
    ];
  }

  /**
   * @inheritDoc
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // @todo fix: "is not a supported key"
//    $form['warning'] = [
//      '#type' => 'html_tag',
//      '#tag' => 'strong',
//      '#value' => (string) $this->t("If an SSO solution is used, it's advised to disable any and all account synchronization and just let the SSO provider handle it."),
//      '#weight' => -10,
//    ];

    $roles = array_map(static function (RoleInterface $role) {
      return $role->label();
    }, \Drupal::entityTypeManager()->getStorage('user_role')->loadMultiple());

    $form['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles to synchronize'),
      '#description' => $this->t('Select the roles for which accounts need to be synchronized, leave empty to synchronize all accounts (not recommended, but possible and allowed).'),
      '#description_display' => 'before',
      '#default_value' => $this->configuration['roles'],
      '#options' => array_filter($roles, function ($role) {
        return $role !== 'anonymous';
      }, ARRAY_FILTER_USE_KEY),
      '#states' => [
        'visible' => [
          ':input[name="plugins[account_sync][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

}

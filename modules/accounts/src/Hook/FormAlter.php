<?php

namespace Drupal\multisite_helper_accounts\Hook;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\multisite_helper\MultisiteHelperPluginManager;
use Drupal\single_content_sync\ContentExporterInterface;

class FormAlter {

  use DependencySerializationTrait;

  public function __construct(
    private readonly MultisiteHelperPluginManager $pluginManager,
    private readonly ContentExporterInterface $contentExporter,
  ) {}

  #[Hook('form_user_form_alter')]
  public function userFormAlter(array &$form, FormStateInterface $form_state): void {
    $form['actions']['submit']['#submit'][] = [$this, 'userFormSubmit'];
  }

  /**
   * Send this node's data to other subsites.
   */
  public function userFormSubmit(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    /** @var \Drupal\multisite_helper_accounts\Plugin\MultisiteHelperPlugin\AccountSync $plugin */
    $plugin = $this->pluginManager->getPlugin('account_sync');
    $plugin_config = $plugin->getConfiguration();
    if (empty($plugin_config['enabled'])) {
      return;
    }

    /** @var \Drupal\user\UserInterface $user */
    $user = $form_state->getFormObject()->getEntity();

    // Check if the user has any of the configured roles.
    $configured_roles = array_filter($plugin_config['roles']);
    if (!empty($configured_roles) && empty(array_intersect($user->getRoles(), $configured_roles))) {
      return;
    }

    $user_values = $this->contentExporter->doExportToArray($user);
    if (!empty($values['pass'])) {
      $user_values['custom_fields']['pass'] = [['value' => $values['pass']]];
    }
    $plugin->send($user_values);
  }

}

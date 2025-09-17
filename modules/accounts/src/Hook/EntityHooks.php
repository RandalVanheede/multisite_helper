<?php

namespace Drupal\multisite_helper_accounts\Hook;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\multisite_helper\MultisiteHelperInterface;
use Drupal\multisite_helper\MultisiteHelperPluginManager;
use Drupal\user\UserInterface;

class EntityHooks {

  use DependencySerializationTrait;

  public function __construct(
    private readonly MultisiteHelperPluginManager $pluginManager,
    private readonly MultisiteHelperInterface $helper,
  ) {}

  #[Hook('user_insert')]
  #[Hook('user_update')]
  public function save(UserInterface $user): void {
    $this->doSend($user, 'send');
  }

  #[Hook('user_delete')]
  public function delete(UserInterface $user): void {
    $this->doSend($user, 'remove');
  }

  public function doSend(UserInterface $user, string $action): void {
    /** @var \Drupal\multisite_helper_accounts\Plugin\MultisiteHelperPlugin\AccountSync $plugin */
    $plugin = $this->pluginManager->getPlugin('account_sync');
    $plugin_config = $plugin->getConfiguration();
    if (empty($plugin_config['enabled'])) {
      return;
    }

    // Check if the user has any of the configured roles.
    $configured_roles = array_filter($plugin_config['roles']);
    if (!empty($configured_roles) && empty(array_intersect($user->getRoles(), $configured_roles))) {
      return;
    }

    $user_values = $this->helper->getEntityProcessor()->exportEntity($user);
    $plugin->{$action}($user_values);
  }

}

<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_menu\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\multisite_helper\MultisiteHelperInterface;
use Drupal\multisite_helper\MultisiteHelperPluginManager;

class EntityHooks {

  public function __construct(
    private readonly MultisiteHelperPluginManager $pluginManager,
    private readonly MultisiteHelperInterface $helper,
  ) {}

  #[Hook('menu_link_content_insert')]
  #[Hook('menu_link_content_update')]
  public function save(MenuLinkContentInterface $menuLink): void {
    $this->doSend($menuLink, 'send');
  }

  #[Hook('menu_link_content_delete')]
  public function delete(MenuLinkContentInterface $menuLink): void {
    $this->doSend($menuLink, 'remove');
  }

  /**
   * Sends the required data to the send/remove endpoint.
   */
  public function doSend(MenuLinkContentInterface $menuLink, string $action): void {
    /** @var \Drupal\multisite_helper_menu\Plugin\MultisiteHelperPlugin\MenuSync $plugin */
    $plugin = $this->pluginManager->getPlugin('menu_sync');
    if (!$plugin->isEnabled()) {
      return;
    }

    if (!$plugin->isMenuAllowed($menuLink->getMenuName())) {
      return;
    }

    $values = $this->helper->getEntityProcessor()->exportEntity($menuLink);
    $plugin->{$action}($values);
  }

}

<?php

namespace Drupal\multisite_helper\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\multisite_helper\MultisiteHelperPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

abstract class MultisiteHelperControllerBase extends ControllerBase {

  public function __construct(
    ConfigFactoryInterface $configFactory,
    protected readonly MultisiteHelperPluginManager $pluginManager,
  ) {
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.multisite_helper_plugin'),
    );
  }

  /**
   * Checks whether the provided API key matches the configured one.
   */
  public function apiKeyAccess(AccountInterface $account, Request $request): AccessResultInterface {
    $api_key = $request->headers->get('x-api-key');
    $config = $this->config('multisite_helper.settings');
    return AccessResult::allowedIf($api_key === $config->get('api_key'));
  }

}

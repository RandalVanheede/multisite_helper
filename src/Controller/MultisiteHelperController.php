<?php

namespace Drupal\multisite_helper\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\multisite_helper\MultisiteHelper;
use Drupal\multisite_helper\MultisiteHelperPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class MultisiteHelperController extends ControllerBase {

  public function __construct(
    private readonly MultisiteHelperPluginManager $pluginManager,
    ConfigFactoryInterface $configFactory,
  ) {
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.multisite_helper_plugin'),
      $container->get('config.factory')
    );
  }

  /**
   * Processes the provided data for the plugin.
   */
  public function receive(string $plugin_id, Request $request): JsonResponse {
    // Make sure to set the importing flag to TRUE, to avoid infinite loops.
    MultisiteHelper::setImporting();

    /** @var \Drupal\multisite_helper\MultisiteHelperPluginInterface $plugin */
    $plugin = $this->pluginManager->getPlugin($plugin_id);
    $plugin->receive(json_decode($request->getContent(), TRUE), $request->getMethod());
    return new JsonResponse(['message' => 'OK!']);
  }

  /**
   * Checks whether the provided API key matches the configured one.
   */
  public function apiKeyAccess(AccountInterface $account, string $plugin_id, Request $request) {
    $api_key = $request->headers->get('x-api-key');
    $config = $this->config('multisite_helper.settings');
    return AccessResult::allowedIf($api_key === $config->get('api_key'));
  }

}

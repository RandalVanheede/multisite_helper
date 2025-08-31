<?php

namespace Drupal\multisite_helper\Controller;

use Drupal\Core\Access\AccessResult;
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
  ) {}

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('plugin.manager.multisite_helper_plugin'));
  }

  /**
   * Processes the provided data for the plugin.
   */
  public function receive(string $plugin_id, Request $request): JsonResponse {
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
    return AccessResult::allowedIf($api_key === MultisiteHelper::getSetting('api_key'));
  }

}

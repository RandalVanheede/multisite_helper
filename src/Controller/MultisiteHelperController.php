<?php

namespace Drupal\multisite_helper\Controller;

use Drupal\multisite_helper\MultisiteHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class MultisiteHelperController extends MultisiteHelperControllerBase {

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

}

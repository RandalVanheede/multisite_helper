<?php

declare(strict_types=1);

namespace Drupal\multisite_helper\Controller;

use Drupal\multisite_helper\MultisiteHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MultisiteHelperController extends MultisiteHelperControllerBase {

  /**
   * Processes the provided data for the plugin.
   */
  public function receive(string $plugin_id, Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    if ($data === NULL) {
      return new JsonResponse(
        ['error' => 'Invalid JSON payload.'],
        Response::HTTP_BAD_REQUEST,
      );
    }

    // Make sure to set the importing flag to TRUE, to avoid infinite loops.
    MultisiteHelper::setImporting();

    try {
      /** @var \Drupal\multisite_helper\MultisiteHelperPluginInterface $plugin */
      $plugin = $this->pluginManager->getPlugin($plugin_id);
      $plugin->receive($data, $request->getMethod());
      return new JsonResponse(['message' => 'OK!']);
    }
    finally {
      MultisiteHelper::setImporting(FALSE);
    }
  }

}

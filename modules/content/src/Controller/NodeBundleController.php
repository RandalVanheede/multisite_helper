<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_content\Controller;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\multisite_helper\Controller\MultisiteHelperControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class NodeBundleController extends MultisiteHelperControllerBase {

  private readonly EntityTypeBundleInfoInterface $bundleInfo;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->bundleInfo = $container->get('entity_type.bundle.info');
    return $instance;
  }

  /**
   * Returns a list of bundles.
   */
  public function list(): JsonResponse {
    /** @var \Drupal\multisite_helper_content\Plugin\MultisiteHelperPlugin\ContentSync $plugin */
    $plugin = $this->pluginManager->getPlugin('content_sync');
    $bundle_info = $this->bundleInfo->getBundleInfo('node');
    $bundle_info = array_filter($bundle_info, static function (string $bundle) use ($plugin) {
      return $plugin->isBundleAllowed($bundle);
    }, ARRAY_FILTER_USE_KEY);
    return new JsonResponse($bundle_info);
  }

}

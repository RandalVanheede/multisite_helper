<?php

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
    return new JsonResponse($this->bundleInfo->getBundleInfo('node'));
  }

}

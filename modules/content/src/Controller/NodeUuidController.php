<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_content\Controller;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class NodeUuidController extends ControllerBase {

  public function __construct(
    private readonly EntityRepositoryInterface $entityRepository,
  ) {}

  public static function create(ContainerInterface $container) {
    return new static($container->get('entity.repository'));
  }

  /**
   * Redirect the user to the appropriate route.
   */
  public function uuidRedirect(string $uuid, ?string $operation = NULL): RedirectResponse {
    $node = $this->entityRepository->loadEntityByUuid('node', $uuid);

    $url = $node->toUrl($operation ? $operation . '-form' : NULL);
    return $this->redirect($url->getRouteName(), $url->getRouteParameters(), $url->getOptions());
  }

  /**
   * Check whether the visitor/user has access to this node and its operation.
   */
  public function uuidAccess(AccountInterface $account, string $uuid, ?string $operation = NULL): AccessResultInterface {
    // If the entity was not found, throw 404.
    /** @var \Drupal\node\NodeInterface $node */
    if (!$node = $this->entityRepository->loadEntityByUuid('node', $uuid)) {
      throw new NotFoundHttpException();
    }

    $operation = $operation ?? 'view';
    return $node->access($operation, $account, TRUE);
  }

}

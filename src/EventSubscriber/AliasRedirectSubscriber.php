<?php

declare(strict_types=1);

namespace Drupal\multisite_helper\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects alias hostnames to the canonical primary subsite URL.
 *
 * When a request arrives on a domain alias (not the primary URL of a subsite),
 * this subscriber issues a 301 redirect to the same path on the primary URL.
 */
final class AliasRedirectSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onRequest', 100],
    ];
  }

  /**
   * Redirects alias requests to the canonical primary URL.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();
    $host = $request->getHttpHost();
    $scheme = $request->getScheme();
    $current_url = $scheme . '://' . $host;

    // Load all subsites and check if the current URL matches an alias.
    /** @var \Drupal\multisite_helper\MhSubsiteInterface[] $subsites */
    $subsites = $this->entityTypeManager->getStorage('mh_subsite')->loadMultiple();

    foreach ($subsites as $subsite) {
      // Skip if the current URL is the primary URL (no redirect needed).
      $primary_url = rtrim($subsite->url() ?? '', '/');
      if ($primary_url === 'http://' . $host || $primary_url === 'https://' . $host) {
        return;
      }

      // Check if the current URL matches any alias.
      foreach ($subsite->aliases() as $alias) {
        $alias = rtrim($alias, '/');
        if ($alias === 'http://' . $host || $alias === 'https://' . $host) {
          // Build the redirect URL: primary URL + current path + query string.
          $path = $request->getRequestUri();
          $redirect_url = $primary_url . $path;
          $event->setResponse(new RedirectResponse($redirect_url, 301));
          return;
        }
      }
    }
  }

}

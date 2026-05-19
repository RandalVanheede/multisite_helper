<?php

declare(strict_types=1);

namespace Drupal\multisite_helper;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

class MultisiteHelperServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritDoc}
   */
  public function alter(ContainerBuilder $container): void {
    // Only register the SingleContentSync event subscriber if the module is installed.
    $modules = $container->getParameter('container.modules');
    if (!isset($modules['single_content_sync'])) {
      $container->removeDefinition('multisite_helper.event_subscriber.single_content_sync');
    }
  }

}

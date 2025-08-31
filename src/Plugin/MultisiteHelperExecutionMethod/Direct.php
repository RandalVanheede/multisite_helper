<?php

declare(strict_types=1);

namespace Drupal\multisite_helper\Plugin\MultisiteHelperExecutionMethod;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\multisite_helper\Attribute\MultisiteHelperExecutionMethod;
use Drupal\multisite_helper\MultisiteHelperExecutionMethodPluginBase;
use Drupal\multisite_helper\MultisiteHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[MultisiteHelperExecutionMethod(
  id: 'direct',
  label: new TranslatableMarkup('Direct'),
  description: new TranslatableMarkup('Send the data during the request.'),
)]
final class Direct extends MultisiteHelperExecutionMethodPluginBase {

  private readonly MultisiteHelperInterface $helper;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->helper = $container->get('multisite_helper');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function send(string $plugin_id, array $data, array $sites = []): bool {
    return $this->helper->sendToSites($plugin_id, $data, $sites);
  }

  /**
   * {@inheritDoc}
   */
  public function remove(string $plugin_id, array $data, array $sites = []): bool {
    return $this->helper->removeFromSites($plugin_id, $data, $sites);
  }

}

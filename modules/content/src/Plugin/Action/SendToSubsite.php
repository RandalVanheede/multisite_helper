<?php

namespace Drupal\multisite_helper_content\Plugin\Action;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[Action(
  id: 'mh_send_to_subsite',
  label: new TranslatableMarkup('Send content to subsite'),
  category: new TranslatableMarkup('Custom'),
  type: 'node',
)]
class SendToSubsite extends ActionBase implements ContainerFactoryPluginInterface {


  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->messenger = $container->get('messenger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function access($node, AccountInterface $account = NULL, $return_as_object = FALSE): bool|AccessResultInterface {
    /** @var \Drupal\node\NodeInterface $node */
    $access = $node->access('update', $account, TRUE);
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute($node = NULL): void {
    /** @var \Drupal\node\NodeInterface $node */

    // Set the sync value to true.
    $node->set('mh_sync', TRUE);

    // Retrieve the current sites.
    $sites = $node->get('mh_sites')->getValue();
    $current_site_ids = array_map(static function ($item) {
      return $item['target_id'];
    }, $sites);

    // If necessary, add the new site.
    $subsite_id = $this->configuration['mh_subsite'];
    if (!in_array($subsite_id, $current_site_ids)) {
      $sites[] = [
        'target_id' => $subsite_id,
      ];
      $node->set('mh_sites', $sites);
    }

    // Save the node.
    $node->save();
  }

}

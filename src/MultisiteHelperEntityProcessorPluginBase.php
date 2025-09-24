<?php

declare(strict_types=1);

namespace Drupal\multisite_helper;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for multisite_helper_entity_processor plugins.
 */
abstract class MultisiteHelperEntityProcessorPluginBase extends PluginBase implements MultisiteHelperEntityProcessorInterface {

  protected EntityRepositoryInterface $entityRepository;

  protected readonly EntityTypeBundleInfoInterface $bundleInfo;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityRepository = $container->get('entity.repository');
    $instance->bundleInfo = $container->get('entity_type.bundle.info');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function description(): string {
    // Cast the description to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['description'];
  }

  /**
   * {@inheritDoc}
   */
  public function getEntityBaseInformation(array $data): array {
    return [
      'uuid' => $data['uuid'],
      'entity_type' => $data['entity_type'],
      'is_translation' => $data['is_translation'],
      'language' => $data['language'],
      'bundle' => $data['bundle'] ?? $data['entity_type'],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function deleteEntity(array $data): bool {
    [
      'entity_type' => $entity_type,
      'uuid' => $uuid,
    ] = $this->getEntityBaseInformation($data);

    $entity = $this->entityRepository->loadEntityByUuid($entity_type, $uuid);
    return (bool) $entity?->delete();
  }

}

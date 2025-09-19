<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_complex_serializer;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\multisite_helper_complex_serializer\Enum\EntityType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for entity_type plugins.
 */
abstract class EntityTypePluginBase extends PluginBase implements EntityTypeInterface {

  protected readonly EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
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
   * {@inheritDoc}
   */
  public function import(array $data): bool|EntityInterface {
    try {
      $storage = $this->entityTypeManager->getStorage($data['entity_type']);
      return $storage->create($data['fields']);
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function export(EntityInterface $entity): array {
    return $entity->toArray();
  }

  /**
   * {@inheritDoc}
   */
  public function getBaseData(EntityInterface $entity): array {
    $type = EntityType::GENERIC;
    if ($entity instanceof FieldableEntityInterface) {
      $type = EntityType::FIELDABLE;
    }
    elseif ($entity instanceof ConfigEntityInterface) {
      $type = EntityType::CONFIG;
    }

    return [
      'type' => $type->value,
      'entity_type' => $entity->getEntityTypeId(),
      'bundle' => method_exists($entity, 'bundle') ? $entity->bundle() : $entity->getEntityTypeId(),
      'uuid' => $entity->uuid(),
    ];
  }

}

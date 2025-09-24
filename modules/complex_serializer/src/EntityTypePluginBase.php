<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_complex_serializer;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\multisite_helper_complex_serializer\Enum\EntityType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for entity_type plugins.
 */
abstract class EntityTypePluginBase extends PluginBase implements EntityTypeInterface {

  protected readonly EntityTypeManagerInterface $entityTypeManager;

  protected readonly EntityRepositoryInterface $entityRepository;

  protected readonly FieldTypePluginManager $fieldTypePluginManager;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityRepository = $container->get('entity.repository');
    $instance->fieldTypePluginManager = $container->get('plugin.manager.field_type');
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
    $entity = $this->getEntity($data, stub: FALSE);
    $entity->save();
    return $entity;
  }

  /**
   * {@inheritDoc}
   */
  public function importStub(array $data): bool|EntityInterface {
    $entity = $this->getEntity($data, stub: TRUE);
    $entity->save();
    return $entity;
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

    $extra_data = [
      'is_translation' => FALSE,
      'language' => NULL,
    ];

    if ($entity instanceof TranslatableInterface) {
      $extra_data['is_translation'] = !$entity->isDefaultTranslation();
      $extra_data['language'] = $entity->language()->getId();
    }

    return [
      'type' => $type->value,
      'entity_type' => $entity->getEntityTypeId(),
      'bundle' => method_exists($entity, 'bundle') ? $entity->bundle() : $entity->getEntityTypeId(),
      'uuid' => $entity->uuid(),
    ] + $extra_data;
  }

  /**
   * Loads or creates an entity from the given import data.
   */
  protected function getEntity(array $data, bool $stub): ?EntityInterface {
    try {
      [
        'uuid' => $uuid,
        'entity_type' => $entity_type,
        'bundle' => $bundle,
        'language' => $language,
        'is_translation' => $is_translation,
      ] = $data;

      if (!$entity = $this->entityRepository->loadEntityByUuid($entity_type, $uuid)) {
        $storage = $this->entityTypeManager->getStorage($entity_type);
        $entity_type = $entity->getEntityType();

        $base_data = [
          $entity_type->getKey('uuid') => $uuid,
        ];
        if ($entity_type->hasKey('bundle')) {
          $base_data[$entity_type->getKey('bundle')] = $bundle;
        }
        if ($entity_type->hasKey('langcode')) {
          $base_data[$entity_type->getKey('langcode')] = $language;
        }

        if ($stub) {
          $entity = $storage->create($base_data);
        }
        else {
          $entity = $storage->create($base_data + ($data['fields'] ?? []));
        }
      }

      if (!$stub) {
        if ($is_translation && $entity instanceof TranslatableInterface) {
          $entity = $entity->hasTranslation($language)
            ? $entity->getTranslation($language)
            : $entity->addTranslation($language, $entity->toArray());
        }
      }

      return $entity;
    }
    catch (\Exception $e) {
      // @todo error logging.
    }

    return NULL;
  }

}

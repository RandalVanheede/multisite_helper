<?php

declare(strict_types=1);

namespace Drupal\multisite_helper\Plugin\MultisiteHelperEntityProcessor;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\multisite_helper\Attribute\MultisiteHelperEntityProcessor;
use Drupal\multisite_helper\MultisiteHelperEntityProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Plugin implementation of the multisite_helper_entity_processor.
 */
#[MultisiteHelperEntityProcessor(
  id: 'serializer',
  label: new TranslatableMarkup('Serialization importer/exporter'),
  description: new TranslatableMarkup('Serializes and deserializes entities using services provides by core, supports complex entities.'),
  module_dependencies: ['serialization']
)]
final class Serializer extends MultisiteHelperEntityProcessorPluginBase {

  private readonly SerializerInterface $serializer;

  private readonly EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->serializer = $container->get('serializer');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function importEntity(array $data): bool {
    [
      'entity_type' => $entity_type_id,
      'uuid' => $uuid,
    ] = $this->getEntityBaseInformation($data);
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $saved_entities = [];

    foreach ($data['extra_entities'] as $extra_entity_type_id => $extra_entities) {
      $extra_entity_type = $this->entityTypeManager->getDefinition($extra_entity_type_id);
      foreach ($extra_entities as $extra_entity_uuid => $extra_entity_data) {
        $extra_entity = $this->serializer->denormalize($extra_entity_data, $extra_entity_type->getClass());
        if (!$extra_entity instanceof ContentEntityInterface) {
          continue;
        }

        if ($extra_entity_existing = $this->entityRepository->loadEntityByUuid($extra_entity_type_id, $extra_entity_uuid)) {
          foreach ($extra_entity->toArray() as $field_name => $field_value) {
            $extra_entity_existing->set($field_name, $field_value);
          }
        }
        else {
          $extra_entity->save();
        }

        $saved_entities[$extra_entity_type_id][$extra_entity_uuid] = $extra_entity->id();
      }
    }

    foreach ($data['fields'] as $field_name => $field_values) {
      foreach ($field_values as $field_delta => $field_value) {
        if (!is_array($field_value)) {
          continue;
        }

        if (empty($field_value['target_type']) || empty($field_value['target_uuid'])) {
          continue;
        }

        if (!isset($saved_entities[$field_value['target_type']][$field_value['target_uuid']])) {
          continue;
        }

        $data['fields'][$field_name][$field_delta] = [
          'target_id' => $saved_entities[$field_value['target_type']][$field_value['target_uuid']],
        ];
      }
    }

    $entity = $this->serializer->denormalize($data['fields'], $entity_type->getClass());
    if (!$entity instanceof ContentEntityInterface) {
      return FALSE;
    }

    if ($entity_existing = $this->entityRepository->loadEntityByUuid($entity_type_id, $uuid)) {
      $revision_field = $entity_type->hasKey('revision')
        ? $entity_type->getKey('revision')
        : NULL;

      foreach ($entity->toArray() as $field_name => $field_value) {
        if ($revision_field && $field_name === $revision_field) {
          continue;
        }
        $entity_existing->set($field_name, $field_value);
      }

      $entity_existing->save();
    }
    else {
      $entity->save();
    }

    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function exportEntity(ContentEntityInterface $entity, array $extra_data = []): array {
    $entity_type = $entity->getEntityType();
    $entity_data = [
      'entity_type' => $entity_type->id(),
      'bundle' => $entity_type->id(),
      'uuid' => $entity->uuid(),
      'extra_entities' => [],
    ];

    if ($entity_type->hasKey('bundle')) {
      $entity_data['bundle'] = $entity->get($entity_type->getKey('bundle'))->getString();
    }

    // Serialize the target entity as an array.
    $entity_data['fields'] = $this->serializer->normalize($entity, 'json');

    // Also serialize/normalize extra entities that are referenced from the target entity.
    foreach ($entity_data['fields'] as $field_values) {
      foreach ($field_values as $field_value) {
        if (!is_array($field_value)) {
          continue;
        }

        if (empty($field_value['target_type']) || empty($field_value['target_uuid'])) {
          continue;
        }

        if (isset($entity_data['extra_entities'][$field_value['target_type']][$field_value['target_uuid']])) {
          continue;
        }

        $extra_entity = $this->entityRepository->loadEntityByUuid($field_value['target_type'], $field_value['target_uuid']);
        if (!$extra_entity instanceof ContentEntityInterface) {
          continue;
        }

        $this->normalizeEntity($extra_entity, $entity_data['extra_entities']);
      }
    }

    // Add extra data to the custom_fields array item.
    $entity_data['fields'] = NestedArray::mergeDeepArray([$entity_data['fields'], $extra_data], TRUE);
    return $entity_data;
  }

  /**
   * Recursive function to normalize all referenced entities.
   */
  private function normalizeEntity(ContentEntityInterface $entity, array &$extra_entities): void {
    $entity_data = $this->serializer->normalize($entity, 'json');

    if (!isset($extra_entities[$entity->getEntityTypeId()][$entity->uuid()])) {
      $extra_entities[$entity->getEntityTypeId()][$entity->uuid()] = $entity_data;
    }

    // Also serialize/normalize extra entities that are referenced from the target entity.
    foreach ($entity_data as $field_values) {
      foreach ($field_values as $field_value) {
        if (!is_array($field_value)) {
          continue;
        }

        if (empty($field_value['target_type']) || empty($field_value['target_uuid'])) {
          continue;
        }

        if (isset($extra_entities[$field_value['target_type']][$field_value['target_uuid']])) {
          continue;
        }

        $extra_entity = $this->entityRepository->loadEntityByUuid($field_value['target_type'], $field_value['target_uuid']);
        if (!$extra_entity instanceof ContentEntityInterface) {
          continue;
        }

        $this->normalizeEntity($extra_entity, $extra_entities);
      }
    }
  }

}

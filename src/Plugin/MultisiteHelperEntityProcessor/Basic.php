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

/**
 * Plugin implementation of the multisite_helper_entity_processor.
 */
#[MultisiteHelperEntityProcessor(
  id: 'basic',
  label: new TranslatableMarkup('Basic'),
  description: new TranslatableMarkup('Uses a very basic entity importer/exporter, without support for entity reference or other complex fields.'),
)]
final class Basic extends MultisiteHelperEntityProcessorPluginBase {

  private const SUPPORTED_FIELD_TYPES = [
    'address',
    'address_country',
    'address_zone',
    'integer',
    'decimal',
    'float',
    'boolean',
    'list_string',
    'list_integer',
    'list_float',
    'created',
    'changed',
    'email',
    'link',
    'path',
    'string',
    'string_long',
    'text',
    'text_long',
    'text_with_summary',
    'datetime',
    'time',
    'timestamp',
    'uri',
    'uuid',
    'language',
  ];

  private readonly EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function importEntity(array $data): bool {
    [
      'entity_type' => $entity_type,
      'bundle' => $bundle,
    ] = $data;

    // If the bundle does not exist, don't import the entity.
    if (!array_key_exists($bundle, $this->bundleInfo->getBundleInfo($entity_type))) {
      return FALSE;
    }

    $storage = $this->entityTypeManager->getStorage($data['entity_type']);
    $entity_type = $storage->getEntityType();
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    if (!$entity = $this->entityRepository->loadEntityByUuid($data['entity_type'], $data['uuid'])) {
      $init_data = [
        'uuid' => $data['uuid'],
      ];
      if (!empty($bundle) && $entity_type->hasKey('bundle')) {
        $init_data[$entity_type->getKey('bundle')] = $bundle;
      }
      $entity = $storage->create($init_data);
    }

    if ($data['is_translation']) {
      $entity = $entity->hasTranslation($data['language'])
        ? $entity->getTranslation($data['language'])
        : $entity->addTranslation($data['language'], $entity->toArray());
    }

    $revision_field = $entity_type->hasKey('revision')
      ? $entity_type->getKey('revision')
      : NULL;

    foreach ($data['fields'] as $field_name => $values) {
      if ($revision_field && $field_name === $revision_field) {
        continue;
      }
      $entity->set($field_name, $values);
    }

    $entity->save();

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
      'is_translation' => !$entity->isDefaultTranslation(),
      'language' => $entity->language()->getId(),
      'fields' => [],
    ];

    if ($entity_type->hasKey('bundle')) {
      $entity_data['bundle'] = $entity->get($entity_type->getKey('bundle'))->getString();
    }

    $field_definitions = $entity->getFieldDefinitions();
    foreach ($field_definitions as $field_definition) {
      if (!in_array($field_definition->getType(), self::SUPPORTED_FIELD_TYPES)) {
        continue;
      }

      $entity_data['fields'][$field_definition->getName()] = $entity->get($field_definition->getName())->getValue();
    }

    $entity_data['fields'] = NestedArray::mergeDeepArray([$entity_data['fields'], $extra_data], TRUE);
    return $entity_data;
  }

}

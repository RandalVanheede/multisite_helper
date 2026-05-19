<?php

declare(strict_types=1);

namespace Drupal\multisite_helper\Plugin\MultisiteHelperEntityProcessor;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileRepositoryInterface;
use Drupal\multisite_helper\Attribute\MultisiteHelperEntityProcessor;
use Drupal\multisite_helper\MultisiteHelper;
use Drupal\multisite_helper\MultisiteHelperEntityProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Advanced entity processor plugin with full support for complex field types.
 *
 * Handles entity references (recursive), file/image fields (base64), and
 * content translations natively — no dependency on single_content_sync.
 */
#[MultisiteHelperEntityProcessor(
  id: 'advanced',
  label: new TranslatableMarkup('Advanced'),
  description: new TranslatableMarkup('Handles complex entities including entity references, files, media, paragraphs, and translations. No external module dependencies.'),
)]
final class Advanced extends MultisiteHelperEntityProcessorPluginBase {

  /**
   * Field types exported as raw FieldItemListInterface::getValue().
   */
  private const SCALAR_FIELD_TYPES = [
    'string',
    'string_long',
    'text',
    'text_long',
    'text_with_summary',
    'integer',
    'decimal',
    'float',
    'boolean',
    'list_string',
    'list_integer',
    'list_float',
    'email',
    'link',
    'uri',
    'datetime',
    'timestamp',
    'created',
    'time',
    'language',
    'uuid',
    'address',
    'address_country',
    'address_zone',
    'path',
  ];

  /**
   * Fields that are always skipped during export (managed by Drupal on save).
   */
  private const SKIP_FIELDS = [
    'vid',
    'changed',
    'default_langcode',
    'content_translation_source',
    'content_translation_outdated',
  ];

  /**
   * Stack of UUIDs currently being exported, used to prevent circular refs.
   *
   * @var string[]
   */
  private array $exportStack = [];

  private EntityTypeManagerInterface $entityTypeManager;

  private FileSystemInterface $fileSystem;

  private StreamWrapperManagerInterface $streamWrapperManager;

  private LanguageManagerInterface $languageManager;

  private LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Optional file repository service (requires file module).
   */
  private ?FileRepositoryInterface $fileRepository = NULL;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->fileSystem = $container->get('file_system');
    $instance->streamWrapperManager = $container->get('stream_wrapper_manager');
    $instance->languageManager = $container->get('language_manager');
    $instance->loggerFactory = $container->get('logger.factory');
    // file.repository is only available when the file module is installed.
    if ($container->has('file.repository')) {
      $instance->fileRepository = $container->get('file.repository');
    }
    return $instance;
  }

  // ---------------------------------------------------------------------------
  // Export
  // ---------------------------------------------------------------------------

  /**
   * {@inheritDoc}
   */
  public function exportEntity(ContentEntityInterface $entity, array $extra_data = []): array {
    $entity_type = $entity->getEntityType();

    $entity_data = [
      'entity_type' => $entity_type->id(),
      'bundle'      => $entity_type->id(),
      'uuid'        => $entity->uuid(),
      'is_translation' => !$entity->isDefaultTranslation(),
      'language'    => $entity->language()->getId(),
      'fields'      => [],
    ];

    if ($entity_type->hasKey('bundle')) {
      $entity_data['bundle'] = $entity->get($entity_type->getKey('bundle'))->getString();
    }

    // Push this entity onto the circular-reference guard stack.
    $this->exportStack[] = $entity->uuid();

    $revision_key = $entity_type->hasKey('revision')
      ? $entity_type->getKey('revision')
      : NULL;

    try {
      foreach ($entity->getFieldDefinitions() as $field_name => $field_definition) {
        // Skip revision key field.
        if ($revision_key && $field_name === $revision_key) {
          continue;
        }
        // Skip fields that are always managed by Drupal on save.
        if (in_array($field_name, self::SKIP_FIELDS, TRUE)) {
          continue;
        }
        // Skip revision_* prefixed fields.
        if (str_starts_with($field_name, 'revision_')) {
          continue;
        }

        $entity_data['fields'][$field_name] = $this->exportField($entity, $field_name);
      }
    }
    finally {
      // Always pop the stack, even when exportField() throws, so the UUID does
      // not permanently poison the circular-reference guard.
      array_pop($this->exportStack);
    }

    // Merge any caller-supplied extra data into the fields array.
    $entity_data['fields'] = NestedArray::mergeDeepArray(
      [$entity_data['fields'], $extra_data],
      TRUE
    );

    return $entity_data;
  }

  /**
   * Dispatches a single field to the appropriate export handler.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being exported.
   * @param string $field_name
   *   The field machine name.
   *
   * @return array
   *   Serialized field value(s).
   */
  private function exportField(ContentEntityInterface $entity, string $field_name): array {
    $field_definition = $entity->getFieldDefinition($field_name);
    if ($field_definition === NULL) {
      return [];
    }

    $field_type = $field_definition->getType();

    if (in_array($field_type, ['entity_reference', 'entity_reference_revisions'], TRUE)) {
      return $this->exportEntityReferenceField($entity, $field_name);
    }

    if (in_array($field_type, ['file', 'image'], TRUE)) {
      return $this->exportFileField($entity, $field_name);
    }

    // Scalar types and any unknown types: best-effort raw getValue().
    return $entity->get($field_name)->getValue();
  }

  /**
   * Exports an entity reference field by recursively exporting each target.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The host entity.
   * @param string $field_name
   *   The field machine name.
   *
   * @return array
   *   Array of items, each wrapped in ['entity_data' => ...].
   */
  private function exportEntityReferenceField(ContentEntityInterface $entity, string $field_name): array {
    $items = [];

    foreach ($entity->get($field_name) as $item) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface|null $referenced */
      $referenced = $item->entity ?? NULL;

      if (!$referenced instanceof ContentEntityInterface) {
        continue;
      }

      // Circular reference guard: skip if already in the export stack.
      if (in_array($referenced->uuid(), $this->exportStack, TRUE)) {
        continue;
      }

      $items[] = ['entity_data' => $this->exportEntity($referenced)];
    }

    return $items;
  }

  /**
   * Exports a file or image field, encoding file contents as base64.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The host entity.
   * @param string $field_name
   *   The field machine name.
   *
   * @return array
   *   Array of items with file metadata and base64-encoded content.
   */
  private function exportFileField(ContentEntityInterface $entity, string $field_name): array {
    if ($this->fileRepository === NULL) {
      // File module not installed; fall back to raw value.
      return $entity->get($field_name)->getValue();
    }

    $items = [];

    foreach ($entity->get($field_name) as $item) {
      /** @var \Drupal\file\FileInterface|null $file */
      $file = $item->entity ?? NULL;

      if ($file === NULL) {
        continue;
      }

      $file_uri = $file->getFileUri();
      $file_contents = @file_get_contents($file_uri);

      // Collect any extra properties stored on the field item (alt, title, etc.)
      $extra_properties = $item->getValue();
      // Remove the target_id key — we reconstruct the file reference on import.
      unset($extra_properties['target_id']);

      $item_data = [
        'filename'  => $file->getFilename(),
        'uri'       => $file_uri,
        'filemime'  => $file->getMimeType(),
        'file_data' => $file_contents !== FALSE ? base64_encode($file_contents) : NULL,
      ];

      // Merge extra properties (alt, title, width, height for image fields).
      $items[] = array_merge($item_data, $extra_properties);
    }

    return $items;
  }

  // ---------------------------------------------------------------------------
  // Import
  // ---------------------------------------------------------------------------

  /**
   * {@inheritDoc}
   */
  public function importEntity(array $data): bool {
    // Preserve the previous importing state so nested recursive calls don't
    // accidentally clear a flag already set by an outer caller (e.g. the HTTP
    // controller). The finally block restores it unconditionally.
    $wasImporting = MultisiteHelper::isImporting();
    MultisiteHelper::setImporting(TRUE);

    try {
      return $this->doImportEntity($data);
    }
    catch (\Throwable $e) {
      $this->loggerFactory->get('multisite_helper')->error(
        'Advanced entity processor failed to import @type (@uuid): @message',
        [
          '@type'    => $data['entity_type'] ?? 'unknown',
          '@uuid'    => $data['uuid'] ?? 'unknown',
          '@message' => $e->getMessage(),
        ]
      );
      return FALSE;
    }
    finally {
      MultisiteHelper::setImporting($wasImporting);
    }
  }

  /**
   * Internal import implementation, called from the try/catch wrapper.
   *
   * @param array $data
   *   Exported entity data as produced by exportEntity().
   *
   * @return bool
   *   TRUE on success, FALSE when the bundle does not exist.
   *
   * @throws \Exception
   */
  private function doImportEntity(array $data): bool {
    $entity_type_id = $data['entity_type'];
    $bundle         = $data['bundle'] ?? $entity_type_id;

    // Bail out early if the bundle is not present on this site.
    if (!array_key_exists($bundle, $this->bundleInfo->getBundleInfo($entity_type_id))) {
      return FALSE;
    }

    $storage     = $this->entityTypeManager->getStorage($entity_type_id);
    $entity_type = $storage->getEntityType();

    // Load existing entity by UUID or create a new stub.
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->entityRepository->loadEntityByUuid($entity_type_id, $data['uuid']);

    if ($entity === NULL) {
      $init_data = ['uuid' => $data['uuid']];
      if (!empty($bundle) && $entity_type->hasKey('bundle')) {
        $init_data[$entity_type->getKey('bundle')] = $bundle;
      }
      $entity = $storage->create($init_data);
    }

    // Handle translations: switch to (or create) the correct translation.
    if (!empty($data['is_translation']) && !empty($data['language'])) {
      $langcode = $data['language'];
      $entity   = $entity->hasTranslation($langcode)
        ? $entity->getTranslation($langcode)
        : $entity->addTranslation($langcode, $entity->toArray());
    }

    $revision_key = $entity_type->hasKey('revision')
      ? $entity_type->getKey('revision')
      : NULL;

    $field_definitions = $entity->getFieldDefinitions();

    foreach ($data['fields'] as $field_name => $values) {
      // Never set the revision key — Drupal manages it.
      if ($revision_key && $field_name === $revision_key) {
        continue;
      }

      $this->importField($entity, $field_name, (array) $values, $field_definitions);
    }

    $entity->save();

    return TRUE;
  }

  /**
   * Dispatches a single field to the appropriate import handler.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being populated.
   * @param string $field_name
   *   The field machine name.
   * @param array $values
   *   The serialized field values from the export payload.
   * @param \Drupal\Core\Field\FieldDefinitionInterface[] $field_definitions
   *   All field definitions for the entity.
   */
  private function importField(
    ContentEntityInterface $entity,
    string $field_name,
    array $values,
    array $field_definitions,
  ): void {
    if (!$entity->hasField($field_name)) {
      return;
    }

    $field_type = isset($field_definitions[$field_name])
      ? $field_definitions[$field_name]->getType()
      : NULL;

    if (in_array($field_type, ['entity_reference', 'entity_reference_revisions'], TRUE)) {
      $this->importEntityReferenceField($entity, $field_name, $values, $field_definitions);
      return;
    }

    if (in_array($field_type, ['file', 'image'], TRUE)) {
      $this->importFileField($entity, $field_name, $values);
      return;
    }

    // Scalar and unknown types: set directly.
    $entity->set($field_name, $values);
  }

  /**
   * Imports an entity reference field by recursively importing each target.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The host entity.
   * @param string $field_name
   *   The field machine name.
   * @param array $values
   *   Array of items, each potentially containing an 'entity_data' key.
   * @param \Drupal\Core\Field\FieldDefinitionInterface[] $field_definitions
   *   All field definitions for the host entity, used to detect
   *   entity_reference_revisions fields that require target_revision_id.
   */
  private function importEntityReferenceField(
    ContentEntityInterface $entity,
    string $field_name,
    array $values,
    array $field_definitions,
  ): void {
    $references = [];

    foreach ($values as $item) {
      if (!isset($item['entity_data'])) {
        // Plain target_id reference — pass through as-is.
        $references[] = $item;
        continue;
      }

      $ref_data        = $item['entity_data'];
      $ref_entity_type = $ref_data['entity_type'] ?? NULL;
      $ref_uuid        = $ref_data['uuid'] ?? NULL;

      if ($ref_entity_type === NULL || $ref_uuid === NULL) {
        continue;
      }

      // Recursively import the referenced entity first.
      $this->importEntity($ref_data);

      // Now load it by UUID so we can build the reference.
      $referenced = $this->entityRepository->loadEntityByUuid($ref_entity_type, $ref_uuid);
      if ($referenced !== NULL) {
        $ref = ['target_id' => $referenced->id()];
        // entity_reference_revisions fields (e.g. Paragraphs) also require
        // target_revision_id to load the correct revision.
        if (isset($field_definitions[$field_name])
            && $field_definitions[$field_name]->getType() === 'entity_reference_revisions'
            && method_exists($referenced, 'getRevisionId')) {
          $ref['target_revision_id'] = $referenced->getRevisionId();
        }
        $references[] = $ref;
      }
    }

    $entity->set($field_name, $references);
  }

  /**
   * Imports a file or image field, decoding base64 content when present.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The host entity.
   * @param string $field_name
   *   The field machine name.
   * @param array $values
   *   Array of items, each potentially containing a 'file_data' key.
   */
  private function importFileField(
    ContentEntityInterface $entity,
    string $field_name,
    array $values,
  ): void {
    if ($this->fileRepository === NULL) {
      // File module not installed; skip silently.
      return;
    }

    $file_references = [];

    foreach ($values as $item) {
      if (!isset($item['file_data']) || $item['file_data'] === NULL) {
        // No embedded content — pass through any existing target_id.
        if (isset($item['target_id'])) {
          $file_references[] = $item;
        }
        continue;
      }

      $destination_uri = $item['uri'] ?? NULL;
      if ($destination_uri === NULL) {
        continue;
      }

      // Ensure the destination directory exists.
      $directory = $this->fileSystem->dirname($destination_uri);
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

      $file_contents = base64_decode($item['file_data'], TRUE);
      if ($file_contents === FALSE) {
        $this->loggerFactory->get('multisite_helper')->warning(
          'Advanced processor: could not base64-decode file_data for URI @uri.',
          ['@uri' => $destination_uri]
        );
        continue;
      }

      try {
        $file = $this->fileRepository->writeData(
          $file_contents,
          $destination_uri,
          FileExists::Replace
        );
      }
      catch (\Throwable $e) {
        $this->loggerFactory->get('multisite_helper')->error(
          'Advanced processor: failed to write file @uri: @message',
          ['@uri' => $destination_uri, '@message' => $e->getMessage()]
        );
        continue;
      }

      // Build the field item value, preserving extra properties (alt, title…).
      $ref = ['target_id' => $file->id()];
      foreach (['alt', 'title', 'width', 'height', 'description'] as $prop) {
        if (isset($item[$prop])) {
          $ref[$prop] = $item[$prop];
        }
      }

      $file_references[] = $ref;
    }

    $entity->set($field_name, $file_references);
  }

}

<?php

declare(strict_types=1);

namespace Drupal\multisite_helper\Plugin\MultisiteHelperEntityProcessor;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\multisite_helper\Attribute\MultisiteHelperEntityProcessor;
use Drupal\multisite_helper\MultisiteHelperEntityProcessorPluginBase;
use Drupal\single_content_sync\ContentExporterInterface;
use Drupal\single_content_sync\ContentImporterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the multisite_helper_entity_processor.
 */
#[MultisiteHelperEntityProcessor(
  id: 'single_content_sync',
  label: new TranslatableMarkup('Single Content Sync importer/exporter'),
  description: new TranslatableMarkup('Uses the import/export functionality of the single content sync module, supports much more complex importing/exporting.'),
  module_dependencies: ['single_content_sync'],
)]
final class SingleContentSync extends MultisiteHelperEntityProcessorPluginBase {

  private readonly ContentImporterInterface $contentImporter;

  private readonly ContentExporterInterface $contentExporter;

  private readonly EntityRepositoryInterface $entityRepository;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->contentImporter = $container->get('single_content_sync.importer');
    $instance->contentExporter = $container->get('single_content_sync.exporter');
    $instance->entityRepository = $container->get('entity.repository');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function importEntity(array $data): bool {
    $this->contentImporter->doImport($data);
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function exportEntity(ContentEntityInterface $entity, array $extra_data = []): array {
    $entity_data = $this->contentExporter->doExportToArray($entity);
    // Add extra data to the custom_fields array item.
    $entity_data['custom_fields'] = NestedArray::mergeDeepArray([$entity_data['custom_fields'], $extra_data]);
    return $entity_data;
  }

  /**
   * {@inheritDoc}
   */
  public function deleteEntity(array $data): bool {
    $entity = $this->entityRepository->loadEntityByUuid($data['entity_type'], $data['uuid']);
    return (bool) $entity?->delete();
  }

}

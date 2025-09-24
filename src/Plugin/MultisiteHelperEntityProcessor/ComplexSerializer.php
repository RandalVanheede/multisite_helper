<?php

declare(strict_types=1);

namespace Drupal\multisite_helper\Plugin\MultisiteHelperEntityProcessor;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\multisite_helper\Attribute\MultisiteHelperEntityProcessor;
use Drupal\multisite_helper\MultisiteHelperEntityProcessorPluginBase;
use Drupal\multisite_helper_complex_serializer\SerializerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the multisite_helper_entity_processor.
 */
#[MultisiteHelperEntityProcessor(
  id: 'complex_serializer',
  label: new TranslatableMarkup('Complex Serializer'),
  description: new TranslatableMarkup('Uses the import/export functionality of the complex_serial submodule, supports complex entities.'),
  module_dependencies: ['multisite_helper_complex_serializer'],
)]
final class ComplexSerializer extends MultisiteHelperEntityProcessorPluginBase {

  private readonly SerializerInterface $serializer;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->serializer = $container->get('multisite_helper.serializer');
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

    $this->serializer->deserialize($data);
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function exportEntity(ContentEntityInterface $entity, array $extra_data = []): array {
    $entity_data = $this->serializer->serialize($entity, TRUE);
    // Add extra data to the custom_fields array item.
    $entity_data['fields'] = NestedArray::mergeDeepArray([$entity_data['fields'], $extra_data], TRUE);
    return $entity_data;
  }

}

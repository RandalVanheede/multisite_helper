<?php

declare(strict_types=1);

namespace Drupal\multisite_helper;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Render\Markup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a listing of multisite helper subsites.
 */
final class MhSubsiteListBuilder extends ConfigEntityListBuilder {

  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    private readonly MultisiteHelperInterface $helper,
    private readonly RequestStack $requestStack,
  ) {
    parent::__construct($entity_type, $storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('multisite_helper'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['subsite'] = $this->t('Subsite');
    $header['url'] = $this->t('Url');
    $header['status'] = $this->t('Status');
    $header['accessible'] = $this->t('Accessible');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\multisite_helper\MhSubsiteInterface $entity */
    $row['subsite'] = $entity->label() . ' (' . $entity->id() . ')';
    $row['url'] = $entity->url();
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    $row['accessible'] = $this->helper->ping($entity->url(), $entity->authorization()) ? '✔' : '✖';

    $host = $this->requestStack->getCurrentRequest()?->getHttpHost();
    if ($host !== null && in_array($row['url'], [
      'http://' . $host,
      'https://' . $host,
    ])) {
      foreach ($row as &$row_item) {
        $row_item = Markup::create("<strong>$row_item</strong>");
      }
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    $operations = parent::getOperations($entity);
    if (isset($operations['edit'])) {
      $url = &$operations['edit']['url'];
      $attributes = $url->getOption('attributes');
      $attributes['class'][] = 'use-ajax';
      $attributes['data-dialog-type'] = 'modal';
      $attributes['data-dialog-options'] = Json::encode(['width' => 600]);
      $url->setOption('attributes', $attributes);
    }
    return $operations;
  }

}

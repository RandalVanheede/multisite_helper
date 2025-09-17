<?php

declare(strict_types=1);

namespace Drupal\multisite_helper;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\Markup;

/**
 * Provides a listing of multisite helper subsites.
 */
final class MhSubsiteListBuilder extends ConfigEntityListBuilder {

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
    $row['accessible'] = MultisiteHelper::ping($entity->url()) ? '✔' : '✖';

    $host = \Drupal::request()->getHttpHost();
    if (in_array($row['url'], [
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

<?php

declare(strict_types=1);

namespace Drupal\multisite_helper;

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
    $row['url'] = $entity->get('url');
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    $row['accessible'] = \Drupal::service('multisite_helper')->ping($entity->get('url')) ? '✔' : '✖';

    if (\Drupal::request()->getSchemeAndHttpHost() === $row['url']) {
      foreach ($row as &$row_item) {
        $row_item = Markup::create("<strong>$row_item</strong>");
      }
    }

    return $row + parent::buildRow($entity);
  }

}

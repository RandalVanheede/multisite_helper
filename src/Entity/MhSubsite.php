<?php

declare(strict_types=1);

namespace Drupal\multisite_helper\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\multisite_helper\Form\MhSubsiteForm;
use Drupal\multisite_helper\MhSubsiteInterface;
use Drupal\multisite_helper\MhSubsiteListBuilder;

/**
 * Defines the multisite helper subsite entity type.
 */
#[ConfigEntityType(
  id: 'mh_subsite',
  label: new TranslatableMarkup('Multisite Helper Subsite'),
  label_collection: new TranslatableMarkup('Multisite Helper Subsites'),
  label_singular: new TranslatableMarkup('multisite helper subsite'),
  label_plural: new TranslatableMarkup('multisite helper subsites'),
  config_prefix: 'mh_subsite',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => MhSubsiteListBuilder::class,
    'form' => [
      'add' => MhSubsiteForm::class,
      'edit' => MhSubsiteForm::class,
      'delete' => EntityDeleteForm::class,
    ],
  ],
  links: [
    'collection' => '/admin/config/system/multisite-helper/subsites',
    'add-form' => '/admin/config/system/multisite-helper/subsites/add',
    'edit-form' => '/admin/config/system/multisite-helper/subsites/{mh_subsite}',
    'delete-form' => '/admin/config/system/multisite-helper/subsites/{mh_subsite}/delete',
  ],
  admin_permission: 'administer mh_subsite',
  label_count: [
    'singular' => '@count multisite helper subsite',
    'plural' => '@count multisite helper subsites',
  ],
  config_export: [
    'id',
    'status',
    'label',
    'description',
    'url',
  ],
)]
final class MhSubsite extends ConfigEntityBase implements MhSubsiteInterface {

  public const ENABLED = 1;
  public const DISABLED = 0;

  /**
   * The example ID.
   */
  protected string $id;

  /**
   * The example label.
   */
  protected string $label;

  /**
   * The example description.
   */
  protected string $description;

  /**
   * The example description.
   */
  protected string $url;

}

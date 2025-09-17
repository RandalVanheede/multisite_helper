<?php

declare(strict_types=1);

namespace Drupal\multisite_helper;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a multisite helper subsite entity type.
 */
interface MhSubsiteInterface extends ConfigEntityInterface {

  /**
   * Returns the base64 encoded authorization string.
   */
  public function authorization(): ?string;

  /**
   * Returns the subsite's URL.
   */
  public function url(): string;

}

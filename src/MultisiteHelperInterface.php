<?php

namespace Drupal\multisite_helper;

interface MultisiteHelperInterface {

  /**
   * Sends data to the given set of sites.
   */
  public function sendToSites(string $plugin_id, array $data, array $sites): bool;

  /**
   * REmoves data from the given set of sites.
   */
  public function removeFromSites(string $plugin_id, array $data, array $sites): bool;

}

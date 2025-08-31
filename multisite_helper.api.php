<?php

/**
 * @file
 * Provides the available hooks for the multisite_helper module.
 */

/**
 * Allows altering the plugin data and sites to which the data will be sent.
 */
function hook_multisite_helper_plugin_data_alter(array &$data, array &$sites, string $plugin_id): void {
  if ($plugin_id === 'my_plugin') {
    $data['extra_variable'] = 'Something';

    $sites[] = 'force-sync-to-this-site.ddev.site';
    $sites = array_unique($sites);
  }
}

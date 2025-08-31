<?php

namespace Drupal\multisite_helper;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

class MultisiteHelper implements MultisiteHelperInterface {

  use StringTranslationTrait;

  /**
   * Construct the multisite helper class.
   */
  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly LoggerChannelFactoryInterface $loggerChannelFactory,
    private readonly MessengerInterface $messenger,
    private readonly ClientInterface $httpClient,
  ) {}

  /**
   * Retrieves the main subsite name.
   */
  public static function getMainSiteName(): string {
    return static::getSetting('main_site');
  }

  /**
   * Retrieves the current subsite name.
   */
  public static function getCurrentSiteName(): string {
    return static::getSetting('current_site');
  }

  /**
   * Returns the hostname for a given sitename.
   */
  public static function getHostnameForSite(string $sitename): string|bool {
    $sites = static::getSetting('sites_by_sitename');
    return $sites[$sitename] ?? FALSE;
  }

  /**
   * Retrieves the other subsites' hostnames.
   */
  public static function getOtherSiteHostnames(): array {
    $hostname = \Drupal::request()->getHost();
    $sites = static::getSetting('sites_by_hostname');
    return array_values(array_filter(array_keys($sites), static function ($site) use ($hostname) {
      return $site !== $hostname;
    }));
  }

  /**
   * Retrieves the other subsites formatted as an options array.
   */
  public static function getOtherSitesAsOptions(): array {
    $hostname = \Drupal::request()->getHost();
    $sites_readable = static::getSetting('sites_readable', []);
    $sites = static::getSetting('sites_by_sitename');

    // Filter the current website out, and use the readable sitename if available.
    return array_map(static function ($hostname) use ($sites_readable) {
      return $sites_readable[$hostname] ?? $hostname;
    }, array_filter($sites, static function ($site) use ($hostname) {
      return $site !== $hostname;
    }));
  }

  /**
   * Retrieves a specific setting.
   */
  public static function getSetting(string $key, $default = NULL): mixed {
    $settings = static::getSettings();
    return $settings[$key] ?? $default;
  }

  /**
   * Retrieve the full settings array.
   */
  private static function getSettings(): array {
    return Settings::get('subsite_helper', []);
  }

  /**
   * {@inheritDoc}
   */
  public function sendToSites(string $plugin_id, array $data, array $sites): bool {
    // Allow the plugin data and sites array to be altered.
    $plugin_id_copy = $plugin_id;
    $this->moduleHandler->alter('multisite_helper_plugin_data', $data, $sites, $plugin_id_copy);

    $result = TRUE;
    foreach ($sites as $hostname) {
      try {
        $response = $this->httpClient->post(
          uri: 'https://' . $hostname . '/api/multisite-helper/sync-data/' . $plugin_id,
          options: [
            RequestOptions::HEADERS => [
              'Content-Type' => 'application/json',
              'X-Api-Key' => static::getSetting('api_key'),
            ],
            RequestOptions::JSON => $data,
          ],
        );
      }
      catch (\Exception|GuzzleException $e) {
        // Log a user friendly message.
        $this->messenger
          ->addError('Something went wrong while syncing data to subsite.');

        // Then log debugging data.
        $this->loggerChannelFactory
          ->get('multisite_helper')
          ->debug($e->getMessage());

        $result = FALSE;
        continue;
      }

      if ($response->getStatusCode() > 299 || $response->getStatusCode() < 200) {
        $this->loggerChannelFactory
          ->get('multisite_helper')
          ->error('Something went wrong while syncing data to subsite.');
      }
    }

    $this->loggerChannelFactory
      ->get('multisite_helper')
      ->info($this->t('The @plugin_id item has been processed.', ['@plugin_id' => $plugin_id]));

    return $result;
  }

  /**
   * {@inheritDoc}
   */
  public function removeFromSites(string $plugin_id, array $data, array $sites): bool {
    // Allow the plugin data and sites array to be altered.
    $plugin_id_copy = $plugin_id;
    $this->moduleHandler->alter('multisite_helper_plugin_data', $data, $sites, $plugin_id_copy);

    $result = TRUE;
    foreach ($sites as $hostname) {
      try {
        $response = $this->httpClient->delete(
          uri: 'https://' . $hostname . '/api/multisite-helper/sync-data/' . $plugin_id,
          options: [
            RequestOptions::HEADERS => [
              'Content-Type' => 'application/json',
              'X-Api-Key' => static::getSetting('api_key'),
            ],
            RequestOptions::JSON => $data,
          ],
        );
      }
      catch (\Exception|GuzzleException $e) {
        // Log a user friendly message.
        $this->messenger
          ->addError('Something went wrong while syncing data to subsite.');

        // Then log debugging data.
        $this->loggerChannelFactory
          ->get('multisite_helper')
          ->debug($e->getMessage());

        $result = FALSE;
        continue;
      }

      if ($response->getStatusCode() > 299 || $response->getStatusCode() < 200) {
        $this->loggerChannelFactory
          ->get('multisite_helper')
          ->error('Something went wrong while syncing data to subsite.');
      }
    }

    $this->loggerChannelFactory
      ->get('multisite_helper')
      ->info($this->t('The @plugin_id item has been processed.', ['@plugin_id' => $plugin_id]));

    return $result;
  }

}

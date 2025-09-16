<?php

namespace Drupal\multisite_helper;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\multisite_helper\Entity\MhSubsite;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class MultisiteHelper implements MultisiteHelperInterface {

  use StringTranslationTrait;

  /**
   * The importing flag, this flag should be checked when saving entities
   * to prevent infinite save/delete loops.
   */
  private static bool $isImporting = FALSE;

  private MultisiteHelperEntityProcessorInterface $entityProcessor;

  private ImmutableConfig $config;

  /**
   * Construct the multisite helper class.
   */
  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly LoggerChannelFactoryInterface $loggerChannelFactory,
    private readonly MessengerInterface $messenger,
    private readonly ClientInterface $httpClient,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly MultisiteHelperEntityProcessorPluginManager $entityProcessorPluginManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RequestStack $requestStack,
  ) {
    $this->config = $this->configFactory->get('multisite_helper.settings');
  }

  /**
   * {@inheritDoc}
   */
  public function getCurrentSiteId(): string|bool {
    $scheme_and_host = $this->requestStack->getCurrentRequest()
      ->getSchemeAndHttpHost();
    $subsites = $this->entityTypeManager
      ->getStorage('mh_subsite')
      ->loadByProperties(['url' => $scheme_and_host]);
    $subsite = reset($subsites);
    return $subsite ? $subsite->id() : FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function getCurrentSiteLabel(): string|bool {
    $scheme_and_host = $this->requestStack->getCurrentRequest()
      ->getSchemeAndHttpHost();
    $subsites = $this->entityTypeManager
      ->getStorage('mh_subsite')
      ->loadByProperties(['url' => $scheme_and_host]);
    $subsite = reset($subsites);
    return $subsite ? $subsite->label() : FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function getHostnameForSite(string $site_id): string|bool {
    $subsite = $this->entityTypeManager
      ->getStorage('mh_subsite')
      ->load($site_id);
    return $subsite ? $subsite->label() : FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function getOtherSiteHostnames(): array {
    $scheme_and_host = $this->requestStack->getCurrentRequest()
      ->getSchemeAndHttpHost();
    $storage = $this->entityTypeManager->getStorage('mh_subsite');
    $subsite_ids = $storage->getQuery()
      ->condition('status', MhSubsite::ENABLED)
      ->condition('url', $scheme_and_host, '<>')
      ->execute();

    return array_values(array_filter(array_map(function ($subsite_id) use ($storage) {
      $subsite = $storage->load($subsite_id);
      return $subsite ? $subsite->get('url') : NULL;
    }, $subsite_ids)));
  }

  /**
   * {@inheritDoc}
   */
  public static function getOtherSitesAsOptions(): array {
    $scheme_and_host = \Drupal::request()->getSchemeAndHttpHost();
    $storage = \Drupal::entityTypeManager()->getStorage('mh_subsite');
    $subsite_ids = $storage->getQuery()
      ->condition('status', MhSubsite::ENABLED)
      ->condition('url', $scheme_and_host, '<>')
      ->execute();

    return array_filter(array_map(function ($subsite_id) use ($storage) {
      $subsite = $storage->load($subsite_id);
      return $subsite ? $subsite->label() : NULL;
    }, $subsite_ids));
  }

  /**
   * {@inheritDoc}
   */
  public function sendToSites(string $plugin_id, array $data, array $sites): bool {
    // Force remove the current site from the list of sites.
    $scheme_and_host = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();
    if ($current_site_delta = array_search($scheme_and_host, $sites)) {
      unset($sites[$current_site_delta]);
    }

    // Allow the plugin data and sites array to be altered.
    $plugin_id_copy = $plugin_id;
    $this->moduleHandler->alter('multisite_helper_plugin_data', $data, $sites, $plugin_id_copy);

    $requests = [];
    foreach ($sites as $hostname) {
      $requests[$hostname] = new Request(
        method: 'POST',
        uri: $hostname . '/api/multisite-helper/sync-data/' . $plugin_id,
        headers: [
          'Content-Type' => 'application/json',
          'X-Api-Key' => $this->config->get('api_key'),
        ],
        body: Json::encode($data),
      );
    }

    return $this->sendAsyncRequests($plugin_id_copy, $requests);
  }

  /**
   * {@inheritDoc}
   */
  public function removeFromSites(string $plugin_id, array $data, array $sites): bool {
    // Allow the plugin data and sites array to be altered.
    $plugin_id_copy = $plugin_id;
    $this->moduleHandler->alter('multisite_helper_plugin_data', $data, $sites, $plugin_id_copy);

    $requests = [];
    foreach ($sites as $hostname) {
      $requests[$hostname] = new Request(
        method: 'DELETE',
        uri: 'https://' . $hostname . '/api/multisite-helper/sync-data/' . $plugin_id,
        headers: [
          'Content-Type' => 'application/json',
          'X-Api-Key' => $this->config->get('api_key'),
        ],
        body: Json::encode($data),
      );
    }

    return $this->sendAsyncRequests($plugin_id_copy, $requests);
  }

  /**
   * Sends a set of predefined requests asynchronously.
   */
  private function sendAsyncRequests(string $plugin_id, array $requests): bool {
    $result = TRUE;

    try {
      $pool = new Pool($this->httpClient, $requests, [
        'concurrency' => $this->config->get('concurrent_calls') ?: 5,
        'fulfilled' => function (ResponseInterface $response, $index) {},
        'rejected' => function (RequestException $reason, $index) {
          // Log a user friendly message.
          $this->messenger
            ->addError('Something went wrong while syncing data to subsite.');
          // Then log debugging data.
          $this->loggerChannelFactory->get('multisite_helper')->debug($reason->getMessage());
        },
      ]);

      $pool->promise()->wait();
    }
    catch (\Exception|\Throwable $e) {
      // Log a user friendly message.
      $this->messenger
        ->addError('Something went wrong while syncing data to subsite.');

      // Then log debugging data.
      $this->loggerChannelFactory
        ->get('multisite_helper')
        ->debug($e->getMessage());

      $result = FALSE;
    }

    $this->loggerChannelFactory
      ->get('multisite_helper')
      ->info($this->t('The @plugin_id item has been processed.', ['@plugin_id' => $plugin_id]));

    return $result;
  }

  /**
   * Retrieves the entity processor plugin.
   */
  private function getEntityProcessor(): MultisiteHelperEntityProcessorInterface {
    if (empty($this->entityProcessor)) {
      $plugin_id = $this->config->get('entity_processor');
      $this->entityProcessor = $this->entityProcessorPluginManager->createInstance($plugin_id);
    }

    return $this->entityProcessor;
  }

  /**
   * {@inheritDoc}
   */
  public function getEntityBaseInformation(array $data): array {
    return $this->getEntityProcessor()->getEntityBaseInformation($data);
  }

  /**
   * {@inheritDoc}
   */
  public function importEntity(array $data): bool {
    return $this->getEntityProcessor()->importEntity($data);
  }

  /**
   * {@inheritDoc}
   */
  public function exportEntity(ContentEntityInterface $entity, array $extra_data = []): array {
    return $this->getEntityProcessor()->exportEntity($entity, $extra_data);
  }

  /**
   * {@inheritDoc}
   */
  public function deleteEntity(array $data): bool {
    return $this->getEntityProcessor()->deleteEntity($data);
  }

  /**
   * {@inheritDoc}
   */
  public static function setImporting(bool $importing = TRUE): void {
    self::$isImporting = $importing;
  }

  /**
   * {@inheritDoc}
   */
  public static function isImporting(): bool {
    return self::$isImporting;
  }

  /**
   * {@inheritDoc}
   */
  public static function ping(string $url): ?bool {
    if (!in_array('curl', get_loaded_extensions())) {
      return NULL;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $http_code >= 200 && $http_code < 400;
  }

}

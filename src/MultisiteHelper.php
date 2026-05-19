<?php

declare(strict_types=1);

namespace Drupal\multisite_helper;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\multisite_helper\Entity\MhSubsite;
use Drupal\multisite_helper\Event\AlterPluginDataEvent;
use Drupal\multisite_helper\Event\MultisiteHelperEvents;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class MultisiteHelper implements MultisiteHelperInterface {

  use StringTranslationTrait;

  /**
   * The importing flag, this flag should be checked when saving entities
   * to prevent infinite save/delete loops.
   */
  private static bool $isImporting = FALSE;

  private static ?MhSubsiteInterface $currentSubsite;

  private MultisiteHelperEntityProcessorInterface $entityProcessor;

  private ImmutableConfig $config;

  /**
   * Construct the multisite helper class.
   */
  public function __construct(
    private readonly LoggerChannelFactoryInterface $loggerChannelFactory,
    private readonly MessengerInterface $messenger,
    private readonly ClientInterface $httpClient,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly MultisiteHelperEntityProcessorPluginManager $entityProcessorPluginManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RequestStack $requestStack,
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {
    $this->config = $this->configFactory->get('multisite_helper.settings');
  }

  /**
   * {@inheritDoc}
   */
  public function getCurrentSite(): ?MhSubsiteInterface {
    if (empty(static::$currentSubsite)) {
      $request = $this->requestStack->getCurrentRequest();
      if ($request === NULL) {
        return NULL;
      }
      $host = $request->getHttpHost();

      // Find subsites matching the primary URL (http and https variants).
      $subsites = $this->entityTypeManager
        ->getStorage('mh_subsite')
        ->loadByProperties([
          'url' => [
            'http://' . $host,
            'https://' . $host,
          ],
        ]);

      if ($subsites) {
        static::$currentSubsite = reset($subsites);
      }
      else {
        // Fall back to checking aliases across all subsites.
        $all_subsites = $this->entityTypeManager
          ->getStorage('mh_subsite')
          ->loadMultiple();

        foreach ($all_subsites as $subsite) {
          foreach ($subsite->aliases() as $alias) {
            $alias = rtrim($alias, '/');
            if ($alias === 'http://' . $host || $alias === 'https://' . $host) {
              static::$currentSubsite = $subsite;
              break 2;
            }
          }
        }

        // If still no match, use the default subsite as fallback.
        if (empty(static::$currentSubsite)) {
          foreach ($all_subsites as $subsite) {
            if ($subsite->isDefault()) {
              static::$currentSubsite = $subsite;
              break;
            }
          }
        }
      }
    }

    return static::$currentSubsite;
  }

  /**
   * {@inheritDoc}
   */
  public function getCurrentSiteId(): ?string {
    return $this->getCurrentSite()?->id();
  }

  /**
   * {@inheritDoc}
   */
  public function getOtherSubsites(int $status = MhSubsite::ENABLED): array {
    $storage = $this->entityTypeManager->getStorage('mh_subsite');

    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', $status)
      ->sort('weight');

    // Only exclude the current site when we can identify it.
    if ($currentId = $this->getCurrentSiteId()) {
      $query->condition('id', $currentId, '<>');
    }

    return $storage->loadMultiple($query->execute());
  }

  /**
   * {@inheritDoc}
   */
  public function sendToSites(string $method, string $plugin_id, array $data, array $sites): bool {
    // Force remove the current site from the list of sites.
    $currentSiteId = $this->getCurrentSiteId();
    if ($currentSiteId) {
      $sites = array_filter($sites, static function (MhSubsiteInterface $site) use ($currentSiteId) {
        return $site->id() !== $currentSiteId;
      });
    }

    // Allow the plugin data and sites array to be altered.
    $event = new AlterPluginDataEvent($plugin_id, $data, $sites);
    $this->eventDispatcher->dispatch($event, MultisiteHelperEvents::ALTER_PLUGIN_DATA);
    $data = $event->getPluginData();
    $sites = $event->getSites();

    // Build the requests to be sent.
    $requests = [];
    /** @var \Drupal\multisite_helper\MhSubsiteInterface[] $sites */
    foreach ($sites as $site) {
      $headers = [];
      if ($auth = $site->authorization()) {
        $headers['Authorization'] = 'Basic ' . $auth;
      }

      $requests[$site->url()] = new Request(
        method: $method,
        uri: $site->url() . '/api/multisite-helper/sync-data/' . $plugin_id,
        headers: [
          'Content-Type' => 'application/json',
          'X-Api-Key' => $this->config->get('api_key'),
        ] + $headers,
        body: Json::encode($data),
      );
    }

    $result = TRUE;
    $logger = $this->loggerChannelFactory->get('multisite_helper');

    try {
      $pool = new Pool($this->httpClient, $requests, [
        'concurrency' => $this->config->get('concurrent_calls') ?: 5,
        'fulfilled' => function (ResponseInterface $response, $index) {},
        'rejected' => function (RequestException $reason, $index) use ($logger, &$result) {
          // Log a user friendly message.
          $this->messenger->addError('Something went wrong while syncing data to subsite.');
          // Then log debugging data.
          $logger->debug($reason->getMessage());
          $result = FALSE;
        },
      ]);

      $pool->promise()->wait();
    }
    catch (\Exception|\Throwable $e) {
      // Log a user friendly message.
      $this->messenger->addError('Something went wrong while syncing data to subsite.');

      // Then log debugging data.
      $logger->debug($e->getMessage());

      $result = FALSE;
    }

    $logger->info($this->t('The @plugin_id item has been processed.', ['@plugin_id' => $plugin_id]));

    return $result;
  }

  /**
   * {@inheritDoc}
   */
  public function getEntityProcessor(): MultisiteHelperEntityProcessorInterface {
    if (empty($this->entityProcessor)) {
      $plugin_id = $this->config->get('entity_processor');
      $this->entityProcessor = $this->entityProcessorPluginManager->createInstance($plugin_id);
    }

    return $this->entityProcessor;
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
  public function ping(string $url, ?string $authorization = NULL): bool {
    try {
      $headers = [];
      if ($authorization) {
        $headers['Authorization'] = 'Basic ' . $authorization;
      }

      $response = $this->httpClient->request('GET', $url, [
        'timeout' => 5,
        'connect_timeout' => 5,
        'headers' => $headers,
        'http_errors' => FALSE,
      ]);

      $http_code = $response->getStatusCode();
      return $http_code >= 200 && $http_code < 400;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

}

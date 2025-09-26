<?php

namespace Drupal\multisite_helper;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
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
    private readonly ModuleHandlerInterface $moduleHandler,
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
      $host = $this->requestStack->getCurrentRequest()->getHttpHost();

      // Find subsites with http and https.
      $subsites = $this->entityTypeManager
        ->getStorage('mh_subsite')
        ->loadByProperties([
          'url' => [
            'http://' . $host,
            'https://' . $host,
          ],
        ]);

      // Assign the first result as the current subsite.
      static::$currentSubsite = reset($subsites) ?: NULL;
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

    $subsite_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', $status)
      ->condition('id', $this->getCurrentSiteId(), '<>')
      ->sort('weight')
      ->execute();

    return $storage->loadMultiple($subsite_ids);
  }

  /**
   * {@inheritDoc}
   */
  public function sendToSites(string $method, string $plugin_id, array $data, array $sites): bool {
    // Force remove the current site from the list of sites.
    $host = $this->requestStack->getCurrentRequest()->getHttpHost();
    if (($current_site_delta = array_search('http://' . $host, $sites))
      || $current_site_delta = array_search('https://' . $host, $sites)) {
      unset($sites[$current_site_delta]);
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
        'rejected' => function (RequestException $reason, $index) use ($logger) {
          // Log a user friendly message.
          $this->messenger->addError('Something went wrong while syncing data to subsite.');
          // Then log debugging data.
          $logger->debug($reason->getMessage());
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
  public static function ping(string $url, ?string $authorization = NULL): ?bool {
    if (!in_array('curl', get_loaded_extensions())) {
      return NULL;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($authorization) {
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . $authorization,
      ]);
    }
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $http_code >= 200 && $http_code < 400;
  }

}

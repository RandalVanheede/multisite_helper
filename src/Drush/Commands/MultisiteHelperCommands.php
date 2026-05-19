<?php

declare(strict_types=1);

namespace Drupal\multisite_helper\Drush\Commands;

use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\SuspendQueueException;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 */
final class MultisiteHelperCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Constructs a MultisiteHelperCommands object.
   */
  public function __construct(
    private readonly LockBackendInterface $lock,
    private readonly QueueFactory $queueFactory,
    private readonly QueueWorkerManagerInterface $queueWorkerManager,
  ) {
    parent::__construct();
  }

  /**
   * Command description here.
   */
  #[CLI\Command(name: 'multisite_helper:process-queue-items', aliases: ['mh:pqi'])]
  public function processItems() {
    if (!$this->lock->acquire('multisite_helper_process_queue_items', 3600.0)) {
      $this->logger()->warning("The command multisite_helper:process-queue-items is already running, can't run again.");
      return;
    }

    try {
      $this->processSendItems();
      $this->processRemoveItems();
    }
    finally {
      $this->lock->release('multisite_helper_process_queue_items');
    }
  }

  /**
   * Handle the processing of all post/put items.
   */
  private function processSendItems(): void {
    $queue = $this->queueFactory->get('multisite_helper_send_data');
    $queue_worker = $this->queueWorkerManager->createInstance('multisite_helper_send_data');

    $delta = 0;
    $this->output()->writeln('Starting on "POST/PUT"-queue...');
    while ($item = $queue->claimItem()) {
      $delta++;
      try {
        $data = $item->data;
        $queue_worker->processItem($data);
        $queue->deleteItem($item);
        $this->output()->writeln('Processed item #' . $delta);
      }
      catch (SuspendQueueException $e) {
        $queue->releaseItem($item);
        break;
      }
      catch (\Exception $e) {
        $queue->releaseItem($item);
        $this->logger()->error($e->getMessage());
        $this->output()->writeln('Error processing item #' . $delta . ': ' . $e->getMessage());
      }
    }
    $this->output()->writeln('Done with "POST/PUT"-queue...' . PHP_EOL);
  }

  /**
   * Handle the processing of all delete items.
   */
  private function processRemoveItems(): void {
    $queue = $this->queueFactory->get('multisite_helper_remove_data');
    $queue_worker = $this->queueWorkerManager->createInstance('multisite_helper_remove_data');

    $delta = 0;
    $this->output()->writeln('Starting on "DELETE"-queue...');
    while ($item = $queue->claimItem()) {
      $delta++;
      try {
        $data = $item->data;
        $queue_worker->processItem($data);
        $queue->deleteItem($item);
        $this->output()->writeln('Processed item #' . $delta);
      }
      catch (SuspendQueueException $e) {
        $queue->releaseItem($item);
        break;
      }
      catch (\Exception $e) {
        $queue->releaseItem($item);
        $this->logger()->error($e->getMessage());
        $this->output()->writeln('Error processing item #' . $delta . ': ' . $e->getMessage());
      }
    }
    $this->output()->writeln('Done with "DELETE"-queue...');
  }

}

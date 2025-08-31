<?php

namespace Drupal\multisite_helper\Drush\Commands;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\State\StateInterface;
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
    private readonly StateInterface $state,
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
    if ($this->state->get('multisite_helper.process_queue_items.running', FALSE)) {
      $this->logger()->warning("The command mt_vehicle:force-queue is already running, can't run again.");
      return;
    }
    $this->state->set('multisite_helper.process_queue_items.running', TRUE);

    $this->processSendItems();
    $this->processRemoveItems();

    $this->state->set('multisite_helper.process_queue_items.running', FALSE);
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
        $this->output()->writeln($e->getMessage());
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
        $this->output()->writeln($e->getMessage());
      }
    }
    $this->output()->writeln('Done with "DELETE"-queue...');
  }

}

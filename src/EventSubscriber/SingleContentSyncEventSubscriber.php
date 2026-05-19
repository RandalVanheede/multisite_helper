<?php

declare(strict_types=1);

namespace Drupal\multisite_helper\EventSubscriber;

use Drupal\single_content_sync\Event\ImportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class SingleContentSyncEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ImportEvent::class => 'handleImportEvent',
    ];
  }

  /**
   * Alter Single Content Sync imports.
   */
  public function handleImportEvent(ImportEvent $event): void {
    $entity = $event->getEntity();
    $content = $event->getContent();

    // Make sure to only alter items with our custom parameters.
    if (!($content['is_translation'] ?? FALSE) || !isset($content['language'])) {
      return;
    }

    $language = $content['language'];
    $entity = $entity->hasTranslation($language)
      ? $entity->getTranslation($language)
      : $entity->addTranslation($language, $entity->toArray());

    // Set the entity translation.
    $event->setEntity($entity);
  }

}

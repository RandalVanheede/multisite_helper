<?php

namespace Drupal\multisite_helper_ui_translation\EventSubscriber;

use Drupal\locale\LocaleEvent;
use Drupal\locale\LocaleEvents;
use Drupal\locale\StringStorageInterface;
use Drupal\multisite_helper\MultisiteHelperPluginManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class LocaleEventSubscriber implements EventSubscriberInterface {

  public function __construct(
    private StringStorageInterface $stringStorage,
    private MultisiteHelperPluginManager $pluginManager,
  ) {}

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      LocaleEvents::SAVE_TRANSLATION => 'saveTranslation',
    ];
  }

  /**
   * Trigger synchronization of translations with subsites.
   */
  public function saveTranslation(LocaleEvent $event): void {
    /** @var \Drupal\multisite_helper_ui_translation\Plugin\MultisiteHelperPlugin\UiTranslationSync $plugin */
    $plugin = $this->pluginManager->getPlugin('ui_translation_sync');

    foreach ($event->getLangCodes() as $langcode) {
      $translations = $this->stringStorage->getTranslations([
        'lid' => $event->getLids(),
        'language' => $langcode,
      ]);

      foreach ($translations as $translation) {
        $translation = array_filter((array) $translation, static function ($key) {
          return in_array($key, [
            'locations',
            'source',
            'context',
            'version',
            'language',
            'translation',
            'customized',
          ]);
        }, ARRAY_FILTER_USE_KEY);

        $plugin->send($translation);
      }
    }

  }

}

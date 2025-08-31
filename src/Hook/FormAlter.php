<?php

namespace Drupal\multisite_helper\Hook;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\multisite_helper\MultisiteHelperPluginManager;
use Drupal\single_content_sync\ContentExporterInterface;

class FormAlter {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  public function __construct(
    private readonly MultisiteHelperPluginManager $pluginManager,
    private readonly ContentExporterInterface $contentExporter,
  ) {}

  #[Hook('form_alter')]
  public function entityFormAlter(array &$form, FormStateInterface $form_state): void {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof ContentEntityFormInterface) {
      return;
    }

    /** @var \Drupal\multisite_helper\Plugin\MultisiteHelperPlugin\ContentEntitySync $plugin */
    $plugin = $this->pluginManager->getPlugin('content_entity_sync');
    $plugin_config = $plugin->getConfiguration();
    if (empty($plugin_config['enabled'])) {
      return;
    }

    $entity = $form_object->getEntity();
    if (!in_array($entity->getEntityTypeId(), $plugin_config['entity_types'])) {
      return;
    }

    $form['actions']['submit']['#submit'][] = [$this, 'entityFormSubmit'];
  }

  /**
   * Send this node's data to other subsites.
   */
  public function entityFormSubmit(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $form_state->getFormObject()->getEntity();
    $entity_values = $this->contentExporter->doExportToArray($entity);

    /** @var \Drupal\multisite_helper\Plugin\MultisiteHelperPlugin\ContentEntitySync $plugin */
    $plugin = $this->pluginManager->getPlugin('content_entity_sync');
    $plugin->send($entity_values);
  }

}

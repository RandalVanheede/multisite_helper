<?php

namespace Drupal\multisite_helper\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\multisite_helper\MultisiteHelper;
use Drupal\multisite_helper\MultisiteHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MhSubsiteOverviewForm extends FormBase {

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly MultisiteHelperInterface $helper,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('multisite_helper'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId(): string {
    return 'multisite_helper_subsite_overview_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['table'] = [
      '#type' => 'table',
      '#header' => [
        'subsite' => $this->t('Subsite'),
        'url' => $this->t('Url'),
        'status' => $this->t('Enabled'),
        'accessible' => $this->t('Accessible'),
        'weight' => $this->t('Weight'),
        'operations' => $this->t('Operations'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'subsite-weight',
        ],
      ],
    ];

    $storage = \Drupal::entityTypeManager()->getStorage('mh_subsite');
    $subsite_ids = $storage
      ->getQuery()
      ->accessCheck(FALSE)
      ->sort('weight')
      ->execute();
    foreach ($subsite_ids as $subsite_id) {
      $entity = $storage->load($subsite_id);
      $form['table'][$entity->id()] = [
        'subsite' => ['#markup' => $entity->label() . ' (' . $entity->id() . ')'],
        'url' => ['#markup' => $entity->url()],
        'status' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Status'),
          '#title_display' => 'invisible',
          '#default_value' => $entity->status(),
        ],
        'accessible' => ['#markup' => MultisiteHelper::ping($entity->url()) ? '✔' : '✖'],
        'weight' => [
          '#type' => 'weight',
          '#delta' => count($subsite_ids),
          '#title' => $this->t('Weight for subsite'),
          '#title_display' => 'invisible',
          '#default_value' => $entity->get('weight') ?: 0,
          '#attributes' => [
            'class' => ['subsite-weight'],
          ],
        ],
        'operations' => [
          '#type' => 'operations',
          '#links' => $this->getOperations($entity),
          // Allow links to use modals.
          '#attached' => [
            'library' => ['core/drupal.dialog.ajax'],
          ],
        ],
        '#attributes' => [
          'class' => ['draggable'],
        ],
        '#weight' => $entity->get('weight') ?: 0,
      ];

      if ($this->helper->getCurrentSiteId() === $subsite_id) {
        foreach ($form['table'][$entity->id()] as $key => $item) {
          if (!isset($item['#markup'])) {
            continue;
          }

          $form['table'][$entity->id()][$key]['#markup'] =
            '<strong>' . $item['#markup'] . '</strong>';
        }
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save order'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $storage = \Drupal::entityTypeManager()->getStorage('mh_subsite');
    $values = $form_state->getValues();
    foreach ($values['table'] as $subsite_id => $subsite_item) {
      $subsite = $storage->load($subsite_id);
      foreach ($subsite_item as $field_name => $field_value) {
        $subsite->set($field_name, $field_value);
      }
      $subsite->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    $operations = $this->getDefaultOperations($entity);
    $operations += $this->moduleHandler->invokeAll('entity_operation', [$entity]);
    $this->moduleHandler->alter('entity_operation', $operations, $entity);
    uasort($operations, '\Drupal\Component\Utility\SortArray::sortByWeightElement');

    if (isset($operations['edit'])) {
      $url = &$operations['edit']['url'];
      $attributes = $url->getOption('attributes');
      $attributes['class'][] = 'use-ajax';
      $attributes['data-dialog-type'] = 'modal';
      $attributes['data-dialog-options'] = Json::encode(['width' => 600]);
      $url->setOption('attributes', $attributes);
    }

    return $operations;
  }

  /**
   * Gets this list's default operations.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the operations are for.
   *
   * @return array
   *   The array structure is identical to the return value of
   *   self::getOperations().
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = [];
    if ($entity->access('update') && $entity->hasLinkTemplate('edit-form')) {
      $edit_url = $this->ensureDestination($entity->toUrl('edit-form'));
      if (!empty($entity->label())) {
        $label = $this->t('Edit @entity_label', ['@entity_label' => $entity->label()]);
      }
      else {
        $label = $this->t('Edit @entity_bundle @entity_id', [
          '@entity_bundle' => $entity->bundle(),
          '@entity_id' => $entity->id(),
        ]);
      }
      $attributes = $edit_url->getOption('attributes') ?: [];
      $attributes += ['aria-label' => $label];
      $edit_url->setOption('attributes', $attributes);

      $operations['edit'] = [
        'title' => $this->t('Edit'),
        'weight' => 10,
        'url' => $edit_url,
      ];
    }
    if ($entity->access('delete') && $entity->hasLinkTemplate('delete-form')) {
      $delete_url = $this->ensureDestination($entity->toUrl('delete-form'));
      if (!empty($entity->label())) {
        $label = $this->t('Delete @entity_label', ['@entity_label' => $entity->label()]);
      }
      else {
        $label = $this->t('Delete @entity_bundle @entity_id', [
          '@entity_bundle' => $entity->bundle(),
          '@entity_id' => $entity->id(),
        ]);
      }
      $attributes = $delete_url->getOption('attributes') ?: [];
      $attributes += ['aria-label' => $label];
      $delete_url->setOption('attributes', $attributes);

      $operations['delete'] = [
        'title' => $this->t('Delete'),
        'weight' => 100,
        'attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'width' => 880,
          ]),
        ],
        'url' => $delete_url,
      ];
    }

    return $operations;
  }

  /**
   * Ensures that a destination is present on the given URL.
   *
   * @param \Drupal\Core\Url $url
   *   The URL object to which the destination should be added.
   *
   * @return \Drupal\Core\Url
   *   The updated URL object.
   */
  protected function ensureDestination(Url $url) {
    return $url->mergeOptions(['query' => $this->getRedirectDestination()->getAsArray()]);
  }

}

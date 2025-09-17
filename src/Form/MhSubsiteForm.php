<?php

declare(strict_types=1);

namespace Drupal\multisite_helper\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\multisite_helper\Entity\MhSubsite;

/**
 * Multisite Helper Subsite form.
 */
final class MhSubsiteForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->entity->status(),
    ];

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => [MhSubsite::class, 'load'],
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->get('description'),
      '#rows' => 2,
    ];

    $form['url'] = [
      '#type' => 'url',
      '#title' => $this->t('URL'),
      '#description' => $this->t('The base URL for the website, without trailing slash.'),
      '#default_value' => $this->entity->url(),
      '#required' => TRUE,
    ];

    $authorization_string = $this->entity->get('authorization') ?: '';
    if (empty($authorization) && str_contains($authorization_string, ':')) {
      [$user, $pass] = explode(':', $authorization_string, 2);
    }

    $form['authorization'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('HTTP Authorization'),
    ];

    $form['authorization']['user'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => $user ?? NULL,
    ];

    $form['authorization']['pass'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Password'),
      '#default_value' => $pass ?? NULL,
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = &$form_state->getValues();
    $values['url'] = rtrim($values['url'], '/');

    if (isset($values['user'], $values['pass'])) {
      $values['authorization'] = $values['user'] . ':' . $values['pass'];
    }
    else {
      $values['authorization'] = NULL;
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
      match($result) {
        \SAVED_NEW => $this->t('Created new example %label.', $message_args),
        \SAVED_UPDATED => $this->t('Updated example %label.', $message_args),
      }
    );
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}

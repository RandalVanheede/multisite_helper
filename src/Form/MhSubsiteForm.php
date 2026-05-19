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

    $form['aliases'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Domain aliases'),
      '#description' => $this->t('Additional hostnames that resolve to this subsite, one per line. Include the scheme (e.g. <code>https://www.example.com</code>).'),
      '#default_value' => implode("\n", $this->entity->aliases()),
      '#rows' => 3,
    ];

    $form['is_default'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Default subsite'),
      '#description' => $this->t('Use this subsite as the fallback when no hostname matches. Only one subsite should be marked as default.'),
      '#default_value' => $this->entity->isDefault(),
    ];

    $authorization_string = $this->entity->get('authorization') ?: NULL;
    if (!empty($authorization_string) && str_contains($authorization_string, ':')) {
      [$user, $pass] = explode(':', $authorization_string, 2);
    }

    $form['authorization'] = [
      '#type' => 'details',
      '#title' => $this->t('HTTP Authorization'),
      '#open' => !empty($authorization_string),
    ];

    $form['authorization']['user'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => $user ?? NULL,
    ];

    $form['authorization']['pass'] = [
      '#type' => 'password',
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

    if (!empty($values['user']) && !empty($values['pass'])) {
      $values['authorization'] = $values['user'] . ':' . $values['pass'];
    }
    else {
      $values['authorization'] = '';
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    // Convert aliases textarea (newline-separated) to an array.
    $aliases_raw = $form_state->getValue('aliases', '');
    $aliases = array_filter(array_map('trim', explode("\n", $aliases_raw)));
    $form_state->setValue('aliases', array_values($aliases));

    $result = parent::save($form, $form_state);
    $this->messenger()->addStatus($this->t('The subsite has been saved.'));
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}

<?php

namespace Drupal\easyjob_api\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provide the settings form for entity clone.
 */
class EasyjobApiSettingsForm extends ConfigFormBase implements ContainerInjectionInterface {

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['easyjob_api.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'easyjob_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('easyjob_api.settings');

    $form['base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Base url'),
      '#default_value' => $config->get('base_url'),
      '#description' => $this->t('The URL of Easyjob API with protocol (https).'),
      '#required' => TRUE,
    ];
    $form['timestamp'] = [
      '#type' => 'number',
      '#title' => $this->t('Last import time'),
      '#default_value' => $config->get('timestamp'),
      '#description' => $this->t('The time where the last product import has run.'),
      '#required' => TRUE,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('easyjob_api.settings');
    $form_state->cleanValues();

    $config->set('base_url', $form_state->getValue('base_url'));
    $config->set('timestamp', $form_state->getValue('timestamp'));

    $config->save();

    parent::submitForm($form, $form_state);
  }

}

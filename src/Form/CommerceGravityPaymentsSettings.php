<?php

namespace Drupal\commerce_gravity_payments\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a configuration form for Gravity Payments settings.
 */
class CommerceGravityPaymentsSettings extends ConfigFormBase {

  /**
   * Constructs a new CommerceGravityPaymentsSettings object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.

   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['commerce_gravity_payments.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_gravity_payments_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('commerce_gravity_payments.settings');
 
    $form['mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Mode'),
      '#default_value' => $config->get('mode'),
      '#required' => TRUE,
      '#options' => ['test' => $this->t('Test'), 'live' => $this->t('Live')],
    ];

    $form['oid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OID'),
      '#default_value' => $config->get('oid'),
      '#required' => TRUE,
    ];

    $form['auth_token'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Auth Token'),
      '#default_value' => $config->get('auth_token'),
      '#required' => TRUE,
      '#rows' => 1,
    ];
  
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('commerce_gravity_payments.settings');
    $config
      ->set('mode', $form_state->getValue('mode'))
      ->set('oid', $form_state->getValue('oid'))
      ->set('auth_token', $form_state->getValue('auth_token'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}

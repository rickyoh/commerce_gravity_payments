<?php

namespace Drupal\commerce_gravity_payments\PluginForm;

use Drupal\commerce_payment\Exception\DeclineException;
use Drupal\commerce_payment\PluginForm\PaymentMethodAddForm as BasePaymentMethodAddForm;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;

use Drupal\commerce_gravity_payments\EmergepayClient;

/**
 * Payment menthod form base.
 */
abstract class PaymentMethodAddFormBase extends BasePaymentMethodAddForm {

  /**
   * Gets the emergepay client.
   *
   * @return obj
   *   The emergepay client.
   */
  protected function getEmergepayClient() {
    $config =  \Drupal::config('commerce_gravity_payments.settings');
    
    $client_config = [
      'mode' => $config->get('mode'),
      'oid' => $config->get('oid'),
      'auth_token' => $config->get('auth_token'),
    ];
    
    return new EmergepayClient($client_config);
  }

  /**
   * Gets mode.
   *
   * @return string
   *   The mode.
   */
  protected function getMode() {
    $emergepay_config =  \Drupal::config('commerce_gravity_payments.settings');

    return $emergepay_config->get('mode');
  }


  protected function getLibrary() {
    if($this->getMode() == 'live'){
      return 'commerce_gravity_payments/emergepay_production';
    }

    return  'commerce_gravity_payments/emergepay_sandbox';
  }


}
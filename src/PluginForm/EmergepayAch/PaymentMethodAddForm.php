<?php

namespace Drupal\commerce_gravity_payments\PluginForm\EmergepayAch;

use Drupal\commerce_payment\PluginForm\PaymentMethodAddForm as BasePaymentMethodAddForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_gravity_payments\PluginForm\PaymentMethodAddFormBase;

class PaymentMethodAddForm extends PaymentMethodAddFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['payment_details'] = $this->buildAchForm($form['payment_details']);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildAchForm(array $element) {
    $plugin = $this->plugin;
    $configuration = $plugin->getConfiguration();

    $element['#attached']['library'][] = $this->getLibrary();
    $element['#attached']['library'][] = 'commerce_gravity_payments/form';

    $emergepay_client = $this->getEmergepayClient();

    $transaction_type = 'AchSale';
    $transaction_token = $emergepay_client->startTransaction($transaction_type);

    if($transaction_token == false){
      $element['error'] = [
        '#type' => 'markup',
        '#markup' => '<div id="paymentGatewayError" class="form-text">
        '.$this->t('We are sorry, but the payment gateway is inoperable at the moment. Please contact us for assistance.').'
        </div>',
      ];
      return $element;
    }

    // field styles
    $field_styles = new \stdClass();
    $field_error_styles = new \stdClass();
    if(isset($configuration['field_styles']) && !empty($configuration['field_styles'])){
      $field_styles = json_decode($configuration['field_styles']);
    }
    if(isset($configuration['field_error_styles']) && !empty($configuration['field_error_styles'])){
      $field_error_styles = json_decode($configuration['field_error_styles']);
    }

    $element['#attached']['drupalSettings']['commerceGravityPayments'] = [
      'transactionToken' => $transaction_token,
      'transactionType' => $transaction_type,
      'fieldStyles' => $field_styles,
      'fieldErrorStyles' => $field_error_styles,
    ];
    $element['#attributes']['class'][] = 'emergepay-form';

    // To display validation errors.
    $element['payment_errors'] = [
      '#type' => 'markup',
      '#markup' => '<div id="paymentErrors"></div>',
      '#weight' => -200,
    ];

    $element['account_number'] = [
      '#type' => 'item',
      '#title' => $this->t('Account number'),
      '#required' => TRUE,
      '#validated' => TRUE,
      '#markup' => '<div id="accountNumberContainer" class="form-text"></div>',
    ];

    $element['routing_number'] = [
      '#type' => 'item',
      '#title' => $this->t('Routing number'),
      '#required' => TRUE,
      '#validated' => TRUE,
      '#markup' => '<div id="routingNumberContainer"></div>',
    ];

    $element['account_holder_name'] = [
      '#type' => 'item',
      '#title' => $this->t('Account holder name'),
      '#required' => TRUE,
      '#validated' => TRUE,
      '#markup' => '<div id="accountHolderNameContainer"></div>',
    ];

    $element['transaction_token'] = [
      '#type' => 'hidden',
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['emergepay-transaction-token']
      ]
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function validateCreditCardForm(array &$element, FormStateInterface $form_state) {
    // The JS library performs its own validation.
    $values = $form_state->getValues();
  }

  /**
   * {@inheritdoc}
   */
  public function submitCreditCardForm(array $element, FormStateInterface $form_state) {
    $values = $form_state->getValues();
  }

}

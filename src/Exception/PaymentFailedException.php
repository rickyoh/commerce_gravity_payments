<?php

namespace Drupal\commerce_gravity_payments\Exception;

use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class PaymentFailedException extends PaymentGatewayException {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct($message = "", $code = 0, \Throwable $previous = NULL) {
    if (!$message) {
      $message = $this->t('Payment failed.  Please review your payment details and try again.');
    }
    parent::__construct($message, $code, $previous);
  }

}

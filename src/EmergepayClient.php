<?php

namespace Drupal\commerce_gravity_payments;

use Drupal\commerce_payment\Exception\PaymentGatewayException;

class EmergepayClient {

  protected $mode = 'test';
  protected $oid = '';
  protected $auth_token = '';
  protected $env_url = '';

  protected $test_env_url = 'https://api.emergepay-sandbox.chargeitpro.com/virtualterminal/v1';
  protected $prod_env_url = 'https://api.emergepay.chargeitpro.com/virtualterminal/v1';

  public function __construct(array $configuration) {

    $this->mode = $configuration['mode'];
    $this->oid = $configuration['oid'];
    $this->auth_token = $configuration['auth_token'];

    if($this->mode === 'live'){
      $this->env_url = $this->prod_env_url;
    }else{
      $this->env_url = $this->test_env_url;
    }

  }
  
  //Helper function used to generate a GUID/UUID
  //source: http://php.net/manual/en/function.com-create-guid.php#99425
  public function GUID(){
    if (function_exists('com_create_guid') === true)
    {
        return trim(com_create_guid(), '{}');
    }

    return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
  }

  /**
   * Sends a POST request using cURL.
   *
   * @param string $url The URL to which the request is sent.
   * @param array $body The request body.
   * @return mixed The decoded response data.
   * @throws PaymentGatewayException If an error occurs during the transaction.
   */
  protected function post(string $url, array $body): mixed {
    try {
      $request = curl_init($url);
      curl_setopt_array($request, [
          CURLOPT_HEADER => false,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $this->auth_token],
          CURLOPT_POST => true,
          CURLOPT_POSTFIELDS => json_encode($body),
      ]);

      $response = curl_exec($request);
      $httpCode = curl_getinfo($request, CURLINFO_HTTP_CODE);
      
      curl_close($request);

      if ($httpCode >= 200 && $httpCode < 300) {
        return json_decode($response);
      } else {
        throw new PaymentGatewayException('Request failed with HTTP code: ' . $httpCode);
      }

    } catch (\Exception $e) {
      throw new PaymentGatewayException('Unable to perform transaction.');
    }
  }

  /**
   * Sends a PUT request using cURL.
   *
   * @param string $url The URL to which the request is sent.
   * @param array $body The request body.
   * @return mixed The decoded response data.
   * @throws PaymentGatewayException If an error occurs during the transaction.
   */
  protected function put(string $url, array $body): mixed {
    $payload = json_encode($body);

    try{
      $request = curl_init($url);
      curl_setopt_array($request, [
        CURLOPT_HEADER => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload),
            'Authorization: Bearer ' . $this->auth_token
        ],
        CURLOPT_CUSTOMREQUEST => "PUT",
        CURLOPT_POSTFIELDS => $payload
      ]);

      $response = curl_exec($request);
      $httpCode = curl_getinfo($request, CURLINFO_HTTP_CODE);

      curl_close($request);

      if ($httpCode >= 200 && $httpCode < 300) {
        return json_decode($response);
      } else {
        throw new PaymentGatewayException('Request failed with HTTP code: ' . $httpCode);
      }

    } catch (\Exception $e) {
      throw new PaymentGatewayException('Unable to perform transaction.');
    }
  }

  /**
   * Start a transaction.
   *
   * @param string $transactionType The type of transaction.
   * @return string|null The transaction token if successful, or null on failure.
   */
  public function startTransaction(string $transactionType): ?string {
    $url =  $this->env_url . '/orgs/' . $this->oid . '/transactions/start';

    $body = array(
        'transactionData' => array(
          'transactionType' => $transactionType,
          'method' => 'hostedFields',
          'submissionType' => 'manual',
        )
      );

    // Make the POST request
    $data = $this->post($url, $body);

    // Check if the transactionToken is present in the response
    return $data->transactionToken ?? null;
  }


  /**
   * Process a transaction.
   *
   * @param string $transactionToken The token for the transaction.
   * @param array $transactionData The transaction data to be processed.
   * @return bool|object The response data if successful, or false on failure.
   */
  public function processTransaction(string $transactionToken, array $transactionData): bool|object {
    $url = $this->env_url . '/orgs/' . $this->oid . '/transactions/checkout/' . $transactionToken;

    // Create the request body
    $body = [
        'transactionData' => $transactionData
    ];

    // Make the PUT request
    $data = $this->put($url, $body);

    return $data;
  }


  /**
   * Process a tokenized payment.
   *
   * @param array $transactionData The transaction data for tokenized payment.
   * @return object The response data from the payment processing.
   */
  public function processTokenizedPayment(array $transactionData): object {
    $url = $this->env_url . '/orgs/' . $this->oid . '/transactions/tokenizedPayment';

    // Create the request body
    $body = [
        'transactionData' => $transactionData
    ];

    // Make the PUT request
    $data = $this->put($url, $body);

    return $data;
  }

  /**
   * Process a tokenized refund.
   *
   * @param array $transactionData The transaction data for tokenized refund.
   * @return bool|object The response data if successful, or false on failure.
   */
  public function processTokenizedRefund(array $transactionData): bool|object {
    $url = $this->env_url . '/orgs/' . $this->oid . '/transactions/tokenizedRefund';

    // Create the request body
    $body = [
        'transactionData' => $transactionData
    ];

    // Make the PUT request
    $data = $this->put($url, $body);

    return $data;
  }

  /**
   * Process a void transaction.
   *
   * @param array $transactionData The transaction data for voiding.
   * @return bool|object The response data if successful, or false on failure.
   */
  public function processVoid(array $transactionData): bool|object {
    $url = $this->env_url . '/orgs/' . $this->oid . '/transactions/void';

    // Create the request body
    $body = [
        'transactionData' => $transactionData
    ];

    // Make the PUT request
    $data = $this->put($url, $body);

    return $data;
  }

}
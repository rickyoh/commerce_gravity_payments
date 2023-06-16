<?php

namespace Drupal\commerce_gravity_payments;

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

    if($this->mode == 'live'){
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

  protected function post($url, $body){
    try{
      $request = curl_init($url);
      curl_setopt($request, CURLOPT_HEADER, false);
      curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($request, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Bearer ' . $this->auth_token));
      curl_setopt($request, CURLOPT_POST, true);
      curl_setopt($request, CURLOPT_POSTFIELDS, json_encode($body));

      $response = curl_exec($request);
      $httpcode = curl_getinfo($request, CURLINFO_HTTP_CODE);
      
      curl_close($request);
      return json_decode($response);
    } catch (\Exception $e) {
      throw new PaymentGatewayException('Unable to perform transaction.');
    }
  }


  protected function put($url, $body){
    $payload = json_encode($body);

    try{
      $request = curl_init($url);
      curl_setopt($request, CURLOPT_HEADER, false);
      curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($request, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($payload), 'Authorization: Bearer ' . $this->auth_token));
      curl_setopt($request, CURLOPT_CUSTOMREQUEST, "PUT");
      curl_setopt($request, CURLOPT_POSTFIELDS, $payload);

      $response = curl_exec($request);
      $httpcode = curl_getinfo($request, CURLINFO_HTTP_CODE);

      curl_close($request);
      return json_decode($response);
    } catch (\Exception $e) {
      throw new PaymentGatewayException('Unable to perform transaction.');
    }
  }

  public function startTransaction($transaction_type){
    $url =  $this->env_url . '/orgs/' . $this->oid . '/transactions/start';

    // @todo error handling

    //base_amount and external_tran_id are required in the fields array.
    $body = array(
        'transactionData' => array(
          'transactionType' => $transaction_type,
          'method' => 'hostedFields',
          'submissionType' => 'manual',
        )
      );

    $data = $this->post($url, $body);

    if(isset($data->transactionToken)){
      return $data->transactionToken;
    }
    return false;
  }

  public function processTransaction(string $transactionToken, array $transactionData){
    $url = $this->env_url . '/orgs/' . $this->oid . '/transactions/checkout/' . $transactionToken;

    $body = [
      'transactionData' => $transactionData
    ];

    $data = $this->put($url, $body);

    if(isset($data->status) && $data->status != 200){
      return false;
    }
 
    return $data;
  }


  public function processCreditAuth(string $transactionToken, array $transactionData){
    $url = $this->env_url . '/orgs/' . $this->oid . '/transactions/checkout/' . $transactionToken;

    $body = [
      'transactionData' => $transactionData
    ];

    $data = $this->put($url, $body);

    if(isset($data->status) && $data->status != 200){
      return false;
    }
 
    return $data;
  }


  public function processTokenizedPayment(array $transactionData){
    $url = $this->env_url . '/orgs/' . $this->oid . '/transactions/tokenizedPayment';

    $body = [
      'transactionData' => $transactionData
    ];

    $data = $this->put($url, $body);

    if(isset($data->status) && $data->status != 200){
      return false;
    }
 
    return $data;
  }


  public function processAchSale(string $transactionToken, array $transactionData){
    $url = $this->env_url . '/orgs/' . $this->oid . '/transactions/checkout/' . $transactionToken;

    $body = [
      'transactionData' => $transactionData
    ];

    $data = $this->put($url, $body);

    if(isset($data->status) && $data->status != 200){
      return false;
    }
 
    return $data;
  }


  public function processTokenizedRefund(array $transactionData){
    $url = $this->env_url . '/orgs/' . $this->oid . '/transactions/tokenizedRefund';

    //uniqueTransId, externalTransactionId, and amount are all required.
    $body = array(
      'transactionData' => $transactionData
    );

    $data = $this->put($url, $body);

    if(isset($data->status) && $data->status != 200){
      return false;
    }
    
    return $data;
  }

  public function processVoid(array $transactionData){
    $url = $this->env_url . '/orgs/' . $this->oid . '/transactions/void';

    //uniqueTransId, externalTransactionId
    $body = array(
      'transactionData' => $transactionData
    );

    $data = $this->put($url, $body);

    if(isset($data->status) && $data->status != 200){
      return false;
    }
    
    return $data;
  }

}
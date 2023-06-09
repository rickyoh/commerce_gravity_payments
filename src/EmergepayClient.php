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

  public function startCreditSale(){
    $oid = $this->oid;
    $authToken = $this->auth_token;
    $url =  $this->env_url . '/orgs/' . $oid . '/transactions/start';

    // @todo error handling

    //Set up the request body.
    //base_amount and external_tran_id are required in the fields array.
    $body = array(
        'transactionData' => array(
          'transactionType' => 'CreditSale',
          'method' => 'hostedFields',
          'submissionType' => 'manual',
        )
      );

    //Configure the request
    $request = curl_init($url);
    curl_setopt($request, CURLOPT_HEADER, false);
    curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($request, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Bearer ' . $authToken));
    curl_setopt($request, CURLOPT_POST, true);
    curl_setopt($request, CURLOPT_POSTFIELDS, json_encode($body));

    //Issue the request and get the response
    $response = curl_exec($request);
    curl_close($request);

    $data = json_decode($response);

    if(isset($data->transactionToken)){
      return $data->transactionToken;
    }
    return false;
  }

  public function processCreditSale(string $transactionToken, array $transactionData){
    // Ensure that you replace these with valid values before trying to issue a request
    $oid = $this->oid;
    $authToken = $this->auth_token;

    $url = $this->env_url . '/orgs/' . $oid . '/transactions/checkout/' . $transactionToken;

    // Configure the request body
    // externalTransactionId and amount are required.
    $body = [
      'transactionData' => $transactionData
    ];

    $payload = json_encode($body);

    //Configure the request
    $request = curl_init($url);
    curl_setopt($request, CURLOPT_HEADER, false);
    curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($request, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($payload), 'Authorization: Bearer ' . $authToken));
    curl_setopt($request, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($request, CURLOPT_POSTFIELDS, $payload);

    //Issue the request and get the result
    $response = curl_exec($request);
    $data = json_decode($response);
    curl_close($request);


    if(isset($data->status) && $data->status == 400){
      return false;
    }
 
    return $data;
    

  }



  public function processTokenizedRefund(array $transactionData){
    $oid = $this->oid;
    $authToken = $this->auth_token;


    $url = $this->env_url . '/orgs/' . $oid . '/transactions/tokenizedRefund';

    //Configure the request body.
    //uniqueTransId, externalTransactionId, and amount are all required.
    $body = array(
      'transactionData' => $transactionData
    );

    $payload = json_encode($body);

    //Configure the request
    $request = curl_init($url);
    curl_setopt($request, CURLOPT_HEADER, false);
    curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($request, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($payload), 'Authorization: Bearer ' . $authToken));
    curl_setopt($request, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($request, CURLOPT_POSTFIELDS, $payload);

    //Issue the request and get the result
    $response = curl_exec($request);
    curl_close($request);

    $data = json_decode($response);

    if(isset($data->status) && $data->status == 400){
      return false;
    }
    
    return $data;

  }

}
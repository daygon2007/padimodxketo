<?php

/*
 * MODX snippet to create a web service between Padiact and Marketo.
 *
 * Padiact posts lead data via webhooks to this snippet, which then uses
 * the Marketo REST API to create a new lead and add that lead to a list.
 * 
 * As a fallback, the lead data will be POSTED to a Google spreadsheet
 * using Brace.io's Data API
 *
 */

// if HTTP POST request is received
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  
  // if POST is coming from Padiact
  if (isset($_POST['from']) && $_POST['from'] == 'padiact') {
    
    // get request variables
    $email = $_POST['data']['email'];
    $firstName = $_POST['data']['field1'];
    $lastName = $_POST['data']['field2'];

    // some config variables
    $mktListId = '[ID of Marketo list]';
    $mktClientId = "[your Marketo REST API client ID]";
    $mktClientSecret = "[your Marketo REST API client secret]";
    $mktRestEndpoint = "[your Marketo REST API endpoint]";
    $mktIdentityEndpoint = "[your Marketo REST API identity endpoint]";
    $braceDataPostUrl = "[Brace Data POST key]";

    // authenticate Marketo REST API client
    $authResult = curlHelper(array(
      CURLOPT_URL => $mktIdentityEndpoint . '/oauth/token?grant_type=client_credentials&client_id=' . $mktClientId . '&client_secret=' . $mktClientSecret,
      CURLOPT_RETURNTRANSFER => true
    ));

    // if authentication is successful
    if ($authResult->success && isset($authResult->result->access_token)) {
      
      $modx->log(modx::LOG_LEVEL_DEBUG,"[Padiact Webhook] Web service authenticated");
      
      // get access token
      $accessToken = $authResult->result->access_token;
      
      // set up array of lead data
      $leadArray = array(
        "input" => array(
          array(
            "email" => $email,
            "FirstName" => $firstName,
            "LastName" => $lastName
          )
        )
      );
      
      // encode lead array as JSON
      $postJSON = json_encode($leadArray);
      
      // create/update lead in Marketo
      $createResult = curlHelper(array(
        CURLOPT_URL => $mktRestEndpoint . '/v1/leads.json',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $postJSON,
        CURLOPT_HTTPHEADER => array(
          'Content-Type: application/json',
          'Content-Length: ' . strlen($postJSON),
          'Authorization: Bearer ' . $accessToken
        )
      ));
      
      // if lead is created/updated successfully
      if ($createResult->success && $createResult->result->success) {
        
        $modx->log(modx::LOG_LEVEL_DEBUG,"[Padiact Webhook] Lead created/updated successfully");
        
        // get ID of lead
        $leadId = $createResult->result->result[0]->id;
        
        // set POST data
        $ids = array(
          "input" => array(
            array(
              "id" => $leadId
            )
          )
        );
        
        // encode POST data as JSON
        $idsJSON = json_encode($ids);
        
        // add lead to list
        $listResult = curlHelper(array(
          CURLOPT_URL => $mktRestEndpoint . '/v1/lists/' . $mktListId .'/leads.json',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => $idsJSON,
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($idsJSON),
            'Authorization: Bearer ' . $accessToken
          )
        ));
        
        // if lead added successfully
        if ($listResult->success && $listResult->result->success) {
          
          $modx->log(modx::LOG_LEVEL_DEBUG,"[Padiact Webhook] Lead added to list successfully");
          
        } else {
          
          // if lead not added to list
          postToGoogleDoc();
          $modx->log(modx::LOG_LEVEL_ERROR,"[Padiact Webhook] Error adding to list. Posting to Google Spreadsheet as fallback.");
          
        }
        
      } else {
        
        // if creating/updating lead fails
        postToGoogleDoc();
        $modx->log(modx::LOG_LEVEL_ERROR,"[Padiact Webhook] Error creating lead. Posting to Google Spreadsheet as fallback.");
        
      }
      
    } else {
      
      // if authentication fails
      postToGoogleDoc();
      $modx->log(modx::LOG_LEVEL_ERROR,"[Padiact Webhook] Error authorizing webhook. Posting to Google Spreadsheet as fallback.");
      
    }
    
  }
  
}

// function that POSTS the lead data to a Google spreadsheet as a fallback for fail cURLs.
function postToGoogleDoc() {
  
  // use global variables
  global $email, $firstName, $lastName, $braceDataPostUrl;
  
  // set up POST array
  $gdocLeadArray = array(
    "email" => $email,
    "FirstName" => $firstName,
    "LastName" => $lastName,
    "Date" => date('n/j/y')
  );
  
  // POST data to Brace Data API to add to Google spreadsheet
  $gdocResult = curlHelper(array(
    CURLOPT_URL => $braceDataPostUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => $gdocLeadArray
  ));
  
}

// helper function so I don't have to repeat the same cURL code over and over
function curlHelper($options) {
  
  // initiate cURL
  $curl = curl_init();
  
  // set cURL options
  curl_setopt_array($curl, $options);
  
  // execute cURL and get info
  $response = curl_exec($curl);
  $info = curl_getinfo($curl);
  
  // set 'success' variable to false if there are cURL errors
  $success = (!curl_errno($curl) ? true : false);
  
  // create object with cURL status, response, and info
  $return = (object) array(
    "success" => $success,
    "result" => json_decode($response),
    "info" => $info
  );
  
  // close cURL
  curl_close($curl);
  
  // return object
  return $return;
  
}

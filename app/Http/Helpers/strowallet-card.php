<?php
use GuzzleHttp\Client;
function stro_wallet_create_user($user,$formData,$public_key,$base_url){
    
    
    $client = new Client();

    $response               = $client->request('POST', $base_url.'create-user/', [
        'headers'           => [
            'accept'        => 'application/json',
        ],
        'form_params'       => [
            'public_key'    => $public_key,
            'houseNumber'   => $formData['house_number'],
            'firstName'     => $formData['first_name'],
            'lastName'      => $formData['last_name'],

            'idNumber'      => $formData['id_number'],
            'customerEmail' => $formData['customer_email'],
            'phoneNumber'   => $formData['phone'],
            'dateOfBirth'   => $formData['date_of_birth'],
            'idImage'       => $user->userImage,
            'userPhoto'     => $user->userImage,
            'line1'         => $formData['line1'],
            'state'         => $formData['state'],
            'zipCode'       => $formData['zip_code'],
            'city'          => $formData['city'],
            'country'       => 'NIGERIA',
            'idType'        => 'PASSPORT',
        ],
    ]);

    $result         = $response->getBody();
    $decodedResult  = json_decode($result, true);
    if( $decodedResult['success'] == true ){
        $data =[
            'status'        => true,
            'message'       => "Create Customer Successfully.",
            'data'          => $decodedResult['response'],
        ];
    }else{
        $data =[
            'status'        => false,
            'message'       => $decodedResult['message'] ?? 'Something is wrong! Contact With Admin',
            'data'          => null,
        ];
    }

    return $data;
      
}
// create virtual card for strowallet
function create_strowallet_virtual_card($user,$cardAmount,$customer,$public_key,$base_url){
    
    $curl = curl_init();

    curl_setopt_array($curl, [
    CURLOPT_URL => $base_url."create-card/",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => json_encode([
        'name_on_card' => $user->username,
        'card_type' => $customer->card_brand,
        'public_key' => $public_key,
        'amount' => $cardAmount,
        'customerEmail' => $customer->customerEmail
    ]),
    CURLOPT_HTTPHEADER => [
        "accept: application/json",
        "content-type: application/json"
    ],
    ]);
    $response = curl_exec($curl);
    
    curl_close($curl);
    $result  = json_decode($response, true);
   
    if( $result['success'] == true ){
        $data =[
            'status'        => true,
            'message'       => "Create Card Successfully.",
            'data'          => $result['response'],
        ];
    }else{
        $data =[
            'status'        => false,
            'message'       => $result['message'] ?? 'Something is wrong! Contact With Admin',
            'data'          => null,
        ];
    }

    return $data;
}
// card details
function card_details($card_id,$public_key,$base_url){
    $curl = curl_init();

    curl_setopt_array($curl, [
    CURLOPT_URL => $base_url . "fetch-card-detail/",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => json_encode([
        'public_key'    => $public_key,
        'card_id'       => $card_id
    ]),
    CURLOPT_HTTPHEADER => [
        "accept: application/json",
        "content-type: application/json"
    ],
    ]);

    $response = curl_exec($curl);
    
    curl_close($curl);

    $result  = json_decode($response, true);
   
    if( $result['success'] == true ){
        $data =[
            'status'        => true,
            'message'       => "Card Details Retrieved Successfully.",
            'data'          => $result['response'],
        ];
    }else{
        $data =[
            'status'        => false,
            'message'       => $result['message'] ?? 'Something is wrong! Contact With Admin',
            'data'          => null,
        ];
    }

    return $data;
}
<?php
use GuzzleHttp\Client;
function stro_wallet_create_user(){
    
    $client = new Client();

    $response               = $client->request('POST', 'https://strowallet.com/api/bitvcard/create-user/', [
        'headers'           => [
            'accept'        => 'application/json',
        ],
        'form_params'       => [
            'public_key'    => 'SPSO6TOAJF36KYEGMCHRCBFW4YOF8Y',
            'houseNumber'   => 'AD1254',
            'firstName'     => 'Dee',
            'lastName'      => 'Paul',
            'idNumber'      => '65893214752',
            'customerEmail' => 'user7@appdevs.net',
            'phoneNumber'   => '+234111553',
            'dateOfBirth'   => '05/11/1998',
            'idImage'       => 'https://akash.appdevs.net/Adoctor/public/frontend/images/site-section/seeder/blog1.webp',
            'userPhoto'     => 'https://akash.appdevs.net/Adoctor/public/frontend/images/site-section/seeder/blog1.webp',
            'line1'         => 'Dhalia, Mymensingh',
            'state'         => 'MYMENSINGH',
            'zipCode'       => '1236',
            'city'          => 'MYMENSINGH',
            'country'       => 'NIGERIA',
            'idType'        => 'PASSPORT',
        ],
    ]);

    $result         = $response->getBody();
    $decodedResult  = json_decode($result, true);

    return $decodedResult;
      
}
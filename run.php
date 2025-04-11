<?php

include '/var/www/splynx/addons/splynx-php-api/SplynxApi.php'; // Splynx API

$log_file = "migrate_tax.log"; //log file

//sending  email by API
$api_url = "https://porsche.my-services.com.ua/";
$api = new SplynxAPI($api_url);
$api->setVersion(SplynxApi::API_VERSION_2);
$api->login([
    'auth_type'=> SplynxApi::AUTH_TYPE_API_KEY,
    'key' => "c169131b34d7582b3fd124fae6e75ad5", // please set your key
    'secret' => "e95fc1e7234941c5704553095afbbfa6", // please set your secret
]);

// TAX ID with 15.5 we have to skip it
$tax_id = 15;


// get all InetTariffs by API
$url_inet_tar = "admin/tariffs/internet";
$search_arr = [
    'main_attributes' => [
        'available_for_services' => '1',
        'tax_id' => ['!=', $tax_id],                 
    ],
];

$api->api_call_get($url_inet_tar.'?'.http_build_query($search_arr));
$all_inet_tar = $api->response;

foreach ($all_inet_tar as &$inet_tar) {
    $inet_tar['tax_id'] = $tax_id;
    $old_tariff = $inet_tar['id'];
    unset($inet_tar['id']);
    $inet_tar['title'] = $inet_tar['title'].' new 15.5%';
    $result=$api->api_call_post($url_inet_tar, $inet_tar);
    if ($result) {
        $new_tariff = $api->response;
        file_put_contents($log_file,  "New Internet tariff created: {$new_tariff['id']} \n", FILE_APPEND);
    } else {
        file_put_contents($log_file,  "New Internet tariff creating ERROR: {$api->response} \n", FILE_APPEND);
    }


    //working with services
    $batch_size = 100; // You can adjust this value
    $offset = 0;

    while (true) {
        // get all InetServices by API
        $url_inet_ser = "admin/customers/customer/0/internet-services";

        $search_arr = [
            'main_attributes' => [
                'tariff_id' => $old_tariff,
                'status' => 'active',
            ],
            'limit' => $batch_size,
            'offset' => $offset,
        ];

        $api->api_call_get($url_inet_ser . '?' . http_build_query($search_arr));
        $current_batch = $api->response;

        if (empty($current_batch)) {
            break; // Exit the loop if no more tariffs are found
        }

        foreach ($current_batch as &$inet_serv) {
            // get all InetServices by API
            $url_change_tarr = "admin/tariffs/change-tariff/";
            $data_change = [
                'newTariffId' => $new_tariff['id'],
                'targetDate' => "2025-05-01",
                'description' => $inet_serv['description'],
                'newServicePrice' => $inet_serv['unit_price'],
            ];

            $result = $api->api_call_put($url_change_tarr.$inet_serv['id'].'?type=internet', '',$data_change);
            if ($result) {
                file_put_contents($log_file,  "New internet service created: ID {$api->response}, old servise ID {$inet_serv['id']} \n", FILE_APPEND);
            } else {
                file_put_contents($log_file,  "New Internet service creating ERROR: {$api->response} \n", FILE_APPEND);
            }
        }

        $offset += $batch_size;

        // Optional: Add a delay to avoid overwhelming the API
        // sleep(1);
    }

    //break;
};

print_r('Thank you Nik!');
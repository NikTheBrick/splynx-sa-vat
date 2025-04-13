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

//*********************************************
// Change Services - The main function !!!
//*********************************************
function change_services($all_tariffs, $type) {
    foreach ($all_tariffs as &$tar) {
        $tar['tax_id'] = $tax_id;
        $old_tariff = $tar['id'];
        unset($tar['id']);
        $tar['title'] = $tar['title'].' new 15.5%';
        $result=$api->api_call_post('admin/tariffs/'.$type, $tar);
        if ($result) {
            $new_tariff = $api->response;
            file_put_contents($log_file,  "Old tariff ID: {$old_tariff} -> New {$type} tariff created: {$new_tariff['id']} \n", FILE_APPEND);
        } else {
            file_put_contents($log_file,  "Old tariff ID: {$old_tariff} -> New {$type} tariff creating ERROR: ". print_r($api->response, true) ."\n", FILE_APPEND);
        }
        file_put_contents($log_file,  "-------------------------------------------------------------------------------------------------------\n", FILE_APPEND);

        //working with services
        $batch_size = 100; // You can adjust this value
        $offset = 0;

        while (true) {
            // get all InetServices by API
            $url_ser = 'admin/customers/customer/0/'.$type.'-services';

            $search_arr = [
                'main_attributes' => [
                    'tariff_id' => $old_tariff,
                    'bundle_service_id' => 0,
                    'status' => 'active',
                ],
                'limit' => $batch_size,
                'offset' => $offset,
            ];

            $api->api_call_get($url_ser . '?' . http_build_query($search_arr));
            $current_batch = $api->response;

            if (empty($current_batch)) {
                break; // Exit the loop if no more tariffs are found
            }

            foreach ($current_batch as &$serv) {
                // get all InetServices by API
                $url_change_tarr = "admin/tariffs/change-tariff/";
                $data_change = [
                    'newTariffId' => $new_tariff['id'],
                    'targetDate' => "2025-05-01",
                    'description' => $serv['description'],
                    'newServicePrice' => $serv['unit_price'],
                ];

                $result = $api->api_call_put($url_change_tarr.$serv['id'].'?type=internet', '',$data_change);
                if ($result) {
                    file_put_contents($log_file,  "Old servise ID: {$serv['id']} -> New internet service created: ID {$api->response} \n", FILE_APPEND);
                } else {
                    file_put_contents($log_file,  "Old servise ID: {$serv['id']} -> New Internet service creating ERROR: " . print_r($api->response, true) . "\n", FILE_APPEND);
                }
            }

            $offset += $batch_size;

            // Optional: Add a delay to avoid overwhelming the API
            // sleep(1);
        }

        file_put_contents($log_file,  "-------------------------------------------------------------------------------------------------------\n", FILE_APPEND);
        file_put_contents($log_file,  "\n\n", FILE_APPEND);
        //break;
    };
};

//***************
// Update Tariffs
//***************

// InetTariffs 
$url_inet_tar = "admin/tariffs/internet";
$search_arr = [
    'main_attributes' => [
        'available_for_services' => '1',
        'tax_id' => ['!=', $tax_id],                 
    ],
];

$api->api_call_get($url_inet_tar.'?'.http_build_query($search_arr));
$all_inet_tar = $api->response;
file_put_contents($log_file,  "-------------------------------------------------------------------------------------------------------\n", FILE_APPEND);
file_put_contents($log_file,  "                                        Internet Tariffs                                               \n", FILE_APPEND);
file_put_contents($log_file,  "-------------------------------------------------------------------------------------------------------\n", FILE_APPEND);
change_services($all_inet_tar, "internet");


// VoiceTariffs 
$url_voice_tar = "admin/tariffs/voice";
$search_arr = [
    'main_attributes' => [
        'available_for_services' => '1',
        'tax_id' => ['!=', $tax_id],                 
    ],
];

$api->api_call_get($url_voice_tar.'?'.http_build_query($search_arr));
$all_voice_tar = $api->response;
file_put_contents($log_file,  "-------------------------------------------------------------------------------------------------------\n", FILE_APPEND);
file_put_contents($log_file,  "                                           Voice Tariffs                                               \n", FILE_APPEND);
file_put_contents($log_file,  "-------------------------------------------------------------------------------------------------------\n", FILE_APPEND);
change_services($all_voice_tar, "voice");


// RecurringTariffs 
$url_recurring_tar = "admin/tariffs/recurring";
$search_arr = [
    'main_attributes' => [
        'available_for_services' => '1',
        'tax_id' => ['!=', $tax_id],                 
    ],
];

$api->api_call_get($url_recurring_tar.'?'.http_build_query($search_arr));
$all_recurring_tar = $api->response;
file_put_contents($log_file,  "-------------------------------------------------------------------------------------------------------\n", FILE_APPEND);
file_put_contents($log_file,  "                                        Recurring Tariffs                                              \n", FILE_APPEND);
file_put_contents($log_file,  "-------------------------------------------------------------------------------------------------------\n", FILE_APPEND);
change_services($all_recurring_tar, "recurring");



print_r('Thank you Nik!');
<?php

include '/var/www/splynx/addons/splynx-php-api/SplynxApi.php'; // Splynx API

echo "Please make a choice:\n";
echo "1 - VAT will be on ISP (tariffs & services price won't change) \n";
echo "2 - VAT will be on customers (tariffs & services price will be changed)\n";
echo "3 - VAT will be on ISP (*** VAT exclude!!! ***)(tariffs & services price will be changed) \n";
echo "Enter your choice (1 or 2): ";

$user_choice = trim(fgets(STDIN));
if ($user_choice === '2' or $user_choice === '3') {
    echo "Please select rounding:\n";
    echo "1 - round up to 2 decimals (always to biggest). Example: 123.2345 -> 123.24 (Default rounding!!!) \n";
    echo "2 - round up to integer (always to biggest). Example: 123.2345 -> 124 \n";
    echo "3 - round up to tenth integer (always to biggest). Example: 123.2345 -> 130 \n";
    echo "Enter your choice (1 or 2 or 3): ";
    $rounding_choice = trim(fgets(STDIN));
    if ($rounding_choice === '1') {
        $rounding_choice = 2;
    } elseif ($rounding_choice === '2') {
        $rounding_choice = 0;
    } elseif ($rounding_choice === '3') {
        $rounding_choice = -1;
    } else {
        echo "Invalid choice. Please start from beginning and run script!\n";
        exit(1); // Exit with an error code
    }
} elseif  ($user_choice === '1') {
    echo "Invalid choice. Please enter 1 or 2.\n";
    exit(1); // Exit with an error code
}

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
$target_date = "2025-05-01"; // Make target date a variable

//*********************************************
// rounding 
// $precision = 2 => $rounding_choice = 1
// $precision = 0 => $rounding_choice = 2
// $precision = -1 => $rounding_choice = 3
//*********************************************
function customRoundUp($number, $precision = 2) {
    $factor = pow(10, $precision);
    return ceil($number * $factor) / $factor;
}

//*********************************************
// Change Services - The main function !!!
//*********************************************
function change_services(SplynxAPI $api, $all_tariffs, $type, $tax_id, $log_file, $target_date, $user_choice, $rounding_choice) {
    foreach ($all_tariffs as &$tar) {
        $old_tariff = $tar['id'];
        $tar['tax_id'] = $tax_id;
        if($user_choice === '2'){
            $tar['price'] = ($tar['price'] / 1.15) * 1.155;
            $tar['price'] = customRoundUp($tar['price'],$rounding_choice);
        };
        if($user_choice === '3'){
            $tar['price'] = ($tar['price'] * 1.15) / 1.155;
            $tar['price'] = customRoundUp($tar['price'],$rounding_choice);
        };
        unset($tar['id']);
        $tar['title'] = $tar['title'].' new 15.5%';
        $result = $api->api_call_post('admin/tariffs/'.$type, $tar);
        if ($result) {
            $new_tariff = $api->response;
            file_put_contents($log_file,  "Old tariff ID: {$old_tariff} -> New {$type} tariff created: {$new_tariff['id']} \n", FILE_APPEND);
        } else {
            file_put_contents($log_file,  "Old tariff ID: {$old_tariff} -> New {$type} tariff creating ERROR: ". print_r($api->response, true) ."\n", FILE_APPEND);
        }
        file_put_contents($log_file,  "-------------------------------------------------------------------------------------------------------\n", FILE_APPEND);

        if ($type != 'one-time'){
            $all_services = [];
            $batch_size = 100;
            $offset = 0;
            $url_ser = 'admin/customers/customer/0/'.$type.'-services';

            while (true) {
                $search_arr = [
                    'main_attributes' => [
                        'tariff_id' => $old_tariff,
                        'bundle_service_id' => 0,
                        'status' => ['IN', ['active', 'stopped']],
                    ],
                    'limit' => $batch_size,
                    'offset' => $offset,
                ];

                $api->api_call_get($url_ser . '?' . http_build_query($search_arr));
                $current_batch = $api->response;

                if (empty($current_batch)) {
                    break;
                }

                $all_services = array_merge($all_services, $current_batch);
                $offset += $batch_size;
            }

                foreach ($all_services as &$serv) {
                    // get all InetServices by API
                    $url_change_tarr = "admin/tariffs/change-tariff/";
                    $url_update_service = "admin/customers/customer/";
                    if($user_choice === '2'){
                        $serv['unit_price'] = ($serv['unit_price'] / 1.15) * 1.155;
                        $serv['unit_price'] = customRoundUp($serv['unit_price'],$rounding_choice);
                    };
                    if($user_choice === '3'){
                        $serv['unit_price'] = ($serv['unit_price'] * 1.15) / 1.155;
                        $serv['unit_price'] = customRoundUp($serv['unit_price'],$rounding_choice);
                    };
                    if ($serv['status'] == 'active') {
                        $data_change = [
                            'newTariffId' => $new_tariff['id'],
                            'targetDate' => $target_date,
                            'description' => $serv['description'],
                            'newServicePrice' => $serv['unit_price'],
                        ];
                        $result = $api->api_call_put($url_change_tarr.$serv['id'].'?type='.$type, '',$data_change); // Use $type here
                        if ($result) {
                            file_put_contents($log_file,  "Old service ID: {$serv['id']} -> New {$type} service updated to tariff ID: {$api->response} \n", FILE_APPEND);
                        } else {
                            file_put_contents($log_file,  "Old service ID: {$serv['id']} -> New {$type} service updating ERROR: " . print_r($api->response, true) . "\n", FILE_APPEND);
                        }
                    } elseif ($serv['status'] == 'stopped') {
                        $data_array = [
                            'tariff_id' => $new_tariff['id'],
                            'unit_price' => $serv['unit_price'],
                        ];
                        $result = $api->api_call_put($url_update_service.$serv['customer_id'].'/'.$type.'-services--'.$serv['id'],'', $data_array);
                        if ($result) {
                            file_put_contents($log_file,  "Stopped service ID: {$serv['id']} has been updated -> Ok \n", FILE_APPEND);
                        } else {
                            file_put_contents($log_file,  "Stopped service ID: {$serv['id']} updating ERROR: " . print_r($api->response, true) . "\n", FILE_APPEND);
                        }
                    }
                
            }

        }

        file_put_contents($log_file,  "-------------------------------------------------------------------------------------------------------\n", FILE_APPEND);
        file_put_contents($log_file,  "\n\n", FILE_APPEND);
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
change_services($api, $all_inet_tar, "internet", $tax_id, $log_file, $target_date, $user_choice, $rounding_choice);


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
change_services($api, $all_voice_tar, "voice", $tax_id, $log_file, $target_date, $user_choice, $rounding_choice);


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
change_services($api, $all_recurring_tar, "recurring", $tax_id, $log_file, $target_date, $user_choice, $rounding_choice);



// One-timeTariffs
$url_one_time_tar = "admin/tariffs/one-time";
$search_arr = [
    'main_attributes' => [
        'enabled' => '1',
        'tax_id' => ['!=', $tax_id],
    ],
];

$api->api_call_get($url_one_time_tar.'?'.http_build_query($search_arr));
$all_one_time_tar = $api->response;
file_put_contents($log_file,  "-------------------------------------------------------------------------------------------------------\n", FILE_APPEND);
file_put_contents($log_file,  "                                        One-time Tariffs                                              \n", FILE_APPEND);
file_put_contents($log_file,  "-------------------------------------------------------------------------------------------------------\n", FILE_APPEND);
change_services($api, $all_one_time_tar, "one-time", $tax_id, $log_file, $target_date, $user_choice, $rounding_choice);




print_r('Thank you Nik!');

?>
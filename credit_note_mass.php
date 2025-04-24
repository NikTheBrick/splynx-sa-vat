<?php

include '/var/www/splynx/addons/splynx-php-api/SplynxApi.php'; // Splynx API



$log_file = "credit_notes.log"; //log file

//sending  email by API
$api_url = "https://porsche.my-services.com.ua/";
$api = new SplynxAPI($api_url);
$api->setVersion(SplynxApi::API_VERSION_2);
$api->login([
    'auth_type'=> SplynxApi::AUTH_TYPE_API_KEY,
    'key' => "c169131b34d7582b3fd124fae6e75ad5", // please set your key
    'secret' => "e95fc1e7234941c5704553095afbbfa6", // please set your secret
]);


print_r('Script started, please wait...'); echo ''. PHP_EOL;


// get all invoices by chunks
$all_invoice = [];
$batch_size = 100;
$offset = 0;

$url_invoice = "admin/finance/invoices";

while (true) {
    $search_arr = [
        'main_attributes' => [
            'real_create_datetime' => ['>', '2025-04-09'],
        ],
        'limit' => $batch_size,
        'offset' => $offset,
    ];

    $api->api_call_get($url_invoice . '?' . http_build_query($search_arr));
    $current_batch = $api->response;

    if (empty($current_batch)) {
        break;
    }

    $all_invoice = array_merge($all_invoice, $current_batch);
    $offset += $batch_size;
}


//print_r($all_invoice);


foreach ($all_invoice as &$invoice) {
    // array Items for Credit Note
    file_put_contents($log_file,  "Invoice id: {$invoice['id']} -> ", FILE_APPEND);
    $wrong_tax = 0;
    foreach ($invoice['items'] as &$item) {        
        if ($item['tax'] == '15.5000'){
            $wrong_tax = 1;
        }
    }

    if ($wrong_tax == 0) {
        file_put_contents($log_file,  " was skipped because all items with VAT not equal 15.5% -> Ok \n", FILE_APPEND);
        continue;
    }


    foreach ($invoice['items'] as $key => $item) {
        $items[] = [
            'description' => $item['description'],
            'quantity' => $item['quantity'],
            'unit' => $item['unit'],
            'price' => $item['price'],
            'tax' => $item['tax'],
            'invoice_item_id' => $item['id'],
        ];

        $invoice['items'][$key]['period_from'] = '0000-00-00';
        $invoice['items'][$key]['period_to'] = '0000-00-00';
    }


    // update invoice to clean-up periods
    $result=$api->api_call_put($url_invoice, $invoice['id'], $invoice);
    if ($result) {
        file_put_contents($log_file,  " invoice was updated -> Ok ->", FILE_APPEND);
    } else {
        file_put_contents($log_file,  " invoice wasn't updated -> ERROR \n", FILE_APPEND);
        continue;
    }

       
    // create Credit Note
    $url_cn = "admin/finance/credit-notes";
    $data = [
        'invoicesId' => $invoice['id'],
        'customer_id' => $invoice['customer_id'],
        'resetPeriodForService' => '1',
        'status' => 'not_refunded',
        'items' => $items,
    ];

    $result=$api->api_call_post($url_cn, $data);
    if ($result) {
        file_put_contents($log_file,  " Credit Note created {$api->response['id']} \n", FILE_APPEND);
    } else {
        file_put_contents($log_file,  "ERROR" . print_r($api->response, true) . "\n", FILE_APPEND);
    }

}



echo ''. PHP_EOL; 
print_r('Thank you Nik!');
echo ''. PHP_EOL;
print_r('==============');
?>
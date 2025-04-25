<?php

include '/var/www/splynx/addons/splynx-php-api/SplynxApi.php'; // Splynx API



$log_file = "credit_notes.log"; //log file

$api_url = ""; // please set your domain
$api = new SplynxAPI($api_url);
$api->setVersion(SplynxApi::API_VERSION_2);
$api->login([
    'auth_type'=> SplynxApi::AUTH_TYPE_API_KEY,
    'key' => "", // please set your key
    'secret' => "", // please set your secret
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


foreach ($all_invoice as &$invoice) {
    $items_for_cn = [];

    // array Items for Credit Note
    file_put_contents($log_file,  "Invoice id: {$invoice['id']} -> ", FILE_APPEND);
    $wrong_tax = 0;
    foreach ($invoice['items'] as $one_item) {        
        if ($one_item['tax'] == '15.5000'){
            $wrong_tax = 1;
        }
    }

    if ($wrong_tax == 0) {
        file_put_contents($log_file,  " was skipped because all items with VAT not equal 15.5% -> Ok \n", FILE_APPEND);
        continue;
    }


    foreach ($invoice['items'] as $key => $item) {
        $items_for_cn[] = [
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
        'items' => $items_for_cn,
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

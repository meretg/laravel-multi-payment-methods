<?php

namespace Vector\LaravelMultiPaymentMethods\Methods;

use JsonException;
use Vector\LaravelMultiPaymentMethods\Interfaces\PaymentGatewayInterface;

/**
 * Upayments Payment Method.
 *
 * @author Vector <mo.khaled.yousef@gmail.com>
 */
class Upayments extends BaseMethod implements PaymentGatewayInterface
{

    /**
     * Set Method Driver And Base Url
     *
     * @return void
     * @throws JsonException
     */
    public function __construct()
    {
        //Set Method Driver
        $this->driver = 'upayments';
        //Set Method Live Base Url
        $this->live_base_url = $this->base_url = "https://uapi.upayments.com/api/v1";
        //Set Method test Base Url
        $this->test_base_url = "https://sandboxapi.upayments.com/api/v1";
        //Set Config Required Keys
        $this->requiredConfigKeys = ['merchant_id', 'username', 'password', 'api_key'];
        //Calling Parent Constructor
        parent::__construct();
        //Init Http Client With Additional Configs
        $this->client->withHeaders(["Authorization" =>  'Bearer ' .$this->config->api_key]);
    }


    /**
     * Send Payment Request
     *
     * @param array $details
     * @return array
     * @throws JsonException
     */
    public function pay(array $details): array
    {
        $subDomain =  "charge";
        $response = $this->client->post($subDomain, $this->buildPayRequest($details));
        $jsonResponse = $response->object();
        $success = $response->status() === 200 && $jsonResponse->status === "success";

        $message = $success ? ($jsonResponse->message ?? null) : ($jsonResponse->message ?? null);
        $payment_url = $jsonResponse->data->link ?? null;
        return $this->response($response->status(), $success, $message, $payment_url, (array)$jsonResponse);
    }

    /**
     * Build Payment Request
     *
     * @param array $details
     * @return array
     * @throws JsonException
     */
    public function buildPayRequest(array $details): array
    {

        $transactionDetails = $details['transaction'] ?? [];
        $customerDetails = $details['customer'] ?? [];
        $productDetails = $details['items'] ?? [];
            return [
                "customer"=>[
                    "name"=>(string) $customerDetails['name'],
                    "email"=> (string) $customerDetails['email'],
                    "mobile"=> (string) $customerDetails['phone']
                ],
                'language' => (string)'ar',
                "reference"=> [
                    "id"=> (string)$transactionDetails['id']
                ],
                "paymentGateway"=>["src"=> $transactionDetails['method'] ?? "cc"],

                "order"=> [
                    "id"=> (string)$transactionDetails['id'] ? $transactionDetails['id'] . '_' . time() : null,
                    "currency"=>$transactionDetails['currency_code'] ?? null,
                    "amount"=> $transactionDetails['price'] ?? null
                ],
                'returnUrl' => $this->config->success_url,
                'cancelUrl' => $this->config->error_url,
                "notificationUrl"=>$this->config->notify_url,
                "customerExtraData"=> "User define data"
            ];

//        return [
////            "merchant_id" => $this->config->merchant_id,
////            "username" => $this->config->username,
////            "password" => stripslashes($this->config->password),
////            "api_key" => $this->sandbox ? $this->config->api_key : bcrypt($this->config->api_key),
//            "success_url" => $this->config->success_url,
//            "error_url" => $this->config->error_url,
//            "test_mode" => $this->sandbox ? 1 : 0,
//            "order_id" => $transactionDetails['id'] ? $transactionDetails['id'] . '_' . time() : null,
//            "total_price" => $transactionDetails['price'] ?? null,
//            "CurrencyCode" => $transactionDetails['currency_code'] ?? null,
//            "whitelabled" => $transactionDetails['whitelabled'] ?? null,
//            "reference" => $transactionDetails['id'],
//            "payment_gateway" => $transactionDetails['method'] ?? "cc",
//            "CstFName" => $customerDetails['name'] ?? null,
//            "CstEmail" => $customerDetails['email'] ?? null,
//            "CstMobile" => $customerDetails['phone'] ?? null,
//            "ProductName" => json_encode(collect($productDetails)->pluck('name')->toArray(), JSON_THROW_ON_ERROR),
//            "ProductPrice" => json_encode(collect($productDetails)->pluck('price')->toArray(), JSON_THROW_ON_ERROR),
//            "ProductQty" => json_encode(collect($productDetails)->pluck('quantity')->toArray(), JSON_THROW_ON_ERROR),
//        ];
    }

    /**
     * get Payment Details
     *
     * @param string $orderID
     * @return array
     */
    public function getPaymentDetails(string $orderID, string $accessToken = null): array
    {
        $response = $this->client->asForm()->baseUrl("https://statusapi.upayments.com")->post("api/v1/get-payment-status/".request('track_id'), ['merchant_id' => $this->config->merchant_id, 'order_id' => $orderID]);
        $jsonResponse = $response->object();
//        print_r($jsonResponse);die;
        $success = $response->status() === 200 && $jsonResponse->status === "success";
        $message = $success ? ($jsonResponse->status ?? null) : ($jsonResponse->error_msg ?? null);
        $payment_url = $jsonResponse->paymentURL ?? null;
        return $this->response($response->status(), $success, $message, $payment_url, (array)$jsonResponse);
    }

    /**
     * Validate Response CallBack
     *
     * @param array $request
     * @return bool
     */
    public function validateResponseCallBack(array $request): bool
    {
        return true;
    }

    /**
     * Response CallBack
     *
     * @param array $responseDetails
     * @return array
     */
    public function responseCallBack(array $responseDetails): array
    {
        $isValid = $this->validateResponseCallBack($responseDetails);
        if (!$isValid)
            return $this->response(400, false, "Payment Failed", null, $responseDetails);
        $objectResponse = (object)$responseDetails;
        $success = $objectResponse->result === "CAPTURED";
        $status = $success ? 200 : 400;
        $message = $objectResponse->result;
        return $this->response($status, $success, $message, null, $responseDetails);
    }

}

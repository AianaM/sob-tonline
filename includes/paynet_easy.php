<?php


class PaynetEasy
{
    private $endpointId = 000;
    private $merchantControl = '***';
    private $login = 'login';

    /**
     * Executes request
     *
     * @param string $url Url for payment method
     * @param array $requestFields Request data fields
     *
     * @return      array                           Host response fields
     *
     * @throws      RuntimeException                Error while executing request
     */
    private function sendRequest($url, array $requestFields)
    {
        $curl = curl_init($url);

        curl_setopt_array($curl, array
        (
            CURLOPT_HEADER => 0,
            CURLOPT_USERAGENT => 'PaynetEasy-Client/1.0',
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => 1
        ));

        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($requestFields));

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            $error_message = 'Error occurred: ' . curl_error($curl);
            $error_code = curl_errno($curl);
        } elseif (curl_getinfo($curl, CURLINFO_HTTP_CODE) != 200) {
            $error_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error_message = "Error occurred. HTTP code: '{$error_code}'";
        }

        curl_close($curl);

        if (!empty($error_message)) {
            throw new RuntimeException($error_message, $error_code);
        }

        if (empty($response)) {
            throw new RuntimeException('Host response is empty');
        }

        $responseFields = array();

        parse_str($response, $responseFields);

        return $responseFields;
    }

    private function signString($s, $merchantControl)
    {
        return sha1($s . $merchantControl);
    }

    /**
     * Signs payment (sale/auth/transfer) request
     *
     * @param array $requestFields request array
     * @param string $endpointOrGroupId endpoint or endpoint group ID
     * @param string $merchantControl merchant control key
     */
    private function signPaymentRequest($requestFields, $endpointOrGroupId, $merchantControl)
    {
        $base = '';
        $base .= $endpointOrGroupId;
        $base .= $requestFields['client_orderid'];
        $base .= $requestFields['amount'] * 100;
        $base .= $requestFields['email'];

        return $this->signString($base, $merchantControl);
    }

    /**
     * Signs status request
     *
     * @param array $requestFields request array
     * @param string $login merchant login
     * @param string $merchantControl merchant control key
     */
    private function signStatusRequest($requestFields, $login, $merchantControl)
    {
        $base = '';
        $base .= $login;
        $base .= $requestFields['client_orderid'];
        $base .= $requestFields['orderid'];

        return $this->signString($base, $merchantControl);
    }


    private function signAccountVerificationRequest($requestFields, $endpointOrGroupId, $merchantControl)
    {
        $base = '';
        $base .= $endpointOrGroupId;
        $base .= $requestFields['client_orderid'];
        $base .= $requestFields['email'];
        return $this->signString($base, $merchantControl);
    }

    public function sale_form($data, $redirect_url = null)
    {
        // just in case
        $amount = number_format($data['amount'], 2, '.', '');

        $requestFields = array(
            'client_orderid' => $data['client_orderid'],
            'order_desc' => !empty($data['order_desc']) ? $data['order_desc'] : 'tickets',
            'first_name' => $data['firstname'],
            'last_name' => $data['lastname'],
            'address1' => 'Lenina',
            'city' => 'Yakutsk',
            'zip_code' => '677000',
            'country' => 'RU',
            'phone' => $data['phone'],
            'amount' => $amount,
            'email' => $data['email'],
            'currency' => 'RUB',
            'ipaddress' => '65.153.12.232',
            'redirect_url' => $redirect_url ?: 'https://sakha-opera.ru',
            'preferred_language' => 'ru'
        );

        $requestFields['control'] = $this->signPaymentRequest($requestFields, $this->endpointId, $this->merchantControl);

        return $this->sendRequest('https://sandbox.payneteasy.com/paynet/api/v2/sale-form/' . $this->endpointId, $requestFields);
    }

    public function order_status($data)
    {
        $client_orderid = 'c0f205';
        $orderid = '1508821';

        $requestFields = array(
            'login' => $this->login,
            'client_orderid' => $data['client_orderid'],
            'orderid' => $data['orderid'],
            'by-request-sn' => $data['by-request-sn'],

        );

        $requestFields['control'] = sha1($this->login . $client_orderid . $orderid . $this->merchantControl);

        return $this->sendRequest('https://sandbox.payneteasy.com/paynet/api/v2/status/' . $this->endpointId, $requestFields);
    }
}

<?php

/**
 * Class GateTheater
 */
class GateTheater
{
    private $client;

    private $clientError;

    public function __construct()
    {
        $this->create_soap_client();
    }

    private function create_soap_client()
    {
        $connect_url = "http://ISTicket";
        $username = "username";
        $password = "123123";

        $options = array(
            // Stuff for development.
            'trace' => 1,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,

            // Auth credentials for the SOAP request.
            'login' => $username,
            'password' => $password,
            'x-tonline-session' => session_id()
        );

        try {
            $this->client = new SoapClient($connect_url, $options);
        } catch
        (SoapFault $exception) {
            $this->clientError = $exception;
            echo $exception->getMessage();
        }
    }

    private function call($func, $vars = null): Seance
    {
        if (!empty($this->client) && empty($this->clientError)) {
            $vars = new SoapVar($vars, XSD_STRING);
            $result = call_user_func(array($this->client, $func), '', '', '', $vars);
            return simplexml_load_string($result, 'Seance');
        }
    }

    public function s_info($seance_id, $is_show_hall = false): Seance
    {
        if (is_int($seance_id) || is_string($seance_id)) $seance_id = array($seance_id);
        $seance_tag = '';
        foreach ($seance_id as $value) {
            $seance_tag .= '<Seance Id="' . $value . '"/>';
        }
        $soapData = '<Request IsShowHall="' . $is_show_hall . '" IsShowPrice="1" IsShowAngle="1" IsShowCancel="1">' . $seance_tag . '</Request>';

        return $this->call('S_Info', $soapData);
    }

    public function s_seat_enabled($seance_id, $is_include_selected = true): Seance
    {
        if (is_int($seance_id) || is_string($seance_id)) $seance_id = array($seance_id);
        $seance_tag = '';
        foreach ($seance_id as $value) {
            $seance_tag .= '<Seance Id="' . $value . '"/>';
        }
        $soapData = '<Request IsIncludeSelected="' . $is_include_selected . '" IsShowPrice="1" IsShowAngle="1" IsShowCancel="1">' . $seance_tag . '</Request>';

        return $this->call('S_Seat_Enabled', $soapData);
    }

    public function s_seat_selected(): Seance
    {
        return $this->call('S_Seat_Selected');
    }

    public function s_seat_select(array $seats): ?Seance
    {
        $soapData = '<Request IsIncludeSelected="1">' . $this->seats_tags($seats) . '</Request>';
        return $this->call('S_Seat_Select', $soapData);
    }

    public function s_seat_unselect(array $seats = array()): ?Seance
    {
        $soapData = '<Request IsShowAll="1">' . $this->seats_tags($seats) . '</Request>';
        return $this->call('S_Seat_Unselect', $soapData);
    }

    public function s_order_create(array $user_profile): ?Seance
    {
        $soapData = '<Request>
                        <Order>
                            <Info SiteOrderId="XXX" Comment="прмер" LifeTime="30"/>' . $this->create_customer_tag($user_profile) . '
                            <Delivery/>
                            <Seats>' . $this->seats_tags($user_profile['seats']) . '</Seats>
                        </Order>
                    </Request>';

        return $this->call('S_Order_Create', $soapData);
    }

    public function s_order_info(int $order_id)
    {
        if (empty($order_id)) return;
        $soapData = '<Request><Order Id="' . $order_id . '"/></Request>';
        return $this->call('S_Order_Info', $soapData);
    }

    public function s_order_begin_sold($order, $user_profile)
    {
        if (!$this->create_order_request($order, $user_profile)) return;

        $soapData = $order->Request->asXML();
        return $this->call('S_Order_BeginSold', $soapData);
    }

    public function s_order_complete_sold($order, $user_profile)
    {
        if (!$this->create_order_request($order, $user_profile)) return;

        $soapData = $order->Request->asXML();
        return $this->call('S_Order_CompleteSold', $soapData);
    }

    public function s_order_remove($order, $user_profile)
    {
        if (!$this->create_order_request($order, $user_profile)) return;

        $soapData = $order->Request->asXML();
        return $this->call('S_Order_Remove', $soapData);
    }

    private function print_xml($xml)
    {
        print "<pre>\n";
        print "<h3>XML Result :</h3>";
        var_dump($xml);
        print "</pre>";
    }

    private function debug($result)
    {
        // Отображаем отладочные сообщения
        echo '<h2>Отладка</h2>';
        print "<pre>\n";

        print "<h2>Result</h2>";
        var_export($result);

        print "<h2>Types</h2>";
        var_dump($this->client->__getTypes());

        print "<h2>Cookies</h2>";
        var_dump($this->client->__getCookies());

        echo '<pre>' . htmlspecialchars($this->client->debug_str, ENT_QUOTES) . '</pre>';


        $xml = simplexml_load_string($result);
        print "<h3>XML Result :</h3>";
        var_dump($xml);

        print "<h3>Last Request :</h3>";
        print "RequestHeaders :\n" . htmlentities($this->client->__getLastRequestHeaders()) . "\n";
        echo "REQUEST:\n" . htmlentities($this->client->__getLastRequest()) . "\n";
        print "ResponseHeaders:\n" . htmlentities($this->client->__getLastResponseHeaders()) . "\n";
        echo "RESPONSE:\n" . htmlentities($this->client->__getLastResponse()) . "\n";
        print "</pre>";
    }

    private function seats_tags(array $seats): ?string
    {
        $seats_tags = '';
        foreach ($seats as $seat) {
            $seats_tags .= '<Seat Id="' . $seat . '"/>';
        }
        return $seats_tags;
    }

    private function create_customer_tag(array $user_profile): ?string
    {
        return '<Customer LoyaltyProgramCard="" 
			Name1="' . $user_profile['lastname'] . '" 
			Name2="' . $user_profile['firstname'] . '" 
			Name3="' . $user_profile['patronymic'] . '" 
			Phone="' . $user_profile['phone'] . '" 
			EMail="' . $user_profile['email'] . '"/>';
    }

    private function create_order_request($order, $user_profile): bool
    {
        if (empty($order) || empty($user_profile)) return false;
        $customer = $order->Order->Customer;
        $customer->addAttribute('Name1', $user_profile['lastname']);
        $customer->addAttribute('Name2', $user_profile['firstname']);
        $customer->addAttribute('Name3', $user_profile['patronymic']);

        foreach ($order->Order->Seats->Seance->Sector->Seat as $seat) {
            $seat->addAttribute('Cost', $seat->attributes()->SoldCost);
            $seat->addAttribute('Points', 0);
        }

        $order->addChild('Request');

        $dom_request = dom_import_simplexml($order->Request);
        $dom_order = dom_import_simplexml($order->Order);
        $dom_request->appendChild($dom_order);

        return true;
    }
}

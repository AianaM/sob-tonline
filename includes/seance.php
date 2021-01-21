<?php


class Seance extends SimpleXMLElement
{
    public function send_emails2(): void
    {
        $order_email = $this->Order->Customer->attributes()->EMail;

        $headers = array(
            'From: СахаОпераБалет <info@sakha-opera.ru>',
            'content-type: text/html',
            'bcc: Aiana.Miachina@gmail.com',
        );

        $logo_style = 'background-image: url("https://sakha-opera.ru/wp-content/uploads/2020/07/эмблема.png");';
        $logo_style .= 'background-size: contain; background-repeat: no-repeat; background-position: center;';
        $logo_style .= 'height: 130px; width: 130px; margin: auto; color: transparent;';

        $logo = '<div style="' . $logo_style . '"></div>';
        $logo = '<div class="logo"><a href="https://sakha-opera.ru/">' . $logo . '</a></div>';

        $contacts = '<div class="contacts"><p><a href="https://sakha-opera.ru/">';
        $contacts .= 'Автономное учреждение «Государственный Театр Оперы и Балета им. Д. К. Сивцева-Суорун Омоллоона»</a></p>';
        $contacts .= '<p>г. Якутск, проспект Ленина 46/1</p>';
        $contacts .= '<p>Касса: <a href="tel:+74112360690">+7 (4112) 36-06–90</a></p>';
        $contacts .= '<p>Приемная: <a href="tel:+74112354902">+7 (4112) 35-49-02</a></p>';
        $contacts .= '<p>Email: <a href="mailto:gtobyakutia@mail.ru">gtobyakutia@mail.ru</a></p>';
        $contacts .= '</div>';

        $body_head = '<div>' . $logo . $contacts . '</div>';

        $body = '<div style="margin: auto; max-width: 600px;">' . $body_head . $this->s_order_info() . '</div>';

        remove_all_filters('wp_mail_from');
        remove_all_filters('wp_mail_from_name');

        wp_mail($order_email, 'Ваши билеты на ' . $this->get_seance_show(), $body, $headers);
    }

    public function send_emails(): void
    {
        $order_email = $this->Order->Customer->attributes()->EMail;

        $multiple_to_recipients = array(
//            'Vlad.Miachin@gmail.com',
//            'Aiana.Miachina@gmail.com',
            $order_email
        );

        add_filter('wp_mail_from_name', function ($email_from) {
            return 'СахаОпераБалет';
        });

        remove_filter('wp_mail_content_type', function () {
            return 'text/html';
        });

        add_filter('wp_mail_content_type', function ($content_type) {
            return "text/html";
        });

        $body = '<div style="margin: auto; max-width: 600px;">' . $this->s_order_info() . '</div>';


        wp_mail($multiple_to_recipients, 'Ваши билеты на ' . $this->get_seance_show(), $body);
    }

    private function get_seance_attributes($seance_id = null): SimpleXMLElement
    {
        $by_id = $seance_id ? '[@Id="' . $seance_id . '"]' : '';
        $seance = $this->xpath('//Seance' . $by_id) ?? $this->xpath('//Order/Seats/Seance' . $by_id);
        if (count($seance) === 1) {
            return $seance[0]->attributes();
        }
    }

    public function get_order_id($pretty = false): string
    {
        $order = $this->xpath('//Order');
        if (count($order) === 1) {
            return $pretty
                ? '<div class="order_id"> Номер заказа: <b>' . $order[0]->attributes()->Id . '</b></div>'
                : $order[0]->attributes()->Id;
        }
        return '';
    }

    public function get_seance_show($seance_id = null): string
    {
        $seance = $this->get_seance_attributes($seance_id);
        return $seance->Show;
    }

    public function get_seance_dt($seance_id = null, $pretty = false): string
    {
        $seance = $this->get_seance_attributes($seance_id);
        $dt = strtotime($seance->dtSeance);
        if ($pretty) {
            $dtSeance = '<span class="date">' . date_i18n('l <b>j F</b> Y ', $dt) . '</span>';
            $dtSeance .= '<span class="time">' . date_i18n('в <b>H:i</b>', $dt) . '</span>';
            return $dtSeance;
        }
        return $dtSeance = date_i18n('l j F Y в H:i', $dt);
    }

    public function order_desc(): string
    {
        $show = $this->get_seance_show();
        $dtSeance = $this->get_seance_dt();

        $seats = '';
        foreach ($this->xpath('//Order/Seats/Seance/Sector') as $sector) {
            $rows = [];

            foreach ($sector->xpath('Seat') as $seat) {
                $row = (int)$seat->attributes()->Row;
                if (!array_key_exists($row, $rows)) {
                    $rows[$row] = array();
                }
                $rows[$row][] = (string)$seat->attributes()->Seat;
            }

            $rows_str = '';
            foreach ($rows as $row => $row_seats) {
//                $rows_str .= 'Сектор ' . $sector->attributes()->Name. ' ';
                $rows_str .= 'Ряд: ' . $row;
                $rows_str .= (count($row_seats) > 1 ? ' Места: ' : 'Место: ') . implode(', ', $row_seats) . PHP_EOL;
            }
            $seats .= $rows_str;
        }

        return 'Билеты на ' . $show . ' - ' . $dtSeance . '. ' . PHP_EOL . $seats;
    }

    public function s_order_info(): string
    {
        $title = '<div class="seance seance-id-' . $this->get_order_id() . '">
                <a class="event_date" href="https://sakha-opera.ru/?p=3056">' . $this->get_seance_dt(null, true) . '</a>
                <a class="show" href="https://sakha-opera.ru/?p=3056">' . $this->get_seance_show() . '</a> 
            </div>';

        return '<div style="margin-bottom: 20px;">' . $this->get_order_id(true) . '</div>'
            . '<div  style="margin-bottom: 20px;">' . $title . '</div>'
            . $this->s_seat_selected_pretty_list();
    }

    public function s_order_amount()
    {
        $amount = 0;
        foreach ($this->xpath('//Order/Seats/Seance/Sector/Seat/@SoldCost') as $soldcost) {
            $amount += $soldcost;
        }
        return $amount;
    }

    public function as_array($node = null): array
    {
        if (is_null($node)) {
            $result = [];
            $nodes = $this->xpath('//Seance');
            foreach ($nodes as $_node) {
                $result[(int)$_node->attributes()->Id] = $this->as_array($_node);
            }
            return $result;
        } elseif (is_int($node) || is_string($node)) {
            $seance = $this->xpath('//Seance[@Id="' . $node . '"]') ?: [];
            return $this->as_array($seance[0]);
        }

        $arr = array();
        foreach ($node->attributes() as $key => $value) {
            $arr[$key] = (string)$value;
        }
        if ($node->children()) {
            foreach ($node->children() as $name => $child) {
                if (count($node->xpath($name)) > 1) {
                    $arr[$name][] = $this->as_array($child);
                } else {
                    $arr[$name] = $this->as_array($child);
                }
            }
        }
        return $arr;
    }

    public function s_seat_selected_pretty_list(): string
    {
        $seance = $this->as_array($this->Order->Seats->Seance);
        if (empty($seance)) {
            $seats_selected = $this->as_array($this->Seance->Hall);
            $seats_selected = $seats_selected['Sector']['Seat'];
            $seats_selected = array_filter($seats_selected, function ($seat) {
                return !empty($seat['Selected']);
            });
        } else $seats_selected = $seance['Sector']['Seat'];

        usort($seats_selected, function ($a, $b) {
            if ($a['Row'] === $b['Row'])
                return $a['Seat'] <=> $b['Seat'];
            return $a['Row'] <=> $b['Row'];
        });

        $total = array_sum(array_column($seats_selected, 'Price')) ?: array_sum(array_column($seats_selected, 'SoldCost'));

        $seats_table_head = '<thead><tr><th style="width: 33%">Ряд</th><th style="width: 33%">Место</th><th style="width: 33%">Итог</th></tr></thead>';

        $seats_table_body = '';
        foreach ($seats_selected as $seat) {
            $price = $seat['Price'] ?? $seat['SoldCost'] ?? '';

            $item = '<td class="row" style="text-align: center;">' . $seat['Row'] . '</td>';
            $item .= '<td class="seat" style="text-align: center;">' . $seat['Seat'] . '</td>';
            $item .= '<td class="price" style="text-align: center;">' . $price . '</td>';
            $seats_table_body .= '<tr class="item">' . $item . '</tr>';
        }

        if (empty($seats_table_body)) return '';

        $seats_table_foot = '<tfoot><tr><th scope="row" colspan="2" style="text-align: right;">Итого:</th>
<td style="text-align: center;"><b>' . number_format($total, 2, '.', '') . '</b> ₽</td></tr></tfoot>';
        $seats_table_body = '<tbody class="items">' . $seats_table_body . '</tbody>';

        return '<table style="border: none;">' . $seats_table_head . $seats_table_body . $seats_table_foot . '</table>';
    }

    public function s_seance_ids(): array
    {
        $arr = [];
        foreach ($this->xpath('//Seance/@Id') as $id) {
            array_push($arr, (int)$id);
        }
        return $arr;
    }

    public function seance_pretty_prices($seance_id): string
    {
        $show = $this->xpath('//Seance[@Id="' . $seance_id . '"]/@Show');
        if (empty($show)) return '';

        $min = null;
        $max = null;

        foreach ($this->xpath('//Seance[@Show="' . (string)$show[0] . '"]') as $seance) {
            if ((int)$seance->attributes()->MinPrice < $min || (is_null($min) && (int)$seance->attributes()->MinPrice > 0)) $min = (int)$seance->attributes()->MinPrice;
            if ((int)$seance->attributes()->MaxPrice > $max) $max = (int)$seance->attributes()->MaxPrice;
        }

        return $min . '-' . $max . '₽';
    }

    public function show_all_dates($seance_id): ?array
    {
        $show = $this->xpath('//Seance[@Id="' . $seance_id . '"]/@Show');
        if (empty($show)) return null;

        $group = [];
        foreach ($this->xpath('//Seance[@Show="' . (string)$show[0] . '"]') as $seance) {
            $group[(int)$seance->attributes()->Id] = array(
                'Id' => (int)$seance->attributes()->Id,
                'dtSeance' => $this->get_seance_dt((int)$seance->attributes()->Id, true),
                'FreeCount' => ($seance->attributes()->FreeCount > 0) ? 'Осталось ' . $seance->attributes()->FreeCount . ' билетов' : 'Наличие билетов уточнять',
            );
        }

        return $group;
    }

    public function is_error(): bool
    {
        return !!$this->attributes()->ErrCode;
    }

    public function err_msg(): string
    {
        return $this->attributes()->ErrMsg;
    }

}

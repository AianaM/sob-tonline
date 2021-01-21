<?php

/*
Plugin Name: Sob Tonline
Description: Hello World from and back
Version: 1.0
Author: aiana
Author URI: http://github.com/aianaM
*/

require_once 'includes/gate-theater.php';
require_once 'includes/seance.php';
require_once 'includes/paynet_easy.php';

class SobTonline
{
    private static $_gate_theater = NULL;

    private static $_s_info = null;

    private static $_s_order_info = null;

    static public function getGateTheater()
    {
        if (self::$_gate_theater === NULL)
            self::$_gate_theater = new GateTheater();
        return self::$_gate_theater;
    }

    static public function s_info()
    {
        if (self::$_s_info === NULL) self::$_s_info = self::getGateTheater()->s_info(array());
        return self::$_s_info;
    }

    static public function s_order_info($order_id)
    {
        if (self::$_s_order_info === NULL) self::$_s_order_info = self::getGateTheater()->s_order_info($order_id);
        return self::$_s_order_info;
    }

    public function __construct()
    {
        $this->start_session();

        add_action('init', array($this, 'add_meta_box'));

        add_action('save_post', array($this, 'performance_sob_tonline_fields_update'), 0);

        add_action('elementor/dynamic_tags/register_tags', function ($dynamic_tags) {
            \Elementor\Plugin::$instance->dynamic_tags->register_group('sob-tonline-variables', [
                'title' => 'SobTonline'
            ]);
            include_once('includes/elementor_sob_tonline_tag.php');
            $dynamic_tags->register_tag('ElementorSobTonlineTag');
        });

        add_action('pp_query_actual_shows_filter', function ($query) {
            $meta_query = array(
                'key' => 'seance_id',
                'value' => $this->s_info()->s_seance_ids(),
                'compare' => 'IN'
            );
            $query->set('meta_query', $meta_query);
        });
        if (wp_doing_ajax()) {
            if ($_POST['form_id'] === 'b0fcae9') {
                // Custom Elementor Form Submission Action
                add_action('elementor_pro/forms/form_submitted', function ($module) {
                    $module->add_component('sob_tonline_handler', $this->sob_tonline_handler());
                });
            } else {
                add_action('wp_ajax_sob_tonline_action', array($this, 'sob_tonline_action_callback'));
                add_action('wp_ajax_nopriv_sob_tonline_action', array($this, 'sob_tonline_action_callback'));
            }
        } else {
            add_action('wp', function () {
                if (is_page(3063)) {
                    if (!empty($_GET['pe']) && !empty($_GET['check']) && !empty($_POST['client_orderid'])
                        && !empty($_SESSION['sob_tonline_pe'][$_POST['client_orderid']]
                            && !empty($_SESSION['sob_tonline_pe'][$_POST['client_orderid']]['profile']))
                    ) {
                        $order_id = $_POST['client_orderid'];
                        $profile = $_SESSION['sob_tonline_pe'][$_POST['client_orderid']]['profile'];

                        $order_xml = SobTonline::getGateTheater()->s_order_info($order_id);

                        if (preg_match("/approved/i", $_POST['status'])) {
                            $order_xml = SobTonline::getGateTheater()->s_order_complete_sold($order_xml, $profile);

                            // save just in case
                            if (empty($_SESSION['sob_tonline_pe'])) $_SESSION['sob_tonline_pe'] = array();
                            $_SESSION['sob_tonline_pe'][$order_id]['pe2'] = $_POST;
                            $_SESSION['sob_tonline_pe'][$order_id]['sold'] = $order_xml->as_array();

                            if (!$order_xml->is_error()) {
                                $order_xml->send_emails2();
                                $check = wp_create_nonce($order_id);
                                wp_safe_redirect(site_url('?page_id=3007&order=' . $order_id . '&check=' . $check));
                                exit;
                            } else {
                                self::admin_alarm(json_encode($order_xml, JSON_UNESCAPED_UNICODE));
                            }
                        } else {
                            $order_xml = SobTonline::getGateTheater()->s_order_remove($order_xml, $profile);
                            $_SESSION['sob_tonline_pe'][$order_id]['remove'] = $order_xml->as_array();
                            self::admin_alarm(json_encode($order_xml, JSON_UNESCAPED_UNICODE));
                        }
                    }
                    // add styles and scripts
                    wp_enqueue_style('sob_tonline_style?v=test', plugins_url('assets/css/style.css', __FILE__));
                    wp_enqueue_script('sob_tonline_script?v=test', plugins_url('assets/js/scripts.js', __FILE__), array('jquery'));

                    // config for ajax
                    $config = wp_json_encode(array(
                        'ajaxurl' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('sob_tonline_frontend')
                    ));
                    wp_add_inline_script('sob_tonline_script', 'var sob_tonline_config = ' . $config, 'before');
                }
            });
        }
    }

    public function add_meta_box()
    {
        $icon = plugins_url('assets/shapes/theatre-2.svg', __FILE__);
        register_post_type('performance', array(
            'labels' => array(
                'name' => 'Репертуар',
                'singular_name' => 'Спектакль', // отдельное название записи типа Book
                'add_new' => 'Добавить новый',
                'add_new_item' => 'Добавить новый спектакль',
                'edit_item' => 'Редактировать спектакль',
                'new_item' => 'Новый спектакль',
                'view_item' => 'Посмотреть спектакль',
                'search_items' => 'Найти спектакль',
                'not_found' => 'Спектаклей не найдено',
                'not_found_in_trash' => 'В корзине спектаклей не найдено',
                'parent_item_colon' => '',
                'menu_name' => 'Репертуар'

            ),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => true,
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => null,
            'supports' => array('title', 'author', 'thumbnail', 'comments'),
            'menu_icon' => $icon,
            'register_meta_box_cb' => function () {
                add_meta_box('sob_tonline_fields', 'Дополнительные поля', array($this, 'performance_sob_tonline_fields'), 'performance', 'normal', 'high');
            },
        ));
    }

    public function performance_sob_tonline_fields($post)
    {
        $gateTheater = new GateTheater();
        $s_info_xml = $gateTheater->s_info(array());
        $s_info_seance_xml = $s_info_xml->xpath('//Seance');
        usort($s_info_seance_xml, function ($a, $b) {
            return strtotime($a->attributes()->dtSeance) <=> strtotime($b->attributes()->dtSeance);
        });


        $run_time = get_post_meta($post->ID, 'run_time', true);
        $seance_id = get_post_meta($post->ID, 'seance_id', true);

        $option = '<option>----</option>';
        foreach ($s_info_seance_xml as $seance) {
            $selected = $seance_id === (string)$seance->attributes()->Id ? 'selected' : '';
            $option .= '<option value="' . $seance->attributes()->Id . '" ' . $selected . '>' . $seance->attributes()->Show . ' - ' . $s_info_xml->get_seance_dt((int)$seance->attributes()->Id, true) . '</option>';
        }
        $option .= '<option value="Other">Другой</option>';

        $tags = '<label>Спектакль <select onchange="if(this.value === \'Other\') {
    document.querySelector(\'#seance-id-input\').setAttribute(\'value\', \'\');
    document.querySelector(\'#seance-id-label\').hidden = false;}else {
    document.querySelector(\'#seance-id-input\').setAttribute(\'value\', this.value);
    document.querySelector(\'#seance-id-label\').hidden = true;
}
">' . $option . '</select></label>';
        $tags .= '<label id="seance-id-label" hidden>Id спектакля <input id="seance-id-input" type="text" name="sob_tonline[seance_id]" value="' . $seance_id . '" style="width:50px"/></label>';
        $tags .= '<p><label>Продолжительность <input type="text" name="sob_tonline[run_time]" value="' . $run_time . '" style="width:50%"/></label></p>';
        $tags .= '<input type="hidden" name="sob_tonline_fields_nonce" value="' . wp_create_nonce(__FILE__) . '"/>';

        print_r($tags);
    }

    public function performance_sob_tonline_fields_update($post_id)
    {
        if (empty($_POST['sob_tonline']) || !wp_verify_nonce($_POST['sob_tonline_fields_nonce'], __FILE__)
            || wp_is_post_autosave($post_id) || wp_is_post_revision($post_id))
            return false;

        $_POST['sob_tonline'] = array_map('sanitize_text_field', $_POST['sob_tonline']);
        foreach ($_POST['sob_tonline'] as $key => $value) {
            if (empty($value)) {
                delete_post_meta($post_id, $key);
                continue;
            }
            update_post_meta($post_id, $key, $value);
        }
        return $post_id;
    }

    public function sob_tonline_action_callback()
    {
        check_ajax_referer('sob_tonline_frontend', 'nonce_code');
        if ($_POST['act'] === 'show_hall' && !empty($_POST['seance_id'])) {
            // TODO: check S_Seat_Enabled function
//            $xml = self::getGateTheater()->s_seat_enabled($_POST['seance_id']);
            $xml = self::getGateTheater()->s_info($_POST['seance_id'], true);

            wp_send_json_success(json_encode($xml->as_array(), JSON_UNESCAPED_UNICODE));

        } elseif ($_POST['act'] === 'select_seats' && !empty($_POST['seats'])) {
            $seats = json_decode(stripslashes($_POST['seats']), true);
            if (empty($seats)) wp_send_json_error();

            // TODO: check it
            if ($_POST['clear_all_select'] === 'true') {
                self::getGateTheater()->s_seat_unselect();
            }

            $select_xml = self::getGateTheater()->s_seat_select($seats);

            if (!empty($select_xml->is_error())) wp_send_json_error([
                'message' => $select_xml->err_msg(),
                'errors' => [json_encode($select_xml, JSON_UNESCAPED_UNICODE)]
            ]);


            // TODO: check this - as_array2 twice
            $seats = $select_xml->as_array($select_xml->Seats);
            $select = $select_xml->as_array($select_xml->Select);
            $result = array('seats' => $seats['Seat'], 'select' => $select['Seat']);

            wp_send_json_success(json_encode($result, JSON_UNESCAPED_UNICODE));
        } else wp_send_json_error([
            'message' => 'oO ' . json_encode($_POST, JSON_UNESCAPED_UNICODE)
        ]);
    }

    private function sob_tonline_handler()
    {
        $user_profile = $_POST['form_fields'];
        $user_profile['seats'] = json_decode(stripslashes($user_profile['seats']), true);

        $session_id = session_id();

        if (empty($user_profile['seats']) || empty($session_id)) wp_send_json_error([
            'message' => 'Ошибка на сервере'
        ]);

        $order_xml = self::getGateTheater()->s_order_create($user_profile);

        if (!empty($order_xml->is_error())) wp_send_json_error([
            'message' => $order_xml->err_msg(),
            'errors' => [json_encode($order_xml, JSON_UNESCAPED_UNICODE)]
        ]);

        // TODO: check if user_profile no need
        $order_xml = self::getGateTheater()->s_order_begin_sold($order_xml, $user_profile);

        if (!empty($order_xml->is_error())) wp_send_json_error([
            'message' => $order_xml->err_msg(),
            'errors' => [json_encode($order_xml, JSON_UNESCAPED_UNICODE)]
        ]);

        $user_profile['order_desc'] = $order_xml->order_desc();
        $user_profile['client_orderid'] = $order_xml->get_order_id();
        $user_profile['amount'] = $order_xml->s_order_amount();

        $check = wp_create_nonce($session_id);
        $_SESSION['check'] = $check;

        $pay = new PaynetEasy();
        $pay_response = $pay->sale_form($user_profile, 'https://sakha-opera.ru/kupit-bilet/?pe=' . $session_id . '&check=' . $check);

        // update session
        if (empty($_SESSION['sob_tonline_pe'])) $_SESSION['sob_tonline_pe'] = array();
        $_SESSION['sob_tonline_pe'][$order_xml->get_order_id()] = array(
            'pe' => $pay_response,
            'profile' => $user_profile,
        );

        if (!empty($pay_response['redirect-url'])) {
            wp_send_json_success([
                'message' => 'Спасибо, сейчас вы перейдете на страницу оплаты',
                'data' => ['redirect_url' => $pay_response['redirect-url']],
            ]);
        } else {
            if (empty($selected_array)) wp_send_json_error([
                'message' => 'Что-то пошло не так :('
            ]);
        }
    }

    static public function admin_alarm(string $message)
    {
        wp_mail('Aiana.Miachina@gmail.com', 'SOB', $message);
    }

    private function start_session(): void
    {
        if (!empty($_GET['pe']) && !empty($_GET['check'])) {
            $sid = $_GET['pe'];
            if (!session_id($sid)) session_start();
            if (empty($_SESSION['check']) || $_SESSION['check'] !== $_GET['check']) {
                session_write_close();
                session_id();
                session_start();
            }
        } else {
            if (!session_id()) session_start();
        }
    }
}

new SobTonline();

function help_print($some)
{
    print '<pre>';
    var_dump($some);
    print '</pre>';
}

<?php


class ElementorSobTonlineTag extends \Elementor\Core\DynamicTags\Tag
{

    /**
     * Get Name
     *
     * Returns the Name of the tag
     *
     * @return string
     * @since 2.0.0
     * @access public
     *
     */
    public function get_name()
    {
        return 'Sob-Tonline';
    }

    /**
     * Get Title
     *
     * Returns the title of the Tag
     *
     * @return string
     * @since 2.0.0
     * @access public
     *
     */
    public function get_title()
    {
        return __('Sob-Tonline Variable', 'elementor-pro');
    }

    /**
     * Get Group
     *
     * Returns the Group of the tag
     *
     * @return string
     * @since 2.0.0
     * @access public
     *
     */
    public function get_group()
    {
        return 'sob-tonline-variables';
    }

    /**
     * Get Categories
     *
     * Returns an array of tag categories
     *
     * @return array
     * @since 2.0.0
     * @access public
     *
     */
    public function get_categories()
    {
        return [\Elementor\Modules\DynamicTags\Module::URL_CATEGORY, \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY];
    }

    /**
     * Register Controls
     *
     * Registers the Dynamic tag controls
     *
     * @return void
     * @since 2.0.0
     * @access protected
     *
     */
    protected function _register_controls()
    {

        $variables = array(
            'empty' => 'Выбрать',
            'choice-date-url' => 'URL Выбор даты',
            'choice-seance-url' => 'URL Купить билет',
            'seance-price' => 'Стоимость билетов',
            'seance-dt' => 'Дата время сеанса',
            'seance-show' => 'Название спектакля',
            'seance-data' => 'Атрибуты сеанса',
            'order-info' => 'Данные заказа'
        );

        $this->add_control(
            'param_name',
            [
                'label' => __('Param Name', 'elementor-pro'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => $variables,
            ]
        );
    }

    /**
     * Render
     *
     * Prints out the value of the Dynamic tag
     *
     * @return void
     * @since 2.0.0
     * @access public
     *
     */
    public function render()
    {
        $param_name = $this->get_settings('param_name');

        $variables = [];
        $seance_id = get_post_meta(get_the_ID(), 'seance_id', true);
        if (!empty($seance_id)) {
            $seance_xml = SobTonline::s_info();
            $show = $seance_xml->get_seance_show($seance_id);
            $dt_seance = $seance_xml->get_seance_dt($seance_id);
            $prices = $seance_xml->seance_pretty_prices($seance_id);
            $dates = $seance_xml->show_all_dates($seance_id);
            $dates = json_encode($dates, JSON_UNESCAPED_UNICODE);
            $data = 'data-seance|' . htmlspecialchars($dates) . PHP_EOL . 'data-seance-id|' . $seance_id;
            $variables = array(
                'choice-date-url' => '/kupit-bilet/?seance_id=' . $seance_id,
                'choice-seance-url' => '/kupit-bilet',
                'seance-price' => $prices,
                'seance-dt' => $dt_seance,
                'seance-show' => $show,
                'seance-data' => $data
            );
        }
        $variables['order-info'] = '';
        if (!empty($_GET['order']) && wp_verify_nonce($_GET['check'], $_GET['order'])) {
            $order_xml = SobTonline::s_order_info($_GET['order']);
            $variables['order-info'] = $order_xml->s_order_info();
        }
        if (!$param_name) {
            return;
        }

        if (!isset($variables[$param_name])) {
            return;
        }

        $value = $variables[$param_name];
        echo wp_kses_post($value);
    }
}

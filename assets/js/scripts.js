(function ($) {

    'use strict';

    $(function () {
        const legend_css_enable = $('#hall-block #legend #enable .elementor-icon').css(['backgroundColor', 'fill']);
        const legend_css_not_enable = $('#hall-block #legend #not-enable .elementor-icon').css(['backgroundColor', 'fill']);
        const legend_css_selected = $('#hall-block #legend #selected .elementor-icon').css(['backgroundColor', 'fill']);
        const hall_collection = {};
        const selected_collection = {};

        // waiting any ajaxSusses fo test
        $(document).ajaxSuccess(function (event, request, settings, response) {
            console.log(event, request, settings, response);
        });

        const overlay = '<div class="overlay"><div class="lds-circle"><div></div></div></div>';
        $(document).on({
            ajaxStart: function () {
                $("body").append(overlay);
                $("body").addClass("loading");
            },
            ajaxStop: function () {
                $("body").removeClass("loading");
            }
        });

        // show all seance list on nav-btn click
        $('.seance-nav .show-seance-list a').click(function (event) {
            event.preventDefault();

            const refresh = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.pushState({path: refresh}, '', refresh);
            set_act_btn('seance');

            $('#seance-list-block, #seance-list-block .seance-block').removeClass('pp-visibility-hidden');
            $('#seance-dt-list, #select-seat-block, #select-seat-btn-wrapper, #hall-block, #order-block').addClass('pp-visibility-hidden');
        });

        // show seance dt list on nav-btn click
        $('.seance-nav .show-seance-dt a').click(function (event) {
            event.preventDefault();

            const searchParams = new URLSearchParams(window.location.search);
            if (!searchParams.has('seance_dt') && !searchParams.has('seance_order')) return;
            let seance_block_id = searchParams.has('seance_dt') ? searchParams.get('seance_dt') : searchParams.get('seance_order');
            seance_block_id = $('#seance-dt-list .show-td-item[data-seance-id="' + seance_block_id + '"]').data('block-seance-id');

            show_seance_dt_list(seance_block_id);
        });

        // show hall on nav-btn click
        $('.seance-nav .show-seance-hall a').click(function (event) {
            event.preventDefault();

            const searchParams = new URLSearchParams(window.location.search);
            if (!searchParams.has('seance_order')) return;
            const seance_id = searchParams.get('seance_order');
            const seance_block_id = $('#seance-dt-list .show-td-item[data-seance-id="' + seance_id + '"]').data('block-seance-id');

            show_cart('hall');
            show_seance_dt_list(seance_block_id);
            show_hall(seance_id);

        });

        $('#zoom-in').click(function (event) {
            event.preventDefault();
            let zoom = $('#seance-hall').css('zoom');
            zoom = parseFloat(zoom) + 0.2;
            $('#seance-hall').css('zoom', zoom);
            if (zoom > 0.5) {
                $('#seance-hall .seat, #seance-hall .row').css('font-size', '0.7em');
            }
        });

        $('#zoom-out').click(function (event) {
            event.preventDefault();
            let zoom = $('#seance-hall').css('zoom');
            zoom = parseFloat(zoom) - 0.2;
            $('#seance-hall').css('zoom', zoom);
            if (zoom < 0.5) {
                $('#seance-hall .seat, #seance-hall .row').css('font-size', '8px');
            }
        });

        function show_seance_dt_list(seance_id) {
            const refresh = window.location.protocol + "//" + window.location.host + window.location.pathname + '?seance_id=' + seance_id;
            window.history.pushState({path: refresh}, '', refresh);

            set_act_btn('dt');

            // close all
            $('#seance-list-block .seance-block, #seance-dt-list .show-td-item, #select-seat-block, #select-seat-btn-wrapper, #hall-block, #order-block').addClass('pp-visibility-hidden');

            // open only seance
            $('#seance-dt-list, #seance-dt-list .show-td-item[data-block-seance-id="' + seance_id + '"], ' +
                '#seance-list-block, #seance-list-block .seance-block[data-seance-id="' + seance_id + '"]')
                .removeClass('pp-visibility-hidden');
            scroll_to($('#seance-dt-list'));
        }

        // show seance dt list
        $('.select-seance a.elementor-button-link').click(function (event) {
            event.preventDefault();

            const seance_block = $(this).closest('.seance-block');
            const seance_id = seance_block.data('seance-id');
            show_seance_dt_list(seance_id);
        });

        function show_hall(seance_id) {
            if (hall_collection[seance_id]) return create_hall_elm(seance_id);

            const seance_block = $('.seance-block[data-seance-id="' + seance_id + '"]');
            const dtSeance = seance_block.data('seance') ? seance_block.data('seance')[seance_id].dtSeance : '';
            // TODO: const show = seance_block.

            const data = {
                action: 'sob_tonline_action',
                act: 'show_hall',
                seance_id: seance_id,
                nonce_code: sob_tonline_config.nonce
            };

            jQuery.post(sob_tonline_config.ajaxurl, data, function (response) {
                const s_info = JSON.parse(response.data);
                s_info[seance_id][dtSeance] = dtSeance;
                hall_collection[seance_id] = s_info[seance_id];
                create_hall_elm(seance_id);
            });
        }

        function create_hall_elm(seance_id) {
            const refresh = window.location.protocol + "//" + window.location.host + window.location.pathname + '?seance_dt=' + seance_id;
            window.history.pushState({path: refresh}, '', refresh);

            const seats = hall_collection[seance_id].Hall.Sector.Seat;
            const dtSeance = $('#seance-dt-list .show-td-item[data-seance-id="' + seance_id + '"] .seance-dt').html();
            // show s_info in cart and order info
            $('#hall-block .seance-dt').html(dtSeance);
            $('#order-block .order-info').empty();
            $('#order-block .order-info').append('<div>' + dtSeance + '</div>');
            $('#order-block .order-info').append('<div>' + hall_collection[seance_id].Show + '</div>');

            seats.sort(function (a, b) {
                if (a.Y !== b.Y) return a.Y - b.Y;
                return a.X - b.X;
            });

            selected_collection[$('#seance-hall').attr('data-seance-id')] = selected_seats();

            $('#seance-hall').empty();
            $('#seance-hall').attr('data-seance-id', seance_id);
            $('#select-seat-block [id^="selected-seat-"]').remove();
            $('#select-seat-block .price.sum span').text('');

            let hall_height = 0;
            let hall_width = 0;

            const rows = {};
            $.each(seats, (i, item) => {
                const seat = $('<a class="seat" href="javascript:void(0);"></a>');

                // collect rows
                if (!rows[item.Row] || rows[item.Row].x > parseFloat(item.X)) {
                    rows[item.Row] = {
                        x: parseFloat(item.X),
                        y: item.Y,
                        row: item.Row
                    };
                }

                seat.click(function () {
                    select_seat($(this));
                });

                seat.data('test');
                seat.data('test', item);

                $.each(item, (index, value) => {
                    seat.attr('data-' + index, value);
                });
                seat.text(item.Seat);

                seat.css({
                    top: item.Y + 'px',
                    left: item.X + 'px'
                });

                if (item.IsEnable === "1" && legend_css_enable) seat.css(legend_css_enable);
                else if (legend_css_not_enable) seat.css(legend_css_not_enable);

                if (hall_height < parseInt(item.Y)) hall_height = parseInt(item.Y);
                if (hall_width < parseInt(item.X)) hall_width = parseInt(item.X);
                $('#seance-hall').append(seat);
            });
            $.each(Object.values(rows), (i, item) => {
                const row = $('<span class="row"></span>');
                row.text('Ряд ' + item.row);
                row.css({
                    top: item.y + 'px',
                    left: item.x - 55 + 'px'
                });
                $('#seance-hall').append(row);
            });

            if (selected_collection[seance_id] !== undefined) {
                selected_collection[seance_id].forEach(value => {
                    select_seat($('#hall-block #seance-hall .seat[data-id="' + value + '"]'));
                });
            }

            $('#seance-hall').css({
                height: hall_height + 100 + 'px',
                // width: hall_width + 100 + 'px',
            });

            $('#seance-dt-list .show-td-item').addClass('pp-visibility-hidden');
            $('#select-seat-block, #select-seat-btn-wrapper, #hall-block').removeClass('pp-visibility-hidden');
            $('#select-seats-btn').attr('data-seance-id', seance_id);
            set_act_btn('hall');
            scroll_to($('#hall-block'));
        }

        function show_cart(position) {
            // move all cart to profile form block
            if (position === 'hall') $('#select-seat-block').insertAfter($('#hall-block'));
            if (position === 'order') $('#order-block .order-info').append($('#select-seat-block'));
        }

        function scroll_to(elm) {
            $("body,html").animate(
                {
                    scrollTop: elm.offset().top
                },
                800 //speed
            );
        }

        function set_act_btn(btn) {
            if (btn === 'dt') btn = 'show-seance-dt'; // $('.seance-nav .show-seance-dt a');
            else if (btn === 'seance') btn = 'show-seance-list'; // $('.seance-nav .show-seance-list a');
            else if (btn === 'hall') btn = 'show-seance-hall'; // $('.seance-nav .show-seance-hall a');
            else if (btn === 'order') btn = 'show-order-form'; // $('.seance-nav .show-order-form a');

            const act_a = $('.seance-nav .seance-act a');
            const current_a = $('.seance-nav .' + btn + ' a');

            current_a.css(act_a.css(['backgroundColor', 'color', 'border-radius']));
            act_a.css(current_a.css(['backgroundColor', 'color', 'border-radius']));

            $('.seance-nav .seance-act').removeClass('seance-act');
            $('.seance-nav .' + btn).addClass('seance-act');
        }

        function selected_seats() {
            return $('#seance-hall .seat.selected').map(function () {
                return $(this).data('id');
            }).get();
        }

        // show profile form
        $('#select-seats-btn').click(function (event) {
            event.preventDefault();

            let selectedSeats = $('#seance-hall .seat.selected').map(function () {
                return $(this).data('id');
            }).get();

            selectedSeats = JSON.stringify(selectedSeats);
            const seance_id = $('#select-seats-btn').data('seance-id');

            const data = {
                action: 'sob_tonline_action',
                act: 'select_seats',
                seats: selectedSeats,
                nonce_code: sob_tonline_config.nonce
            };

            jQuery.post(sob_tonline_config.ajaxurl, data, function (response) {
                if (!response.success) {
                    alert(response.data.message);
                    return;
                }
                const s_info = JSON.parse(response.data);
                if (s_info.select) {

                    const refresh = window.location.protocol + "//" + window.location.host + window.location.pathname + '?seance_order=' + seance_id;
                    window.history.pushState({path: refresh}, '', refresh);

                    // set seats into hidden input
                    $('#order_create input[name="form_fields[seats]"]').val(selectedSeats);

                    show_cart('order');
                    // $('#select-seat-block').children().removeClass('.elevation .elevation-ch');
                    $('#order-block, #select-seat-block').removeClass('pp-visibility-hidden');
                    $('#hall-block, #select-seat-btn-wrapper, #seance-dt-list, #seance-list-block').addClass('pp-visibility-hidden');
                    set_act_btn('order');
                    scroll_to($('#order-block'));
                }
            });
        });

        // click on seat
        function select_seat(elm) {
            if (elm.data('isenable') !== 1) return;

            elm.toggleClass('selected');

            if (elm.hasClass('selected')) {
                elm.css(legend_css_selected);

                const cart_item = $('#select-seat-block .cart-item.pp-visibility-hidden');
                const item = cart_item.clone();

                item.attr('id', 'selected-seat-' + elm.data('id'));
                item.find('.row span').text(elm.data('row'));
                item.find('.seat span').text(elm.data('seat'));
                item.find('.price span').text(elm.data('price'));
                cart_item.after(item);
                item.removeClass('pp-visibility-hidden');

                $('#select-seat-block .cart-item').sort(function (a, b) {
                    if (parseFloat($(a).find('.row span').text()) === parseFloat($(b).find('.row span').text()))
                        return parseFloat($(a).find('.seat span').text()) - parseFloat($(b).find('.seat span').text());
                    return parseFloat($(a).find('.row span').text()) - parseFloat($(b).find('.row span').text());
                }).appendTo($('#select-seat-block .cart-item').parent());
            } else {
                if (elm.data('isenable') === 1) elm.css(legend_css_enable);
                else elm.css(legend_css_not_enable);
                $('#select-seat-block #selected-seat-' + elm.data('id')).remove();
            }

            // show total
            let total = 0;
            $('#select-seat-block [id^="selected-seat-"] .price span').each(function () {
                total += parseFloat($(this).text());
            });
            $('#select-seat-block .price.sum span').text(total.toFixed(2));
        }

        // create all seance dt
        function create_dt() {
            const first_td_list = $('#seance-dt-list .show-td-item').first();
            $('.seance-block').each(function () {
                const seance_block = $(this).closest('.seance-block');
                const seance_id = seance_block.data('seance-id');
                const data = seance_block.data('seance');
                const refresh = window.location.protocol + "//" + window.location.host + window.location.pathname;

                $.each(data, function (i, item) {
                    const new_td_list = first_td_list.clone();
                    new_td_list.attr('data-seance-id', item.Id);
                    new_td_list.attr('data-block-seance-id', seance_id);
                    first_td_list.before(new_td_list);

                    let dt_section = new_td_list.find('.seance-dt-block');
                    dt_section.find('.seance-dt').html(item.dtSeance);
                    dt_section.find('.seance-free-count').html(item.FreeCount);

                    const btn = dt_section.find('.select-seance-dt a');
                    btn.attr('href', refresh + '&dt=' + item.Id);
                    btn.attr('data-seance-id', item.Id);
                    btn.attr('data-block-seance-id', seance_id);
                    btn.click(function (event) {
                        event.preventDefault();
                        const seance_id = btn.data('seance-id');
                        show_hall(seance_id);
                    });
                });
            });
            first_td_list.remove();
        }

        create_dt();

        function start_page() {
            const searchParams = new URLSearchParams(window.location.search);
            if (searchParams.has('seance_id')) {
                const seance_id = searchParams.get('seance_id');
                const seance_block_id = $('#seance-dt-list .show-td-item[data-seance-id="' + seance_id + '"]').data('block-seance-id');
                show_seance_dt_list(seance_block_id);
            } else if (searchParams.has('seance_dt') || searchParams.has('seance_order')) {
                const seance_id = searchParams.has('seance_dt') ? searchParams.get('seance_dt') : searchParams.get('seance_order');
                const seance_block_id = $('#seance-dt-list .show-td-item[data-seance-id="' + seance_id + '"]').data('block-seance-id');

                show_seance_dt_list(seance_block_id);
                show_hall(seance_id);
            }
        }

        start_page();
    });

})(jQuery);

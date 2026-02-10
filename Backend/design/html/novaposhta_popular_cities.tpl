{$meta_title=$btr->sviat_np_popular_cities_title scope=global}

<div class="row">
    <div class="col-lg-12 col-md-12">
        <div class="wrap_heading">
            <div class="box_heading heading_page">
                {$btr->sviat_np_popular_cities_title|escape}
            </div>
        </div>
    </div>
</div>

{if $error_message}
    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="alert alert--icon alert--error">
                <div class="alert__content">
                    <div class="alert__title">{$error_message|escape}</div>
                </div>
            </div>
        </div>
    </div>
{/if}

{if $success_message}
    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="alert alert--icon alert--success">
                <div class="alert__content">
                    <div class="alert__title">{$success_message|escape}</div>
                </div>
            </div>
        </div>
    </div>
{/if}



<div class="boxed fn_toggle_wrap">
    <div class="heading_box">
        {$btr->sviat_np_popular_cities_settings_title|escape}
    </div>
    <div class="toggle_body_wrap on fn_card">
        <form method="post" class="fn_form_list fn_settings_form">
            <input type="hidden" name="session_id" value="{$smarty.session.id}">
            <div class="row mb-1">
                <div class="col-lg-12 col-md-12">
                    <div class="activity_of_switch activity_of_switch--left">
                        <div class="activity_of_switch_item">
                            <div class="okay_switch clearfix">
                                <label
                                    class="switch_label">{$btr->sviat_np_popular_cities_enable_ip_detection|escape}</label>
                                <label class="switch switch-default">
                                    <input class="switch-input" name="sviat__np_popular_cities__enable_ip_detection"
                                        value="1" type="checkbox" id="np_enable_ip_detection"
                                        {if $enable_ip_detection}checked{/if}>
                                    <span class="switch-label"></span>
                                    <span class="switch-handle"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="form_text mt-1">
                        {$btr->sviat_np_popular_cities_enable_ip_detection_hint|escape}
                    </div>
                </div>
            </div>
        </form>

        <div class="row mt-1 mb-1">
            <div class="col-lg-12 col-md-12">
                <div class="alert alert--icon alert--info">
                    <div class="alert__content">
                        <div class="alert__title">{$btr->sviat_np_popular_cities_important|escape}</div>
                        <p>{$btr->sviat_np_popular_cities_update_info|escape}</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12 col-md-12">
                <div class="fn_progress_block"></div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12 col-md-12">
                <button type="button" class="btn btn_small btn-warning fn_update_cities">
                    {include file='svg_icon.tpl' svgId='update'}
                    <span>{$btr->sviat_np_popular_cities_update_button|escape}</span>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="boxed fn_toggle_wrap">
    <div class="heading_box">
        {$btr->sviat_np_popular_cities_add|escape}
    </div>
    <div class="toggle_body_wrap on fn_card">
        <form method="post" class="fn_form_list">
            <input type="hidden" name="session_id" value="{$smarty.session.id}">
            <div class="row">
                <div class="col-lg-6 col-md-6">
                    <div class="heading_label heading_label--required">
                        {$btr->sviat_np_popular_cities_select_city|escape}</div>
                    <div class="mb-1">
                        <input type="text" class="fn_newpost_city_name form-control" name="newpost_city_name"
                            autocomplete="off" required>
                        <input type="hidden" name="city_ref" value="">
                    </div>
                </div>
                <div class="col-lg-6 col-md-6">
                    <div class="heading_label">&nbsp;</div>
                    <div class="mb-1">
                        <button type="submit" name="add_city" value="1" class="btn btn_small btn_blue">
                            {include file='svg_icon.tpl' svgId='plus'}
                            <span>{$btr->sviat_np_popular_cities_add|escape}</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    {if $popular_cities}
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12">
                <form id="list_form" method="post" class="fn_form_list fn_fast_button">
                    <input type="hidden" name="session_id" value="{$smarty.session.id}">
                    <div class="okay_list">
                        <div class="okay_list_head">
                            <div class="okay_list_boding okay_list_drag"></div>
                            <div class="okay_list_heading okay_list_check">
                                <input class="hidden_check fn_check_all" type="checkbox" id="check_all_1" name=""
                                    value="" />
                                <label class="okay_ckeckbox" for="check_all_1"></label>
                            </div>
                            <div class="okay_list_heading okay_list_features_name">
                                {$btr->sviat_np_popular_cities_city_name|escape}</div>
                            <div class="okay_list_heading okay_list_close"></div>
                        </div>
                        <div id="sortable" class="okay_list_body sortable">
                            {foreach $popular_cities as $city}
                                <div class="fn_row okay_list_body_item fn_sort_item">
                                    <div class="okay_list_row">
                                        <input type="hidden" name="positions[{$city->id}]" value="{$city->position|escape}">

                                        <div class="okay_list_boding okay_list_drag move_zone">
                                            {include file='svg_icon.tpl' svgId='drag_vertical'}
                                        </div>

                                        <div class="okay_list_boding okay_list_check">
                                            <input class="hidden_check" type="checkbox" id="id_{$city->id}" name="check[]"
                                                value="{$city->id}" />
                                            <label class="okay_ckeckbox" for="id_{$city->id}"></label>
                                        </div>

                                        <div class="okay_list_boding okay_list_features_name">
                                            {$city->name|escape}
                                        </div>

                                        <div class="okay_list_boding okay_list_close">
                                            <button data-hint="{$btr->general_delete|escape}" type="button"
                                                class="btn_close fn_remove hint-bottom-right-t-info-s-small-mobile  hint-anim"
                                                data-toggle="modal" data-target="#fn_action_modal"
                                                onclick="success_action($(this));">
                                                {include file='svg_icon.tpl' svgId='trash'}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            {/foreach}
                        </div>

                        <div class="okay_list_footer fn_action_block">
                            <div class="okay_list_foot_left">
                                <div class="okay_list_boding okay_list_drag"></div>
                                <div class="okay_list_heading okay_list_check">
                                    <input class="hidden_check fn_check_all" type="checkbox" id="check_all_2" name=""
                                        value="" />
                                    <label class="okay_ckeckbox" for="check_all_2"></label>
                                </div>
                                <div class="okay_list_option">
                                    <select name="action" class="selectpicker form-control">
                                        <option value="delete">{$btr->general_delete|escape}</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn_small btn_blue">
                                {include file='svg_icon.tpl' svgId='checked'}
                                <span>{$btr->general_apply|escape}</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    {else}
        <div class="heading_box mt-1">
            <div class="text_grey">{$btr->sviat_np_popular_cities_no_items|escape}</div>
        </div>
    {/if}
</div>

<script src="{$rootUrl}/backend/design/js/autocomplete/jquery.autocomplete-min.js"></script>
{literal}
    <script>
        $(function() {
            $(".fn_newpost_city_name").devbridgeAutocomplete({
                serviceUrl: okay.router['OkayCMS_NovaposhtaCost_find_city'],
                minChars: 1,
                maxHeight: 320,
                noCache: true,
                onSelect: function(suggestion) {
                    $('input[name="city_ref"]').val(suggestion.data.ref);
                },
                formatResult: function(suggestion, currentValue) {
                    var reEscape = new RegExp('(\\' + ['/', '.', '*', '+', '?', '|', '(', ')', '[', ']', '{', '}', '\\'].join('|\\') + ')', 'g');
                    var pattern = '(' + currentValue.replace(reEscape, '\\$1') + ')';
                    return "<span>" + suggestion.value.replace(new RegExp(pattern, 'gi'),
                        '<strong>$1<\/strong>') + "<\/span>";
                }
            });

            $('button[name="add_city"]').on('click', function(e) {
                var cityRefInput = $('input[name="city_ref"]');
                var cityInput = $(".fn_newpost_city_name");
                var ref = cityRefInput.val();

                if (!ref) {
                    e.preventDefault();
                    e.stopPropagation();
                    alert({/literal}'{$btr->sviat_np_popular_cities_select_city_validation|escape}'{literal});
                    cityInput.focus();
                    return false;
                }
            });

            $('form.fn_form_list').on('submit', function(e) {
                var submitButton = $(document.activeElement);
                var clickedButton = e.originalEvent && e.originalEvent.submitter ? $(e.originalEvent
                    .submitter) : null;

                if ((submitButton.attr('name') === 'add_city') || (clickedButton && clickedButton.attr(
                        'name') === 'add_city')) {
                    var ref = $(this).find('input[name="city_ref"]').val();
                    var cityInput = $(".fn_newpost_city_name");

                    if (!ref) {
                        e.preventDefault();
                        alert({/literal}'{$btr->sviat_np_popular_cities_select_city_validation|escape}'{literal});
                        cityInput.focus();
                        return false;
                    }
                }

                if ($(this).attr('id') === 'list_form') {
                    $('#sortable .fn_row').each(function(index) {
                        $(this).find('input[name^="positions"]').val(index + 1);
                    });
                }
            });

        {/literal}
        {if $success_message}
            {literal}
                $(".fn_newpost_city_name").val('');
                $('input[name="city_ref"]').val('');
            {/literal}
        {/if}
        {literal}

            $(document).on('change', '#np_enable_ip_detection', function() {
                var form = $(this).closest('form.fn_settings_form');
                var formData = form.serialize();
                formData += '&save_settings=1';

                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                    },
                    error: function() {
                        alert({/literal}'{$btr->sviat_np_popular_cities_settings_error|escape}'{literal});
                    }
                });
            });

            $(document).on('click', '.fn_update_cities', function() {
                var button = $(this);
                var progressBlock = $('.fn_progress_block');

                if (button.hasClass('updating')) {
                    return;
                }

                button.addClass('updating').prop('disabled', true);
                button.find('span').text({/literal}'{$btr->sviat_np_popular_cities_updating|escape}'{literal});

                progressBlock.html('');

                var progressItem = $(
                    '<progress class="progress progress-xs progress-info" value="0" max="100">0%</progress>'
                    );
                var progressContainer = $(
                    '<div class="mb-2"><p class="mb-1">' + {/literal}'{$btr->sviat_np_popular_cities_update_progress|escape}'{literal} + '</p></div>');
                var resultBlock = $('<div class="np_update_result" style="display: none"></div>');

                progressItem.appendTo(progressContainer);
                resultBlock.appendTo(progressContainer);
                progressContainer.appendTo(progressBlock);

                var progressValue = 0;
                var progressInterval = setInterval(function() {
                    progressValue += 2;
                    if (progressValue > 90) {
                        progressValue = 90;
                    }
                    progressItem.attr('value', progressValue);
                }, 200);
                $.ajax({
                    url: "/backend/index.php?controller=Sviat.NovaPoshtaPopularCities.NPPopularCitiesAdmin@updateCitiesAjax",
                    type: 'POST',
                    dataType: 'json',
                    success: function(data) {
                        clearInterval(progressInterval);
                        progressItem.attr('value', 100);

                        setTimeout(function() {
                            progressItem.hide();

                            if (data.success) {
                                resultBlock
                                    .text({/literal}'{$btr->sviat_np_popular_cities_update_success|escape}'{literal})
                                    .addClass('alert alert--success')
                                    .css('padding', '5px')
                                    .fadeIn(500);

                                button.find('span').text({/literal}'{$btr->sviat_np_popular_cities_update_success|escape}'{literal});
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            } else {
                                resultBlock
                                    .text(data.error || {/literal}'{$btr->sviat_np_popular_cities_update_error|escape}'{literal})
                                    .addClass('alert alert--error')
                                    .css('padding', '5px')
                                    .fadeIn(500);

                                button.find('span').text({/literal}'{$btr->sviat_np_popular_cities_update_button|escape}'{literal});
                                button.removeClass('updating').prop('disabled', false);
                            }
                        }, 300);
                    },
                    error: function(xhr, status, errorThrown) {
                        clearInterval(progressInterval);
                        progressItem.attr('value', 100).hide();

                        setTimeout(function() {
                            resultBlock
                                .text({/literal}'{$btr->sviat_np_popular_cities_error_prefix|escape}'{literal} + errorThrown)
                                .addClass('alert alert--error')
                                .css('padding', '5px')
                                .fadeIn(500);

                            button.find('span').text({/literal}'{$btr->sviat_np_popular_cities_update_button|escape}'{literal});
                            button.removeClass('updating').prop('disabled', false);
                        }, 300);
                    }
                });
            });
        });
    </script>
{/literal}
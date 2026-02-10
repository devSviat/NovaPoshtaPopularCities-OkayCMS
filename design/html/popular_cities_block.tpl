{if $delivery->module_id == $np_delivery_module_id && $np_popular_cities}
    <div class="np_popular_cities" data-enable-ip-detection="{$np_enable_ip_detection|default:0}">
        <div class="np_popular_cities__list">
            {foreach $np_popular_cities as $city}
                {if !empty($city->city_ref)}
                <button type="button" 
                        class="np_popular_city__btn" 
                        data-city-ref="{$city->city_ref|escape}"
                        data-city-name="{$city->name|escape}">
                    {$city->name|escape}
                </button>
                {/if}
            {/foreach}
        </div>
    </div>
{/if}

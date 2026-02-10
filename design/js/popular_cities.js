(function() {
    'use strict';
    
    function initPopularCitiesScript() {
        var $ = window.jQuery || window.$;
        
        if (!$) {
            setTimeout(initPopularCitiesScript, 100);
            return;
        }
        
        function updateActiveCityButton() {
            var activeDelivery = $('input[name="delivery_id"]:checked');
            if (!activeDelivery.length) {
                $('.np_popular_city__btn').removeClass('active');
                return;
            }
            
            var deliveryItem = activeDelivery.closest('.delivery__item');
            var deliveryBlock = deliveryItem.find('.fn_delivery_novaposhta');
            
            if (!deliveryBlock.length) {
                $('.np_popular_city__btn').removeClass('active');
                return;
            }
            
            var cityIdInput = deliveryBlock.find('input[name="novaposhta_delivery_city_id"]');
            if (!cityIdInput.length) {
                cityIdInput = deliveryItem.find('input[name="novaposhta_delivery_city_id"]');
            }
            
            var selectedCityRef = cityIdInput.val();
            
            $('.np_popular_city__btn').each(function() {
                var $btn = $(this);
                var cityRef = $btn.data('city-ref');
                
                if (cityRef && cityRef === selectedCityRef) {
                    $btn.addClass('active');
                } else {
                    $btn.removeClass('active');
                }
            });
        }
        
        function getClientIp(callback) {
            $.ajax({
                url: 'https://api.ipify.org?format=json',
                type: 'GET',
                dataType: 'json',
                timeout: 5000,
                success: function(data) {
                    var clientIp = data.ip;
                    if (callback) {
                        callback(clientIp);
                    }
                },
                error: function(xhr, status, error) {
                    $.ajax({
                        url: 'https://api64.ipify.org?format=json',
                        type: 'GET',
                        dataType: 'json',
                        timeout: 5000,
                        success: function(data) {
                            var clientIp = data.ip;
                            if (callback) {
                                callback(clientIp);
                            }
                        },
                        error: function() {
                            console.error('[NovaPoshtaPopularCities] getClientIp: Failed to get client IP');
                            if (callback) {
                                callback(null);
                            }
                        }
                    });
                }
            });
        }
        
        function getClientIp(callback) {
            $.ajax({
                url: 'https://api.ipify.org?format=json',
                type: 'GET',
                dataType: 'json',
                timeout: 5000,
                success: function(data) {
                    var clientIp = data.ip;
                    if (callback) {
                        callback(clientIp);
                    }
                },
                error: function(xhr, status, error) {
                    $.ajax({
                        url: 'https://api64.ipify.org?format=json',
                        type: 'GET',
                        dataType: 'json',
                        timeout: 5000,
                        success: function(data) {
                            var clientIp = data.ip;
                            if (callback) {
                                callback(clientIp);
                            }
                        },
                        error: function() {
                            console.error('[NovaPoshtaPopularCities] getClientIp: Failed to get client IP');
                            if (callback) {
                                callback(null);
                            }
                        }
                    });
                }
            });
        }
        
        function getCityByIpAndAddToList() {
            var $popularCitiesBlock = $('.np_popular_cities');
            if (!$popularCitiesBlock.length) {
                return;
            }
            
            var enableIpDetection = $popularCitiesBlock.data('enable-ip-detection');
            if (!enableIpDetection || enableIpDetection == 0) {
                return;
            }
            
            var $popularCitiesList = $popularCitiesBlock.find('.np_popular_cities__list');
            if (!$popularCitiesList.length) {
                return;
            }
            
            if (!okay || !okay.router || !okay.router['Sviat_NovaPoshtaPopularCities_get_city_by_ip']) {
                console.error('[NovaPoshtaPopularCities] Route not found');
                return;
            }
            
            var apiUrl = okay.router['Sviat_NovaPoshtaPopularCities_get_city_by_ip'];
            
            var existingCityByIp = $popularCitiesList.find('.np_popular_city__btn[data-city-by-ip="true"]');
            if (existingCityByIp.length > 0) {
                existingCityByIp.first().prependTo($popularCitiesList);
                return;
            }
            
            var savedCityRef = localStorage.getItem('np_city_by_ip_ref');
            var savedCityName = localStorage.getItem('np_city_by_ip_name');
            
            if (savedCityRef && savedCityName) {
                var savedCityInList = $popularCitiesList.find('.np_popular_city__btn[data-city-ref="' + savedCityRef + '"]');
                if (savedCityInList.length > 0) {
                    savedCityInList.first().prependTo($popularCitiesList);
                    savedCityInList.first().attr('data-city-by-ip', 'true');
                    return;
                }
            }
            
            getClientIp(function(clientIp) {
                if (!clientIp) {
                    console.error('[NovaPoshtaPopularCities] Could not get client IP');
                    return;
                }
                
                $.ajax({
                    url: apiUrl,
                    type: 'GET',
                    data: {
                        ip: clientIp
                    },
                    dataType: 'json',
                    timeout: 10000,
                    success: function(response) {
                        if (response && response.success && response.city) {
                            var cityRef = response.city.ref;
                            var cityName = response.city.name;
                            
                            var existingCity = $popularCitiesList.find('.np_popular_city__btn[data-city-ref="' + cityRef + '"]');
                            if (existingCity.length > 0) {
                                existingCity.first().prependTo($popularCitiesList);
                                existingCity.first().attr('data-city-by-ip', 'true');
                                localStorage.setItem('np_city_by_ip_ref', cityRef);
                                localStorage.setItem('np_city_by_ip_name', cityName);
                            } else {
                                var $newCityBtn = $('<button>')
                                    .attr('type', 'button')
                                    .addClass('np_popular_city__btn')
                                    .attr('data-city-ref', cityRef)
                                    .attr('data-city-name', cityName)
                                    .attr('data-city-by-ip', 'true')
                                    .text(cityName);
                                
                                $popularCitiesList.prepend($newCityBtn);
                                localStorage.setItem('np_city_by_ip_ref', cityRef);
                                localStorage.setItem('np_city_by_ip_name', cityName);
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('[NovaPoshtaPopularCities] AJAX error: ' + error);
                    }
                });
            });
        }
        
        $(document).ready(function() {
            updateActiveCityButton();
            
            setTimeout(function() {
                getCityByIpAndAddToList();
            }, 500);
            
            $(document).on('change', 'input[name="novaposhta_delivery_city_id"]', function() {
                updateActiveCityButton();
            });
            
            $(document).on('change', 'input[name="delivery_id"]', function() {
                setTimeout(updateActiveCityButton, 100);
                setTimeout(getCityByIpAndAddToList, 200);
            });
            
            $(document).on('click', '.np_popular_city__btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var cityRef = $(this).data('city-ref');
                var cityName = $(this).data('city-name');
                
                if (!cityRef || !cityName) {
                    return false;
                }
                
                var activeDelivery = $('input[name="delivery_id"]:checked');
                if (!activeDelivery.length) {
                    return false;
                }
                
                var deliveryItem = activeDelivery.closest('.delivery__item');
                var deliveryBlock = deliveryItem.find('.fn_delivery_novaposhta');
                
                if (!deliveryBlock.length) {
                    return false;
                }
                
                var cityInput = deliveryBlock.find('input.city_novaposhta');
                if (!cityInput.length) {
                    cityInput = deliveryBlock.find('input[name="novaposhta_city"]');
                }
                
                if (!cityInput.length) {
                    return false;
                }
                
                var cityIdInput = deliveryBlock.find('input[name="novaposhta_delivery_city_id"]');
                if (!cityIdInput.length) {
                    cityIdInput = deliveryItem.find('input[name="novaposhta_delivery_city_id"]');
                }
                
                if (!cityIdInput.length) {
                    return false;
                }
                
                var selectedWarehouseRef = deliveryBlock.find('input[name="novaposhta_delivery_warehouse_id"]').val();
                
                var deliveryTypesBlock = deliveryBlock.find('.np_delivery_types_block');
                var deliveryTypesHeading = deliveryTypesBlock.find('.np_delivery_types_heading');
                var selectedDeliveryType = null;
                if (deliveryTypesHeading.length) {
                    var activeTypeButton = deliveryTypesHeading.find('a.active');
                    if (activeTypeButton.length) {
                        selectedDeliveryType = activeTypeButton.data('delivery_type');
                    }
                }
                
                cityInput.val(cityName);
                cityInput.removeClass('error').attr('aria-invalid', 'false');
                cityInput.closest('.form__group').find('.error').remove();
                cityInput.closest('.form__group').addClass('filled');
                $("label[for='novaposhta_city']").hide();
                $("label#novaposhta_city-error").hide();
                
                cityIdInput.val(cityRef).trigger('change');
                updateActiveCityButton();
                
                if (selectedDeliveryType) {
                    var checkInterval = setInterval(function() {
                        var newDeliveryTypesHeading = deliveryBlock.find('.np_delivery_types_heading');
                        var newDeliveryTypesContent = deliveryBlock.find('.np_delivery_types_content');
                        
                        if (newDeliveryTypesHeading.length && newDeliveryTypesHeading.children().length > 0) {
                            clearInterval(checkInterval);
                            
                            var typeButton = newDeliveryTypesHeading.find('a[data-delivery_type="' + selectedDeliveryType + '"]');
                            if (typeButton.length) {
                                var typeContent = newDeliveryTypesContent.find('.' + selectedDeliveryType);
                                if (typeContent.length) {
                                    if (selectedWarehouseRef) {
                                        var warehouseOption = typeContent.find('select option[data-warehouse_ref="' + selectedWarehouseRef + '"]');
                                        if (warehouseOption.length) {
                                            typeButton.trigger('click');
                                        }
                                    } else {
                                        typeButton.trigger('click');
                                    }
                                }
                            }
                        }
                    }, 100);
                    
                    setTimeout(function() {
                        clearInterval(checkInterval);
                    }, 3000);
                }
                
                return false;
            });
        });
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(initPopularCitiesScript, 200);
        });
    } else {
        setTimeout(initPopularCitiesScript, 200);
    }
})();

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
        
        $(document).ready(function() {
            updateActiveCityButton();
            
            $(document).on('change', 'input[name="novaposhta_delivery_city_id"]', function() {
                updateActiveCityButton();
            });
            
            $(document).on('change', 'input[name="delivery_id"]', function() {
                setTimeout(updateActiveCityButton, 100);
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

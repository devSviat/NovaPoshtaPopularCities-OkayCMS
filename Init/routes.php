<?php

namespace Okay\Modules\Sviat\NovaPoshtaPopularCities;

return [
    'Sviat_NovaPoshtaPopularCities_find_city' => [
        'slug' => 'ajax/np/popular_cities/find_city',
        'to_front' => true,
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\NPPopularCitiesSearchController',
            'method' => 'findCity',
        ],
    ],
    'Sviat_NovaPoshtaPopularCities_get_city_by_ip' => [
        'slug' => 'ajax/np/popular_cities/get_city_by_ip',
        'to_front' => true,
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\GetCityByIpController',
            'method' => 'getCityByIp',
        ],
    ],
    'Sviat_NovaPoshtaPopularCities_update_cities' => [
        'slug' => 'backend/np/popular_cities/update_cities',
        'to_front' => true,
        'params' => [
            'controller' => __NAMESPACE__ . '\Backend\Controllers\NPPopularCitiesAdmin',
            'method' => 'updateCitiesAjax',
        ],
    ],
];

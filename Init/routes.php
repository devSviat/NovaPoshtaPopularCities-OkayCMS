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
    // Маршрут backend/np/popular_cities/update_cities прибрано: він вів через
    // вітрину на NPPopularCitiesAdmin (нащадок IndexAdmin), якого контейнер там
    // не збирає — конструктор чекає на $manager від backend/index.php. Кнопка
    // «оновити міста» і так ходить на
    // /backend/index.php?controller=Sviat.NovaPoshtaPopularCities.NPPopularCitiesAdmin@updateCitiesAjax,
    // тобто через авторизований вхід адмінки.
];

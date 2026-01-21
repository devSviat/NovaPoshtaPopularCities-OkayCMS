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
];

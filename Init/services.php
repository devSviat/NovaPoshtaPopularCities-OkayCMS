<?php


namespace Okay\Modules\Sviat\NovaPoshtaPopularCities;

use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Modules\Sviat\NovaPoshtaPopularCities\Extenders\FrontExtender;

return [
    FrontExtender::class => [
        'class' => FrontExtender::class,
        'arguments' => [
            new SR(EntityFactory::class),
            new SR(Design::class),
        ],
    ],
];

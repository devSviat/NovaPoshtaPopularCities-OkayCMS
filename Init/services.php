<?php


namespace Okay\Modules\Sviat\NovaPoshtaPopularCities;

use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\Settings;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Modules\Sviat\NovaPoshtaPopularCities\Extenders\FrontExtender;
use Okay\Modules\Sviat\NovaPoshtaPopularCities\Helpers\NPCitiesApiHelper;
use Psr\Log\LoggerInterface;

return [
    FrontExtender::class => [
        'class' => FrontExtender::class,
        'arguments' => [
            new SR(EntityFactory::class),
            new SR(Design::class),
            new SR(Settings::class),
        ],
    ],
    NPCitiesApiHelper::class => [
        'class' => NPCitiesApiHelper::class,
        'arguments' => [
            new SR(Settings::class),
            new SR(LoggerInterface::class),
        ],
    ],
];

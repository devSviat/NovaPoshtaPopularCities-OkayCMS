<?php


namespace Okay\Modules\Sviat\NovaPoshtaPopularCities\Init;


use Okay\Core\Modules\AbstractInit;
use Okay\Core\Modules\EntityField;
use Okay\Helpers\DeliveriesHelper;
use Okay\Modules\Sviat\NovaPoshtaPopularCities\Entities\NPPopularCitiesEntity;
use Okay\Modules\Sviat\NovaPoshtaPopularCities\Entities\NPSettlementsEntity;
use Okay\Modules\Sviat\NovaPoshtaPopularCities\Extenders\FrontExtender;

class Init extends AbstractInit
{
    const PERMISSION = 'sviat__novaposhta_popular_cities';

    public function install()
    {
        $this->setBackendMainController('NPPopularCitiesAdmin');

        $this->migrateEntityTable(NPPopularCitiesEntity::class, [
            (new EntityField('id'))->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
            (new EntityField('city_ref'))->setTypeVarchar(255)->setIndex(),
            (new EntityField('position'))->setTypeInt(11)->setDefault(0)->setIndex(),
        ]);

        $this->migrateEntityTable(NPSettlementsEntity::class, [
            (new EntityField('id'))->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
            (new EntityField('ref'))->setTypeVarchar(255)->setIndex(),
            (new EntityField('city_ref'))->setTypeVarchar(255)->setIndex(),
            (new EntityField('city_name'))->setTypeVarchar(255)->setIndex(),
            (new EntityField('city_translit'))->setTypeVarchar(255)->setNullable(),
            (new EntityField('updated_at'))->setTypeDateTime()->setNullable(),
        ]);
    }

        public function init()
    {
        $this->addPermission(self::PERMISSION);
        $this->registerBackendController('NPPopularCitiesAdmin');
        $this->addBackendControllerPermission('NPPopularCitiesAdmin', self::PERMISSION);

        $this->registerQueueExtension(
            [DeliveriesHelper::class, 'getCartDeliveriesList'],
            [FrontExtender::class, 'assignPopularCities']
        );
        
        $this->addFrontBlock('front_cart_delivery', 'popular_cities_block.tpl');
    }
}

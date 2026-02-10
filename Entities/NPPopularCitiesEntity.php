<?php


namespace Okay\Modules\Sviat\NovaPoshtaPopularCities\Entities;


use Okay\Core\Entity\Entity;

class NPPopularCitiesEntity extends Entity
{
    protected static $fields = [
        'id',
        'city_ref',
        'position',
    ];

    protected static $table = 'sviat__np_popular_cities';
    protected static $tableAlias = 'nppc';

    protected static $defaultOrderFields = [
        'position ASC',
        'id ASC'
    ];

    public function findWithNames()
    {
        $popularCities = $this->find();
        if (empty($popularCities)) {
            return [];
        }
        
        $SL = \Okay\Core\ServiceLocator::getInstance();
        $entityFactory = $SL->getService(\Okay\Core\EntityFactory::class);
        $settlementsEntity = $entityFactory->get(\Okay\Modules\Sviat\NovaPoshtaPopularCities\Entities\NPSettlementsEntity::class);
        
        $cityRefs = [];
        foreach ($popularCities as $popularCity) {
            if (!empty($popularCity->city_ref)) {
                $cityRefs[] = $popularCity->city_ref;
            }
        }
        
        if (empty($cityRefs)) {
            return [];
        }
        
        $settlements = $settlementsEntity->find(['city_ref' => $cityRefs]);
        
        $settlementsMap = [];
        foreach ($settlements as $settlement) {
            if (!empty($settlement->city_ref)) {
                $settlementsMap[$settlement->city_ref] = $settlement;
            }
        }
        
        $result = [];
        foreach ($popularCities as $popularCity) {
            if (empty($popularCity->city_ref)) {
                continue;
            }
            
            if (isset($settlementsMap[$popularCity->city_ref])) {
                $settlement = $settlementsMap[$popularCity->city_ref];
                $popularCity->name = $settlement->city_name;
                $popularCity->ref = $settlement->ref;
                $result[] = $popularCity;
            }
        }
        
        return $result;
    }
}

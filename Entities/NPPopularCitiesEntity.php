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
        $npCitiesEntity = $entityFactory->get(\Okay\Modules\OkayCMS\NovaposhtaCost\Entities\NPCitiesEntity::class);
        
        $cityRefs = [];
        foreach ($popularCities as $popularCity) {
            $cityRefs[] = $popularCity->city_ref;
        }
        
        $cities = $npCitiesEntity->mappedBy('ref')->find(['ref' => $cityRefs]);
        
        $result = [];
        foreach ($popularCities as $popularCity) {
            if (isset($cities[$popularCity->city_ref])) {
                $city = $cities[$popularCity->city_ref];
                $popularCity->name = $city->name;
                $popularCity->ref = $city->ref;
                $result[] = $popularCity;
            }
        }
        
        return $result;
    }
}

<?php


namespace Okay\Modules\Sviat\NovaPoshtaPopularCities\Entities;


use Okay\Core\Entity\Entity;

class NPSettlementsEntity extends Entity
{
    protected static $fields = [
        'id',
        'ref',
        'city_ref',
        'city_name',
        'city_translit',
        'updated_at',
    ];

    protected static $table = 'sviat__np_settlements';
    protected static $tableAlias = 'nps';

    protected static $defaultOrderFields = [
        'city_name ASC',
        'id ASC'
    ];

    protected static $searchFields = [
        'city_name',
        'city_translit'
    ];

    public function add($object)
    {
        $object = (object)$object;
        $object->updated_at = 'NOW()';
        return parent::add($object);
    }

    public function update($ids, $object)
    {
        $object = (object)$object;
        $object->updated_at = 'NOW()';
        parent::update($ids, $object);
    }

    public function filter__keyword($keywords)
    {
        $keywords = (array)$keywords;

        $searchFields = $this->getSearchFields();
        foreach ($keywords as $keyNum => $keyword) {
            $keywordFilter = [];
            foreach ($searchFields as $searchField) {
                $keywordFilter[] = "{$searchField} LIKE :keyword_{$searchField}_{$keyNum}";
                $this->select->bindValue("keyword_{$searchField}_{$keyNum}", $keyword . '%');
            }
            $this->select->where('(' . implode(' OR ', $keywordFilter) . ')');
        }
    }

    public function filter__city_translit($cityTranslit)
    {
        $this->select->where('city_translit = :city_translit');
        $this->select->bindValue('city_translit', $cityTranslit);
    }

    public function filter__city_ref($cityRefs)
    {
        $cityRefs = (array)$cityRefs;
        if (!empty($cityRefs)) {
            $this->select->where('city_ref IN (:city_refs)');
            $this->select->bindValue('city_refs', $cityRefs);
        }
    }
}

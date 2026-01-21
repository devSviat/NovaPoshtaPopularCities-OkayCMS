<?php


namespace Okay\Modules\Sviat\NovaPoshtaPopularCities\Backend\Controllers;


use Okay\Admin\Controllers\IndexAdmin;
use Okay\Core\EntityFactory;
use Okay\Core\Modules\Module;
use Okay\Core\ServiceLocator;
use Okay\Modules\OkayCMS\NovaposhtaCost\Entities\NPCitiesEntity;
use Okay\Modules\Sviat\NovaPoshtaPopularCities\Entities\NPPopularCitiesEntity;

class NPPopularCitiesAdmin extends IndexAdmin
{
    public function fetch(
        NPPopularCitiesEntity $popularCitiesEntity,
        EntityFactory $entityFactory
    ) {
        $SL = ServiceLocator::getInstance();
        /** @var Module $module */
        $module = $SL->getService(Module::class);
        
        /** @var NPCitiesEntity $npCitiesEntity */
        $npCitiesEntity = $entityFactory->get(NPCitiesEntity::class);
        
        if ($this->request->method('post')) {
            $addCity = $this->request->post('add_city');
            if ($addCity !== null) {
                $cityRef = $this->request->post('city_ref');
                $cityName = $this->request->post('newpost_city_name');
                
                if (empty($cityRef) && !empty($cityName)) {
                    $city = $npCitiesEntity->findOne(['name' => $cityName]);
                    if ($city) {
                        $cityRef = $city->ref;
                    }
                }
                
                if (!empty($cityRef)) {
                    $existing = $popularCitiesEntity->findOne(['city_ref' => $cityRef]);
                    if (!$existing) {
                        $allCities = $popularCitiesEntity->order('position DESC')->find();
                        $maxPosition = 0;
                        if (!empty($allCities)) {
                            $maxPosition = (int)$allCities[0]->position;
                        }
                        $position = $maxPosition + 1;
                        
                        $result = $popularCitiesEntity->add([
                            'city_ref' => $cityRef,
                            'position' => $position,
                        ]);
                        
                        if ($result) {
                            $this->design->assign('success_message', 'Місто успішно додано');
                        }
                    } else {
                        $this->design->assign('error_message', 'Це місто вже додано до списку');
                    }
                } else {
                    $this->design->assign('error_message', 'Не вдалося знайти місто. Будь ласка, виберіть місто зі списку');
                }
            }
            
            $ids = $this->request->post('check');
            if (is_array($ids) && $this->request->post('action') == 'delete') {
                $popularCitiesEntity->delete($ids);
            }
            
            $positions = $this->request->post('positions');
            if (!empty($positions) && is_array($positions)) {
                foreach($positions as $id => $position) {
                    $popularCitiesEntity->update((int)$id, ['position' => (int)$position]);
                }
            }
        }

        $popularCities = $popularCitiesEntity->findWithNames();
        $this->design->assign('popular_cities', $popularCities);
        
        $allCities = $npCitiesEntity->order('name')->find();
        $this->design->assign('all_cities', $allCities);
        
        $addedRefs = [];
        foreach ($popularCities as $popularCity) {
            $addedRefs[] = $popularCity->city_ref;
        }
        $this->design->assign('added_city_refs', $addedRefs);

        $this->response->setContent($this->design->fetch('novaposhta_popular_cities.tpl'));
    }
}

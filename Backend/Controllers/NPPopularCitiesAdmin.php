<?php


namespace Okay\Modules\Sviat\NovaPoshtaPopularCities\Backend\Controllers;


use Okay\Admin\Controllers\IndexAdmin;
use Okay\Core\EntityFactory;
use Okay\Core\Modules\Module;
use Okay\Core\ServiceLocator;
use Okay\Modules\OkayCMS\NovaposhtaCost\Entities\NPCitiesEntity;
use Okay\Modules\Sviat\NovaPoshtaPopularCities\Entities\NPPopularCitiesEntity;
use Okay\Modules\Sviat\NovaPoshtaPopularCities\Entities\NPSettlementsEntity;
use Okay\Modules\Sviat\NovaPoshtaPopularCities\Helpers\CityNameMatcher;
use Okay\Modules\Sviat\NovaPoshtaPopularCities\Helpers\NPCitiesApiHelper;

class NPPopularCitiesAdmin extends IndexAdmin
{
    public function fetch(
        NPPopularCitiesEntity $popularCitiesEntity,
        EntityFactory $entityFactory,
        NPCitiesApiHelper $apiHelper
    ) {
        $SL = ServiceLocator::getInstance();
        /** @var Module $module */
        $module = $SL->getService(Module::class);
        
        /** @var NPCitiesEntity $npCitiesEntity */
        $npCitiesEntity = $entityFactory->get(NPCitiesEntity::class);
        
        /** @var NPSettlementsEntity $settlementsEntity */
        $settlementsEntity = $entityFactory->get(NPSettlementsEntity::class);
        
        if ($this->request->method('post')) {
            $updateCities = $this->request->post('update_cities');
            if ($updateCities !== null) {
                $this->updateCitiesFromApi($apiHelper, $settlementsEntity);
            }
            
            $saveSettings = $this->request->post('save_settings');
            if ($saveSettings !== null) {
                if ($this->request->post('sviat__np_popular_cities__enable_ip_detection', 'integer')) {
                    $this->settings->set('sviat__np_popular_cities__enable_ip_detection', 1);
                } else {
                    $this->settings->set('sviat__np_popular_cities__enable_ip_detection', 0);
                }
                $this->design->assign('success_message', 'Налаштування збережено');
            }
            
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
        
        $enableIpDetection = $this->settings->get('sviat__np_popular_cities__enable_ip_detection');
        $this->design->assign('enable_ip_detection', $enableIpDetection);

        $this->response->setContent($this->design->fetch('novaposhta_popular_cities.tpl'));
    }

    private function updateCitiesFromApi(NPCitiesApiHelper $apiHelper, NPSettlementsEntity $settlementsEntity): void
    {
        try {
            $limit = 500;
            $allSettlements = $apiHelper->getAllCitySettlements($limit);

            if (empty($allSettlements)) {
                $this->design->assign('error_message', 'Не вдалося отримати міста з API (getSettlements). ' . $apiHelper->getLastCallError());
                return;
            }

            $settlementsMap = [];
            foreach ($allSettlements as $settlement) {
                if (!empty($settlement['ref'])) {
                    $settlementsMap[$settlement['ref']] = $settlement;
                }
            }

            $cityRefMap = $apiHelper->getCityRefsByName($limit);

            $savedCount = 0;
            $updatedCount = 0;
            
            foreach ($settlementsMap as $ref => $settlement) {
                $cityName = $settlement['city_name'] ?? '';
                $cityTranslit = $settlement['city_translit'] ?? '';

                if (empty($ref) || empty($cityName)) {
                    continue;
                }

                // Якщо getSettlements уже повернув DeliveryCity — це
                // найточніший ref для доставки. Далі — зіставлення за назвою,
                // і аж тоді окремий запит: кожне таке місто коштує виклику
                // до API, а їх у цьому запиті й так під сотню.
                $cityRef = $settlement['city_ref'] ?? '';
                if (empty($cityRef)) {
                    $cityRef = $cityRefMap[CityNameMatcher::directoryNameKey($cityName)] ?? '';
                }
                if (empty($cityRef)) {
                    $cityRef = $apiHelper->getCityRefByName($cityName);
                }

                if (empty($cityRef)) {
                    continue;
                }

                $existing = $settlementsEntity->findOne(['ref' => $ref]);
                
                $data = [
                    'ref' => $ref,
                    'city_ref' => $cityRef,
                    'city_name' => $cityName,
                    'city_translit' => $cityTranslit,
                ];

                if ($existing) {
                    $settlementsEntity->update($existing->id, $data);
                    $updatedCount++;
                } else {
                    $settlementsEntity->add($data);
                    $savedCount++;
                }
            }

            $message = sprintf(
                'Міста успішно оновлено. Додано: %d, Оновлено: %d',
                $savedCount,
                $updatedCount
            );
            $this->design->assign('success_message', $message);

        } catch (\Exception $e) {
            $this->design->assign('error_message', 'Помилка при оновленні міст: ' . $e->getMessage());
        }
    }

    public function updateCitiesAjax(
        EntityFactory $entityFactory,
        NPCitiesApiHelper $apiHelper
    ) {
        /** @var NPSettlementsEntity $settlementsEntity */
        $settlementsEntity = $entityFactory->get(NPSettlementsEntity::class);
        
        try {
            $limit = 500;
            $allSettlements = $apiHelper->getAllCitySettlements($limit);

            if (empty($allSettlements)) {
                return $this->response->setContent(json_encode([
                    'success' => false,
                    'error' => 'Не вдалося отримати міста з API (getSettlements). ' . $apiHelper->getLastCallError()
                ]), RESPONSE_JSON);
            }

            $settlementsMap = [];
            foreach ($allSettlements as $settlement) {
                if (!empty($settlement['ref'])) {
                    $settlementsMap[$settlement['ref']] = $settlement;
                }
            }

            $cityRefMap = $apiHelper->getCityRefsByName($limit);

            $savedCount = 0;
            $updatedCount = 0;
            
            foreach ($settlementsMap as $ref => $settlement) {
                $cityName = $settlement['city_name'] ?? '';
                $cityTranslit = $settlement['city_translit'] ?? '';

                if (empty($ref) || empty($cityName)) {
                    continue;
                }

                // Якщо getSettlements уже повернув DeliveryCity — це
                // найточніший ref для доставки. Далі — зіставлення за назвою,
                // і аж тоді окремий запит: кожне таке місто коштує виклику
                // до API, а їх у цьому запиті й так під сотню.
                $cityRef = $settlement['city_ref'] ?? '';
                if (empty($cityRef)) {
                    $cityRef = $cityRefMap[CityNameMatcher::directoryNameKey($cityName)] ?? '';
                }
                if (empty($cityRef)) {
                    $cityRef = $apiHelper->getCityRefByName($cityName);
                }

                if (empty($cityRef)) {
                    continue;
                }

                $existing = $settlementsEntity->findOne(['ref' => $ref]);
                
                $data = [
                    'ref' => $ref,
                    'city_ref' => $cityRef,
                    'city_name' => $cityName,
                    'city_translit' => $cityTranslit,
                ];

                if ($existing) {
                    $settlementsEntity->update($existing->id, $data);
                    $updatedCount++;
                } else {
                    $settlementsEntity->add($data);
                    $savedCount++;
                }
            }

            return $this->response->setContent(json_encode([
                'success' => true,
                'message' => 'Міста успішно оновлено'
            ]), RESPONSE_JSON);

        } catch (\Exception $e) {
            return $this->response->setContent(json_encode([
                'success' => false,
                'error' => 'Помилка при оновленні міст: ' . $e->getMessage()
            ]), RESPONSE_JSON);
        }
    }
}

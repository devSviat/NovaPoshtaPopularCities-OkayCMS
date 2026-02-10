<?php


namespace Okay\Modules\Sviat\NovaPoshtaPopularCities\Backend\Controllers;


use Okay\Admin\Controllers\IndexAdmin;
use Okay\Core\EntityFactory;
use Okay\Core\Modules\Module;
use Okay\Core\ServiceLocator;
use Okay\Modules\OkayCMS\NovaposhtaCost\Entities\NPCitiesEntity;
use Okay\Modules\Sviat\NovaPoshtaPopularCities\Entities\NPPopularCitiesEntity;
use Okay\Modules\Sviat\NovaPoshtaPopularCities\Entities\NPSettlementsEntity;
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
            $allSettlements = [];
            $page = 1;
            $limit = 500;
            $maxPages = 200;
            
            do {
                $settlements = $apiHelper->getSettlements($page, $limit);
                if (!empty($settlements)) {
                    $allSettlements = array_merge($allSettlements, $settlements);
                    
                    if (count($settlements) < $limit) {
                        $nextSettlements = $apiHelper->getSettlements($page + 1, $limit);
                        if (empty($nextSettlements) || count($nextSettlements) === 0) {
                            break;
                        }
                    }
                    $page++;
                } else {
                    break;
                }
            } while ($page <= $maxPages);

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

            $cityRefMap = [];
            $citiesPage = 1;
            
            do {
                $cities = $apiHelper->getCities($citiesPage, $limit);
                if (!empty($cities)) {
                    foreach ($cities as $city) {
                        if (!empty($city['city_name']) && !empty($city['city_ref'])) {
                            $cityName = mb_strtolower(trim($city['city_name']));
                            if (!isset($cityRefMap[$cityName])) {
                                $cityRefMap[$cityName] = $city['city_ref'];
                            }
                        }
                    }
                    $citiesPage++;
                } else {
                    break;
                }
            } while (count($cities) >= $limit);

            $savedCount = 0;
            $updatedCount = 0;
            $processedCities = [];
            
            foreach ($settlementsMap as $ref => $settlement) {
                $cityName = $settlement['city_name'] ?? '';
                $cityTranslit = $settlement['city_translit'] ?? '';

                if (empty($ref) || empty($cityName)) {
                    continue;
                }

                $cityNameLower = mb_strtolower(trim($cityName));
                if (isset($processedCities[$cityNameLower])) {
                    continue;
                }
                $processedCities[$cityNameLower] = true;

                $cityRef = $cityRefMap[$cityNameLower] ?? '';
                
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
            $allSettlements = [];
            $page = 1;
            $limit = 500;
            $maxPages = 200;
            
            do {
                $settlements = $apiHelper->getSettlements($page, $limit);
                if (!empty($settlements)) {
                    $allSettlements = array_merge($allSettlements, $settlements);
                    
                    if (count($settlements) < $limit) {
                        $nextSettlements = $apiHelper->getSettlements($page + 1, $limit);
                        if (empty($nextSettlements) || count($nextSettlements) === 0) {
                            break;
                        }
                    }
                    $page++;
                } else {
                    break;
                }
            } while ($page <= $maxPages);

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

            $cityRefMap = [];
            $citiesPage = 1;
            
            do {
                $cities = $apiHelper->getCities($citiesPage, $limit);
                if (!empty($cities)) {
                    foreach ($cities as $city) {
                        if (!empty($city['city_name']) && !empty($city['city_ref'])) {
                            $cityName = mb_strtolower(trim($city['city_name']));
                            if (!isset($cityRefMap[$cityName])) {
                                $cityRefMap[$cityName] = $city['city_ref'];
                            }
                        }
                    }
                    $citiesPage++;
                } else {
                    break;
                }
            } while (count($cities) >= $limit);

            $savedCount = 0;
            $updatedCount = 0;
            $processedCities = [];
            
            foreach ($settlementsMap as $ref => $settlement) {
                $cityName = $settlement['city_name'] ?? '';
                $cityTranslit = $settlement['city_translit'] ?? '';

                if (empty($ref) || empty($cityName)) {
                    continue;
                }

                $cityNameLower = mb_strtolower(trim($cityName));
                if (isset($processedCities[$cityNameLower])) {
                    continue;
                }
                $processedCities[$cityNameLower] = true;

                $cityRef = $cityRefMap[$cityNameLower] ?? '';
                
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

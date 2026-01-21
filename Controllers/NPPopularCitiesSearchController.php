<?php


namespace Okay\Modules\Sviat\NovaPoshtaPopularCities\Controllers;


use Okay\Core\Request;
use Okay\Core\Response;
use Okay\Modules\OkayCMS\NovaposhtaCost\Entities\NPCitiesEntity;

class NPPopularCitiesSearchController
{
    public function findCity(
        Request $request,
        Response $response,
        NPCitiesEntity $citiesEntity
    ) {
        $filter['keyword'] = $request->get('query');
        $filter['limit'] = 25;
        
        $cities = $citiesEntity->find($filter);

        $suggestions = [];
        if (!empty($cities)) {
            foreach ($cities as $city) {
                $suggestion = new \stdClass();

                $suggestion->value = $city->name;
                $suggestion->data = (object)[
                    'id' => $city->id,
                    'ref' => $city->ref,
                    'name' => $city->name,
                ];
                $suggestions[] = $suggestion;
            }
        }

        $res = new \stdClass;
        $res->query = $filter['keyword'];
        $res->suggestions = $suggestions;

        $response->setContent(json_encode($res), RESPONSE_JSON);
    }
}

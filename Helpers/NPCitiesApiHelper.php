<?php


namespace Okay\Modules\Sviat\NovaPoshtaPopularCities\Helpers;


use Okay\Core\Settings;
use Psr\Log\LoggerInterface;

class NPCitiesApiHelper
{
    private string $lastCallError = '';

    /**
     * Скільки записів віддала остання сторінка getSettlements ДО відсіву сіл.
     * Саме за цим числом видно, чи є ще сторінки: відфільтрований результат
     * майже завжди менший за ліміт, бо міст серед населених пунктів меншість.
     */
    private int $lastSettlementsPageSize = 0;
    private Settings $settings;
    private LoggerInterface $logger;

    public function __construct(
        Settings $settings,
        LoggerInterface $logger
    ) {
        $this->settings = $settings;
        $this->logger = $logger;
    }

    public function getCities(int $page = 1, int $limit = 500): array
    {
        $request = [
            "modelName" => "Address",
            "calledMethod" => "getCities",
            "methodProperties" => [
                "Page" => (string) $page,
                "Limit" => (string) $limit,
            ],
        ];

        $response = $this->request($request);
        if (!empty($response->success) && !empty($response->data)) {
            $result = [];
            foreach ($response->data as $city) {
                $result[] = [
                    'ref' => $city->Ref ?? '',
                    'city_ref' => $city->Ref ?? '',
                    'city_name' => $city->Description ?? '',
                ];
            }
            return $result;
        }
        return [];
    }

    public function searchSettlements(string $cityName = ''): array
    {
        $request = [
            "modelName" => "AddressGeneral",
            "calledMethod" => "searchSettlements",
            "methodProperties" => [
                "CityName" => $cityName,
                "Limit" => "5000",
            ],
        ];

        $response = $this->request($request);
        if (empty($response->success) || empty($response->data)) {
            return [];
        }

        $result = [];
        foreach ($response->data as $item) {
            // searchSettlements повертає записи всередині data[].Addresses.
            // Підтримуємо також плоску відповідь на випадок зміни формату API.
            $addresses = [];
            if (!empty($item->Addresses) && is_iterable($item->Addresses)) {
                $addresses = $item->Addresses;
            } else {
                $addresses = [$item];
            }

            foreach ($addresses as $settlement) {
                $ref = $settlement->Ref ?? '';
                $cityRef = $settlement->DeliveryCity ?? '';
                $name = $settlement->MainDescription ?? ($settlement->Description ?? '');

                if ($ref === '' || $name === '') {
                    continue;
                }

                // Не фільтруємо лише типом "м.". Нова Пошта обслуговує також
                // села/селища, а IP-геолокація цілком може повернути їх назву.
                $result[] = [
                    'ref' => $ref,
                    'city_ref' => $cityRef,
                    'city_name' => $name,
                ];
            }
        }

        return $result;
    }

    public function getCityRefByName(string $cityName): ?string
    {
        $results = $this->searchSettlements($cityName);
        foreach ($results as $result) {
            if (!empty($result['city_ref']) && 
                mb_strtolower(trim($result['city_name'])) === mb_strtolower(trim($cityName))) {
                return $result['city_ref'];
            }
        }
        return null;
    }

    public function getSettlements(int $page = 1, int $limit = 500): array
    {
        $request = [
            "modelName" => "AddressGeneral",
            "calledMethod" => "getSettlements",
            "methodProperties" => [
                "Page" => (string) $page,
                "Limit" => (string) $limit,
            ],
        ];

        $response = $this->request($request);
        $this->lastSettlementsPageSize = empty($response->data) ? 0 : count((array) $response->data);

        if (!empty($response->success) && !empty($response->data)) {
            $result = [];
            
            foreach ($response->data as $settlement) {
                $isCity = false;
                $isVillage = false;
                
                if (!empty($settlement->SettlementTypeDescription)) {
                    $typeDesc = mb_strtolower($settlement->SettlementTypeDescription);
                    if (stripos($typeDesc, 'село') !== false || 
                        stripos($typeDesc, 'селище') !== false ||
                        stripos($typeDesc, 'selo') !== false ||
                        stripos($typeDesc, 'selyshche') !== false) {
                        $isVillage = true;
                    }
                }
                
                if ($isVillage) {
                    continue;
                }
                
                if (!empty($settlement->SettlementTypeDescription)) {
                    $typeDesc = mb_strtolower($settlement->SettlementTypeDescription);
                    if (stripos($typeDesc, 'місто') !== false || 
                        stripos($typeDesc, 'город') !== false ||
                        stripos($typeDesc, 'misto') !== false) {
                        $isCity = true;
                    }
                }
                
                if (!$isCity && !empty($settlement->SettlementTypeDescriptionTranslit)) {
                    $typeDescTranslit = mb_strtolower($settlement->SettlementTypeDescriptionTranslit);
                    if (stripos($typeDescTranslit, 'misto') !== false) {
                        $isCity = true;
                    }
                }
                
                if ($isCity && !empty($settlement->Ref) && !empty($settlement->Description)) {
                    $result[] = [
                        'ref' => $settlement->Ref,
                        'city_name' => $settlement->Description,
                        'city_translit' => $settlement->DescriptionTranslit ?? '',
                    ];
                }
            }
            
            return $result;
        }
        return [];
    }

    /**
     * Усі міста довідника, посторінково.
     *
     * Пагінацію веде сира кількість записів на сторінці, а не кількість міст
     * після відсіву: сторінка з самих лише сіл — звичайна річ, і зупинятись
     * на ній означало б втратити весь хвіст довідника.
     */
    public function getAllCitySettlements(int $limit = 500, int $maxPages = 200): array
    {
        $all = [];

        for ($page = 1; $page <= $maxPages; $page++) {
            $cities = $this->getSettlements($page, $limit);

            if (!empty($cities)) {
                $all = array_merge($all, $cities);
            }

            if ($this->lastSettlementsPageSize < $limit) {
                break;
            }
        }

        return $all;
    }

    public function getLastCallError(): string
    {
        return $this->lastCallError;
    }

    /**
     * protected, а не private, щоб тест міг підмінити транспорт і перевірити
     * класифікацію населених пунктів без походу в API Нової Пошти. Той самий
     * прийом уже застосований в OkayCMS/NovaposhtaCost, де NPApiHelper::request
     * узагалі публічний.
     */
    protected function request(array $requestParams)
    {
        if (empty($requestParams)) {
            return false;
        }
        
        $requestParams["apiKey"] = $this->settings->get('newpost_key');

        $maxRetries = 3;
        $retryDelay = 1;
        $retryErrno = [6, 7, 35, 28, 52, 56];

        $attempt = 0;

        do {
            $attempt++;

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, 'https://api.novaposhta.ua/v2.0/json/');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Connection: close"
            ]);
            curl_setopt($ch, CURLOPT_HEADER, 0);

            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST , 'POST');

            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestParams));

            curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            curl_setopt($ch, CURLOPT_AUTOREFERER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            $response = curl_exec($ch);
            $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno    = curl_errno($ch);
            $error    = curl_error($ch);


            $tooManyRequests = false;
            if ($response !== false) {
                $responseJson = json_decode($response);

                if (!empty($responseJson->errors)
                    && in_array('To many requests', $responseJson->errors, true)
                ) {
                    $this->lastCallError = "To many requests";
                    // Всередині циклу ретраїв: після цього буде ще спроба, і вона
                    // цілком може вдатись. Тому той самий рівень, що й у решти
                    // ретраїв нижче, — інакше успішний виклик лишав по собі ERROR.
                    $this->logger->warning('NovaPoshta Popular Cities API error: "' . $this->lastCallError . '"');
                    $tooManyRequests = true;
                } else {
                    break;
                }
            }

            if (!$tooManyRequests && !in_array($errno, $retryErrno, true)) {
                $this->lastCallError = "CURL response code:$status error #{$errno}: {$error}";
                $this->logger->warning('NovaPoshta Popular Cities API error: "' . $this->lastCallError . '"');
                return false;
            }

            $this->logger->warning(sprintf(
                'NovaPoshta Popular Cities API warning retry %d/%d: CURL #%d %s status http:%d',
                $attempt,
                $maxRetries,
                $errno,
                $error,
                $status
            ));

            sleep($retryDelay);

        } while ($attempt < $maxRetries);

        if ($response === false) {
            $this->lastCallError = "CURL failed after {$maxRetries} retries. Last error #{$errno}: {$error}";
            $this->logger->warning('NovaPoshta Popular Cities API error: "' . $this->lastCallError . '"');
            return false;
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->lastCallError = 'Invalid JSON response';
            $this->logger->warning('NovaPoshta Popular Cities API error: "' . $this->lastCallError . '"');
            return false;
        }

        if (!empty($responseJson->errors)) {
            $this->lastCallError = implode('<br>', (array) $responseJson->errors);

            if (strpos($this->lastCallError, 'API key') !== false) {
                $this->settings->set('np_api_key_error', $this->lastCallError);
            }

            $this->logger->warning('NovaPoshta Popular Cities API error: "' . $this->lastCallError . '"');
            return false;
        }
        
        if (!empty($responseJson->success)) {
            if (!isset($responseJson->data)) {
                $this->lastCallError = 'Response data is empty';
                $this->logger->warning('NovaPoshta Popular Cities API error: "' . $this->lastCallError . '"');
                return false;
            }
            return $responseJson;
        }

        return false;
    }
}

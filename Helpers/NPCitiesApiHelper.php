<?php


namespace Okay\Modules\Sviat\NovaPoshtaPopularCities\Helpers;


use Okay\Core\Settings;
use Psr\Log\LoggerInterface;

class NPCitiesApiHelper
{
    private string $lastCallError = '';
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
        if (!empty($response->success) && !empty($response->data)) {
            $result = [];
            foreach ($response->data as $settlement) {
                if (!empty($settlement->SettlementTypeCode) && 
                    (trim($settlement->SettlementTypeCode) === 'м.' || trim($settlement->SettlementTypeCode) === 'м')) {
                    $result[] = [
                        'ref' => $settlement->Ref ?? '',
                        'city_ref' => $settlement->DeliveryCity ?? '',
                        'city_name' => $settlement->MainDescription ?? '',
                    ];
                }
            }
            return $result;
        }
        return [];
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

    public function getLastCallError(): string
    {
        return $this->lastCallError;
    }

    private function request(array $requestParams)
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

            curl_close($ch);

            $tooManyRequests = false;
            if ($response !== false) {
                $responseJson = json_decode($response);

                if (!empty($responseJson->errors)
                    && in_array('To many requests', $responseJson->errors, true)
                ) {
                    $this->lastCallError = "To many requests";
                    $this->logger->error('NovaPoshta Popular Cities API error: "' . $this->lastCallError . '"');
                    $tooManyRequests = true;
                } else {
                    break;
                }
            }

            if (!$tooManyRequests && !in_array($errno, $retryErrno, true)) {
                $this->lastCallError = "CURL response code:$status error #{$errno}: {$error}";
                $this->logger->error('NovaPoshta Popular Cities API error: "' . $this->lastCallError . '"');
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
            $this->logger->error('NovaPoshta Popular Cities API error: "' . $this->lastCallError . '"');
            return false;
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->lastCallError = 'Invalid JSON response';
            $this->logger->error('NovaPoshta Popular Cities API error: "' . $this->lastCallError . '"');
            return false;
        }

        if (!empty($responseJson->errors)) {
            $this->lastCallError = implode('<br>', (array) $responseJson->errors);

            if (strpos($this->lastCallError, 'API key') !== false) {
                $this->settings->set('np_api_key_error', $this->lastCallError);
            }

            $this->logger->error('NovaPoshta Popular Cities API error: "' . $this->lastCallError . '"');
            return false;
        }
        
        if (!empty($responseJson->success)) {
            if (!isset($responseJson->data)) {
                $this->lastCallError = 'Response data is empty';
                $this->logger->error('NovaPoshta Popular Cities API error: "' . $this->lastCallError . '"');
                return false;
            }
            return $responseJson;
        }

        return false;
    }
}

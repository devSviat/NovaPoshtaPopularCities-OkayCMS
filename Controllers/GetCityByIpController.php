<?php


namespace Okay\Modules\Sviat\NovaPoshtaPopularCities\Controllers;


use Okay\Core\Request;
use Okay\Core\Response;
use Okay\Core\Settings;
use Okay\Core\EntityFactory;
use Psr\Log\LoggerInterface;
use Okay\Modules\Sviat\NovaPoshtaPopularCities\Entities\NPSettlementsEntity;

class GetCityByIpController
{
    public function getCityByIp(
        Request $request,
        Response $response,
        EntityFactory $entityFactory,
        Settings $settings,
        LoggerInterface $logger
    ) {
        /** @var NPSettlementsEntity $settlementsEntity */
        $settlementsEntity = $entityFactory->get(NPSettlementsEntity::class);
        
        $enableIpDetection = $settings->get('sviat__np_popular_cities__enable_ip_detection');
        if (!$enableIpDetection) {
            $response->setContent(json_encode([
                'success' => false, 
                'message' => 'IP detection is disabled'
            ]), RESPONSE_JSON);
            return;
        }
        
        $clientIpFromParam = $request->get('ip');
        $testIp = $request->get('test_ip');
        $clientIp = $clientIpFromParam ?: $testIp;
        
        if (empty($clientIp)) {
            $clientIp = $this->getClientIp($request);
        }
        
        if (empty($clientIp)) {
            // Наш бік: за HTTP REMOTE_ADDR майже завжди є, тож порожній IP —
            // це радше проксі/конфіг, ніж поведінка відвідувача.
            $logger->warning('[NovaPoshtaPopularCities] GetCityByIpController: IP is empty');
            $response->setContent(json_encode([
                'success' => false, 
                'message' => 'IP not found'
            ]), RESPONSE_JSON);
            return;
        }
        
        if (!$testIp && ($clientIp === '127.0.0.1' || $clientIp === '::1')) {
            $response->setContent(json_encode([
                'success' => false, 
                'message' => 'Local IP detected'
            ]), RESPONSE_JSON);
            return;
        }
        
        $apiResult = $this->getCityNameByIp($clientIp, $logger);
        
        // Без логу: getCityNameByIp() уже написала, що саме не вдалось —
        // або помилку конкретного API, або «All IP APIs failed». Другий рядок
        // про ту саму подію лише дублював би її, до того ж без подробиць.
        if (!$apiResult || empty($apiResult['cityName'])) {
            $response->setContent(json_encode([
                'success' => false, 
                'message' => 'City not found'
            ]), RESPONSE_JSON);
            return;
        }
        
        $cityNameEn = $apiResult['cityName'];
        $city = null;
        
        if (!empty($cityNameEn)) {
            $city = $settlementsEntity->findOne(['city_translit' => $cityNameEn]);
            
            if (!$city) {
                $cityNameEnLower = mb_strtolower(trim($cityNameEn));
                $cityNameNormalized = $this->normalizeTranslit($cityNameEn);
                $settlements = $settlementsEntity->find();

                foreach ($settlements as $s) {
                    if (empty($s->city_translit)) {
                        continue;
                    }

                    $settlementTranslitLower = mb_strtolower(trim($s->city_translit));
                    if ($settlementTranslitLower === $cityNameEnLower
                        || $this->normalizeTranslit($s->city_translit) === $cityNameNormalized
                    ) {
                        $city = $s;
                        break;
                    }
                }
            }
        }
        
        if (!$city || empty($city->city_ref)) {
            // Не помилка: відвідувач із міста, якого немає в довіднику Нової Пошти
            // (інша країна, село поза списком). Штатний результат пошуку.
            $logger->info('[NovaPoshtaPopularCities] GetCityByIpController: City not found in database. Searched translit: ' . $cityNameEn);
            $response->setContent(json_encode([
                'success' => false, 
                'message' => 'City not found in database'
            ]), RESPONSE_JSON);
            return;
        }
        
        $result = [
            'success' => true,
            'city' => [
                'ref' => $city->city_ref,
                'name' => $city->city_name,
            ]
        ];
        
        $response->setContent(json_encode($result), RESPONSE_JSON);
    }
    
    private function normalizeTranslit(string $value): string
    {
        $value = strtolower(trim($value));

        // Різні geo-IP сервіси та Нова Пошта можуть по-різному передавати
        // українське "щ": shch / sch. Напр. Borshchahivka / Borschahivka.
        $value = str_replace('shch', 'sch', $value);

        // Для порівняння назви населеного пункту пробіли, дефіси та
        // апострофи не повинні створювати різні ключі.
        return preg_replace('/[^a-z0-9]+/u', '', $value) ?? $value;
    }

    private function getClientIp(Request $request): string
    {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_CLIENT_IP',
        ];
        
        foreach ($ipHeaders as $header) {
            $ip = null;
            if (isset($_SERVER[$header])) {
                $ip = $_SERVER[$header];
            }
            
            if (!empty($ip)) {
                if ($header === 'HTTP_X_FORWARDED_FOR' || $header === 'HTTP_X_FORWARDED') {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!empty($remoteAddr) && filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
            return $remoteAddr;
        }
        
        return '';
    }
    
    private function getCityNameByIp(string $ip, LoggerInterface $logger): array
    {
        $apis = [
            [
                'name' => 'ip-api.com',
                'url' => 'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,message,city,country',
                'parser' => 'parseIpApiCom'
            ],
            [
                'name' => 'ipinfo.io',
                'url' => 'https://ipinfo.io/' . urlencode($ip) . '/json',
                'parser' => 'parseIpInfoIo'
            ],
        ];
        
        $lastError = null;
        
        foreach ($apis as $api) {
            $result = $this->callIpApi($api['url'], $logger);
            
            if (isset($result['error'])) {
                $lastError = $result;
                continue;
            }
            
            $parsed = $this->{$api['parser']}($result['data'], $logger);
            
            if (isset($parsed['cityName']) && !empty($parsed['cityName'])) {
                return $parsed;
            }
            
            $lastError = $parsed;
        }
        
        // Зовнішні гео-сервіси, не наш код: сайт працює, лише підказка міста
        // не спрацювала.
        $logger->warning('[NovaPoshtaPopularCities] GetCityByIpController: All IP APIs failed');
        return $lastError ?: ['error' => 'All IP APIs failed'];
    }
    
    private function callIpApi(string $url, LoggerInterface $logger): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        if ($curlError) {
            return ['error' => 'cURL error: ' . $curlError];
        }
        
        if ($httpCode !== 200) {
            return ['error' => 'HTTP error code: ' . $httpCode, 'rawResponse' => $response];
        }
        
        $data = json_decode($response, true);
        
        if (!$data) {
            return ['error' => 'Invalid JSON response', 'rawResponse' => $response];
        }
        
        return ['data' => $data];
    }
    
    private function parseIpApiCom(array $data, LoggerInterface $logger): array
    {
        if ($data['status'] !== 'success') {
            $errorMsg = $data['message'] ?? 'Unknown error';
            return ['error' => 'API error: ' . $errorMsg, 'rawResponse' => $data];
        }
        
        $cityName = $data['city'] ?? null;
        
        if (empty($cityName)) {
            return ['error' => 'City name is empty', 'rawResponse' => $data];
        }
        
        return [
            'cityName' => $cityName,
            'country' => $data['country'] ?? null
        ];
    }
    
    private function parseIpInfoIo(array $data, LoggerInterface $logger): array
    {
        if (isset($data['error'])) {
            $errorMsg = is_array($data['error']) ? json_encode($data['error']) : $data['error'];
            return ['error' => 'API error: ' . $errorMsg, 'rawResponse' => $data];
        }
        
        $cityName = $data['city'] ?? null;
        
        if (empty($cityName)) {
            return ['error' => 'City name is empty', 'rawResponse' => $data];
        }
        
        return [
            'cityName' => $cityName,
            'country' => $data['country'] ?? null
        ];
    }
    
}

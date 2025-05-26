<?php
namespace App\Billingo\WooCommerce\Service;
use App\Billingo\Service\BillingoClient;
use Symfony\Component\HttpFoundation\Response;
use App\Billingo\WooCommerce\Service\Billingo_Logger;

class Billingo_Connection_Tester
{
    /**
     * Teszteli a Billingo API-val való kapcsolatot
     * 
     * @param string|null $apiKey Az API kulcs amit tesztelni szeretnénk
     * @return array Eredmény tömb három lehetséges állapottal: 'not_configured', 'error', 'success'
     */
    public static function testConnection(string $apiKey = null): string
    {
        if (empty($apiKey)) {
            $apiKey = get_option('wc_billingo_api_key', '');
        }
        
        if (empty($apiKey)) {
            return 'not_configured';
        }
        
        try {
            $client = new BillingoClient($apiKey);
            $response = $client->util()->getServerTime()->getResponse();
            
            if ($response->getStatusCode() === Response::HTTP_OK) {
                Billingo_Logger::info("API connection test successful");
                return 'success';
            } else {
                $errors = $response->getErrors();
                Billingo_Logger::error('API connection test failed: ' . json_encode($errors));
                return 'error';
            }
        } catch (\Exception $exception) {
            Billingo_Logger::error('API connection test failed with exception: ' . $exception->getMessage());
            return 'error';
        }
    }
}
<?php

namespace App\Billingo\Service;

use App\Billingo\Enums\BillingoErrorEnum;
use App\Billingo\Enums\HttpMethodEnum;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;

class BillingoConnector
{
    const BASE_URI = 'https://api.billingo.hu/v3/';
    private readonly Client $client;
    private array $header;
    private static ?BillingoConnector $instance = null;

    public function __construct(string $apiKey)
    {
        // Időtúllépés nélkül (Guzzle alapértelmezés: korlátlan) egy Billingo-kiesés a pénztárat, az állapotváltás
        // hookokat és a rendelés-szerkesztő oldalt is a PHP max_execution_time-ig lefagyasztaná.
        $this->client = new Client([
            'base_uri' => self::BASE_URI,
            'connect_timeout' => 5,
            'timeout' => 30,
            // Az átirányítás az egyedi X-API-KEY fejlécet is továbbvinné (a Guzzle csak az Authorization és
            // Cookie fejlécet törli host-váltáskor); a Billingo API átirányítást nem használ.
            'allow_redirects' => false,
        ]);
        $this->header = [
            'X-API-KEY' => $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Plugin-Name'    => 'Woccomerce 4.0',
        ];
    }

    public static function getInstance(string $apiKey = ''): BillingoConnector
    {
        if (self::$instance === null) {
            self::$instance = new self($apiKey);
        } elseif ($apiKey !== '' && $apiKey !== self::$instance->header['X-API-KEY']) {
            // Az első példány kulcsa különben örökre megmaradna: egy másik kulccsal (pl. a beállításoknál még
            // el sem mentett kapcsolat-teszt kulccsal) indított hívás a régi kulcsot használná.
            self::$instance->header['X-API-KEY'] = $apiKey;
        }

        return self::$instance;
    }

    public function send(ApiContext $apiContext, ?string $outputFile = null): BillingoResponse
    {
        if (!$apiContext->selfControl()) {

            return new BillingoResponse(
                BillingoErrorEnum::BAD_API_CONTEXT->value,
                0,
                errors: ['bad context'],
            );
        }

        try {
            $response = $this->client->request(
                $apiContext->getMethod()->value,
                $apiContext->getUrl(),
                [
                    'headers' => $this->header,
                    'query' => $apiContext->getMethod() == HttpMethodEnum::GET
                        ? $apiContext->getContent()
                        : null,
                    'body' => $apiContext->getMethod() != HttpMethodEnum::GET
                        ? json_encode($apiContext->getContent(), JSON_INVALID_UTF8_SUBSTITUTE)
                        : null,
                ]
            );

            $contentType = $response->getHeaderLine('Content-Type');

            if (str_contains($contentType, 'application/json')) {
                return $this->getJsonResponse($response);
            }

            // Csak a ténylegesen letöltött fájl számít PDF-nek: egy 204-es válasz vagy egy HTML karbantartó
            // oldal korábban "sikeres PDF"-ként, üres Document-tel jött vissza.
            if (str_contains($contentType, 'pdf') || str_contains($contentType, 'octet-stream')) {
                return $this->getPdfResponse($response, $outputFile);
            }

            if (trim((string)$response->getBody()) === '') {
                return new BillingoResponse($response->getReasonPhrase(), $response->getStatusCode());
            }

            return new BillingoResponse(
                BillingoErrorEnum::NETWORK_ERROR->value,
                $response->getStatusCode(),
                data: billingoCollection([]),
                errors: ['Váratlan, nem JSON válasz érkezett a Billingo API-tól (Content-Type: ' . $contentType . ')'],
            );

        } catch (GuzzleException $exception) {

            $response = $exception instanceof RequestException ? $exception->getResponse() : null;

            if ($response === null) {
                return new BillingoResponse(
                    BillingoErrorEnum::NETWORK_ERROR->value,
                    0,
                    data: billingoCollection([]),
                    errors: [$exception->getMessage()],
                );
            }

            $errors = json_decode($response->getBody()->getContents(), true);

            return new BillingoResponse(
                $response->getReasonPhrase(),
                $response->getStatusCode(),
                data: billingoCollection([]),
                errors: is_array($errors) ? $errors : [(string) $response->getBody()]
            );
        }
    }

    protected function getPdfResponse(Response $response, ?string $outputFile): BillingoResponse
    {
        // Az Accept fejlécet NEM írjuk át a singleton-on: egy letöltés után minden későbbi JSON hívás
        // Accept: application/pdf fejléccel menne ki.
        $pdfContent = $response->getBody()->getContents();

        $billingoResponse = new BillingoResponse($response->getReasonPhrase(), $response->getStatusCode());

        if ($outputFile) {
            file_put_contents($outputFile, $pdfContent);

            $billingoResponse->setData(["message" => "PDF saved to $outputFile"]);

        } else {

            $billingoResponse->setData(["content" => $pdfContent]);
        }

        return $billingoResponse;
    }

    protected function getJsonResponse(Response $response): BillingoResponse
    {
        $body = json_decode($response->getBody()->getContents(), true);

        // Üres törzs, "null" vagy hibás JSON (proxy, WAF, megcsonkolt válasz) esetén a null a BillingoResponse
        // tömb-típusú paraméterén TypeError-t (\Error) dobna, amit a hívók catch (\Exception) ága nem fog meg.
        if (!is_array($body)) {
            return new BillingoResponse(
                BillingoErrorEnum::NETWORK_ERROR->value,
                $response->getStatusCode(),
                data: billingoCollection([]),
                errors: ['A Billingo API érvénytelen (nem értelmezhető JSON) választ adott.'],
            );
        }

        $hasDataKey = isset($body['data']);
        $meta = $hasDataKey ? array_diff_key($body, ['data' => '']) : [];
        $data = $hasDataKey
            ? (empty($body['data']) ? billingoCollection([]) : $body['data'])
            : $body;

        return new BillingoResponse(
            $response->getReasonPhrase(),
            $response->getStatusCode(),
            $meta,
            $data,
        );
    }
}

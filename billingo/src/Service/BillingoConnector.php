<?php

namespace App\Billingo\Service;

use App\Billingo\Enums\BillingoErrorEnum;
use App\Billingo\Enums\HttpMethodEnum;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;

class BillingoConnector
{
    const BASE_URI = 'https://api.billingo.hu/v3/';
    private readonly Client $client;
    private array $header;
    private static ?BillingoConnector $instance = null;

    public function __construct(string $apiKey)
    {
        $this->client = new Client(['base_uri' => self::BASE_URI]);
        $this->header = [
            'X-API-KEY' => $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    public static function getInstance(string $apiKey = ''): BillingoConnector
    {
        if (self::$instance === null) {
            self::$instance = new self($apiKey);
        }

        return self::$instance;
    }

    public function send(ApiContext $apiContext, string $outputFile = null): BillingoResponse
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
                        ? json_encode($apiContext->getContent())
                        : null,
                ]
            );

            $contentType = $response->getHeaderLine('Content-Type');

            return str_contains($contentType, 'application/json')
                ? $this->getJsonResponse($response)
                : $this->getPdfResponse($response, $outputFile);

        } catch (GuzzleException $exception) {

            $response = $exception->getResponse();

            return new BillingoResponse(
                $response->getReasonPhrase(),
                $response->getStatusCode(),
                data: billingoCollection([]),
                errors: json_decode($response->getBody()->getContents(), true)
            );
        }
    }

    protected function getPdfResponse(Response $response, ?string $outputFile): BillingoResponse
    {
        $this->header['Accept'] = $response->getHeaderLine('Content-Type');

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

        $meta = isset($body['data']) ? array_diff_key($body, ['data' => '']) : [];
        $data = !empty($meta)
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

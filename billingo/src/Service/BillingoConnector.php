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
        $this->client = new Client(['base_uri' => self::BASE_URI]);
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

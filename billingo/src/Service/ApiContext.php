<?php

namespace App\Billingo\Service;

use App\Billingo\Enums\HttpMethodEnum;

class ApiContext
{
    protected string $url;
    protected array $content = [];
    protected HttpMethodEnum $method;

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getContent(): array
    {
        return $this->content;
    }

    public function setContent(array $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getMethod(): HttpMethodEnum
    {
        return $this->method;
    }

    public function setMethod(HttpMethodEnum $method): self
    {
        $this->method = $method;

        return $this;
    }

    public function fromArray(array $apiContext): self
    {
        $callableFunctions = [
            'url' => 'setUrl',
            'method' => 'setMethod',
            'content' => 'setContent',
        ];

        array_walk($callableFunctions, function ($function, $name) use ($apiContext) {

            if (isset($apiContext[$name])) {
                $this->$function($apiContext[$name]);
            }
        });

        return $this;
    }

    public function selfControl(): bool
    {
        return !empty($this->url) && !empty($this->method);
    }
}

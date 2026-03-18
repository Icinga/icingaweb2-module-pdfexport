<?php

namespace Icinga\Module\Pdfexport\WebDriver;

use Exception;
use GuzzleHttp\Client;
use RuntimeException;

class CommandExecutor
{
    protected const DEFAULT_HEADERS = [
        'Content-Type' => 'application/json;charset=UTF-8',
        'Accept' => 'application/json',
    ];

    protected ?Client $client = null;

    public function __construct(
        protected string $url,
        protected ?float $timeout = 10,
    ) {
        $this->client = new Client();
    }

    public function execute(?string $sessionId, CommandInterface $command): Response
    {
        $method = $command->getMethod();
        $path = $command->getPath();
        if (str_contains($path, ':sessionId')) {
            if ($sessionId === null) {
                throw new RuntimeException('Session ID is not set');
            }
            $path = str_replace(':sessionId', $sessionId, $path);
        }
        $params = $command->getParameters();
        foreach ($params as $name => $value) {
            if (str_starts_with($name, ':')) {
                $path = str_replace($name, $value, $path);
                unset($params[$name]);
            }
        }

        if (is_array($params) && ! empty($params) && $method !== 'POST') {
            throw new RuntimeException('Invalid HTTP method');
        }

        if ($command instanceof Command
            && $command->getName() === DriverCommand::NewSession) {
            $method = 'POST';
        }

        $headers = static::DEFAULT_HEADERS;

        if (in_array($method, ['POST', 'PUT'], true)) {
            unset ($headers['expect']);
        }

        if (is_array($params) && ! empty($params)) {
            $body = json_encode($params);
        } else {
            $body = '{}';
        }

        $options = [
            'headers' => $headers,
            'expect' => false,
            'body' => $body,
            'http_errors' => false,
            'timeout' => $this->timeout ?? 0,
        ];

        $response = $this->client->request($method, $this->url . $path, $options);

        $results = json_decode($response->getBody()->getContents(), true);

        if ($results === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(json_last_error_msg());
        }

        if (! is_array($results)) {
            throw new RuntimeException('Invalid response');
        }

        $value = $results['value'] ?? null;
        $message = $results['message'] ?? null;

        if (is_array($value) && array_key_exists('sessionId', $value)) {
            $sessionId = $value['sessionId'];
        } else if (isset($results['sessionId'])) {
            $sessionId = $results['sessionId'];
        }

        if (isset($value['error'])) {
            throw new Exception(sprintf(
                "Error in command response: %s - %s", $value['error'], $value['message'] ?? "Unknown error"
            ));
        }

        $status = $results['status'] ?? 0;
        if ($status !== 0) {
            throw new Exception($message, $status);
        }

        return new Response($sessionId, $status, $value);
    }
}

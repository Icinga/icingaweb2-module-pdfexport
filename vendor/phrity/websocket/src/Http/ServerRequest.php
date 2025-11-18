<?php

/**
 * Copyright (C) 2014-2025 Textalk and contributors.
 * This file is part of Websocket PHP and is free software under the ISC License.
 */

namespace WebSocket\Http;

use BadMethodCallException;
use Psr\Http\Message\{
    ServerRequestInterface,
    UriInterface
};

/**
 * WebSocket\Http\ServerRequest class.
 * Only used for handshake procedure.
 */
class ServerRequest extends Request implements ServerRequestInterface
{
    /**
     * Retrieve server parameters.
     * @return array<mixed>
     */
    public function getServerParams(): array
    {
        throw new BadMethodCallException("Not implemented.");
    }

    /**
     * Retrieves cookies sent by the client to the server.
     * @return array<string, mixed>
     */
    public function getCookieParams(): array
    {
        throw new BadMethodCallException("Not implemented.");
    }

    /**
     * Return an instance with the specified cookies.
     * @param array<string, mixed> $cookies Array of key/value pairs representing cookies.
     * @return static
     */
    public function withCookieParams(array $cookies): self
    {
        throw new BadMethodCallException("Not implemented.");
    }

    /**
     * Retrieves the deserialized query string arguments, if any.
     * @return array<string|int, mixed>
     */
    public function getQueryParams(): array
    {
        parse_str($this->getUri()->getQuery(), $result);
        return $result;
    }

    /**
     * Return an instance with the specified query string arguments.
     * @param array<string, mixed> $query Array of query string arguments
     * @return static
     */
    public function withQueryParams(array $query): self
    {
        throw new BadMethodCallException("Not implemented.");
    }

    /**
     * Retrieve normalized file upload data.
     * @return array<mixed> An array tree of UploadedFileInterface instances.
     */
    public function getUploadedFiles(): array
    {
        throw new BadMethodCallException("Not implemented.");
    }

    /**
     * Create a new instance with the specified uploaded files.
     * @param array<mixed> $uploadedFiles An array tree of UploadedFileInterface instances.
     * @return static
     */
    public function withUploadedFiles(array $uploadedFiles): self
    {
        throw new BadMethodCallException("Not implemented.");
    }

    /**
     * Retrieve any parameters provided in the request body.
     * @return null|array<mixed>|object The deserialized body parameters, if any.
     */
    public function getParsedBody()
    {
        throw new BadMethodCallException("Not implemented.");
    }

    /**
     * Return an instance with the specified body parameters.
     * @param null|array<mixed>|object $data The deserialized body data.
     * @return static
     */
    public function withParsedBody($data): self
    {
        throw new BadMethodCallException("Not implemented.");
    }

    /**
     * Retrieve attributes derived from the request.
     * @return mixed[] Attributes derived from the request.
     */
    public function getAttributes(): array
    {
        throw new BadMethodCallException("Not implemented.");
    }

    /**
     * Retrieve a single derived request attribute.
     * @param string $name The attribute name.
     * @param mixed $default Default value to return if the attribute does not exist.
     * @return mixed
     */
    public function getAttribute(string $name, $default = null)
    {
        throw new BadMethodCallException("Not implemented.");
    }

    /**
     * Return an instance with the specified derived request attribute.
     * @param string $name The attribute name.
     * @param mixed $value The value of the attribute.
     * @return static
     */
    public function withAttribute(string $name, $value): self
    {
        throw new BadMethodCallException("Not implemented.");
    }

    /**
     * Return an instance that removes the specified derived request attribute.
     * @param string $name The attribute name.
     * @return static
     */
    public function withoutAttribute(string $name): self
    {
        throw new BadMethodCallException("Not implemented.");
    }

    public function __toString(): string
    {
        return $this->stringable('%s %s', $this->getMethod(), $this->getRequestTarget());
    }
}

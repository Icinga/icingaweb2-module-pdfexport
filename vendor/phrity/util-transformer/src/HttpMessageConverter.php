<?php

namespace Phrity\Util\Transformer;

use InvalidArgumentException;
use Psr\Http\Message\{
    MessageInterface,
    RequestInterface,
    ResponseInterface,
    ServerRequestInterface,
    UriInterface,
};

/**
 * @phpstan-type TypeUri array{
 *   scheme: string,
 *   authority: string,
 *   userInfo: string,
 *   host: string,
 *   port: int|null,
 *   path: string,
 *   query: string,
 *   fragment: string,
 * }
 * @phpstan-type TypeMessage array{
 *   protocolVersion: string,
 *   headers: TypeHeaders,
 *   body: string,
 * }
 * @phpstan-type TypeRequest array{
 *   protocolVersion: string,
 *   headers: TypeHeaders,
 *   body: string,
 *   requestTarget: string,
 *   method: string,
 *   uri: TypeUri|UriInterface,
 * }
 * @phpstan-type TypeServerRequest array{
 *   protocolVersion: string,
 *   headers: TypeHeaders,
 *   body: string,
 *   requestTarget: string,
 *   method: string,
 *   uri: TypeUri|UriInterface,
 *   serverParams: array<string, mixed>,
 *   cookieParams: array<string, mixed>,
 *   queryParams: array<string, mixed>,
 *   attributes: array<string, mixed>,
 * }
 * @phpstan-type TypeResponse array{
 *   protocolVersion: string,
 *   headers: TypeHeaders,
 *   body: string,
 *   statusCode: int,
 *   reasonPhrase: string,
 * }
 * @phpstan-type TypeHeaders array<string, string|int|null>
*/
class HttpMessageConverter implements TransformerInterface
{
    /** @var array<string|null> $supportedTypes */
    private static array $supportedTypes = [
        Type::ARRAY,
        Type::OBJECT,
        Type::STRING,
    ];
    private string $default;
    private bool $subresolve;


    /**
     * @param Type::ARRAY|Type::OBJECT|Type::STRING $default
     */
    public function __construct(string $default = Type::OBJECT, bool $subresolve = false)
    {
        if (!in_array($default, self::$supportedTypes)) {
            throw new InvalidArgumentException("Invalid '{$default}' provided");
        }
        $this->default = $default;
        $this->subresolve = $subresolve;
    }

    public function canTransform(mixed $subject, string|null $type = null): bool
    {
        $type ??= $this->default;
        if (!in_array($type, self::$supportedTypes)) {
            return false;
        }
        if ($subject instanceof UriInterface || $subject instanceof MessageInterface) {
            return true;
        }
        return false;
    }

    /**
     * @param UriInterface|MessageInterface $subject
     * @param Type::ARRAY|Type::OBJECT|Type::STRING|null $type
     * @return TypeUri|TypeRequest|TypeServerRequest|TypeResponse|TypeMessage|object|string
     */
    public function transform(mixed $subject, string|null $type = null): mixed
    {
        /** @var Type::ARRAY|Type::OBJECT|Type::STRING $targetType */
        $targetType = $type ?? $this->default;

        if (!$this->canTransform($subject, $targetType)) {
            $subjectType = get_debug_type($subject);
            throw new TransformerException("HttpMessage conversion for '{$subjectType}' is not supported.");
        }
        if ($subject instanceof UriInterface) {
            return match ($targetType) {
                Type::ARRAY => $this->createUriData($subject),
                Type::OBJECT => $this->toObject($this->createUriData($subject)),
                Type::STRING => $subject->__toString(),
            };
        }
        if ($subject instanceof ServerRequestInterface) {
            return match ($targetType) {
                Type::ARRAY => $this->createServerRequestData($subject),
                Type::OBJECT => $this->toObject($this->createServerRequestData($subject)),
                Type::STRING => sprintf(
                    '%s %s HTTP/%s',
                    $subject->getMethod(),
                    $subject->getRequestTarget(),
                    $subject->getProtocolVersion()
                ),
            };
        }
        if ($subject instanceof RequestInterface) {
            return match ($targetType) {
                Type::ARRAY => $this->createRequestData($subject),
                Type::OBJECT => $this->toObject($this->createRequestData($subject)),
                Type::STRING => sprintf(
                    '%s %s HTTP/%s',
                    $subject->getMethod(),
                    $subject->getRequestTarget(),
                    $subject->getProtocolVersion()
                ),
            };
        }
        if ($subject instanceof ResponseInterface) {
            return match ($targetType) {
                Type::ARRAY => $this->createResponseData($subject),
                Type::OBJECT => $this->toObject($this->createResponseData($subject)),
                Type::STRING => sprintf(
                    'HTTP/%s %s %s',
                    $subject->getProtocolVersion(),
                    $subject->getStatusCode(),
                    $subject->getReasonPhrase()
                ),
            };
        }
        return match ($targetType) {
            Type::ARRAY => $this->createMessageData($subject),
            Type::OBJECT => $this->toObject($this->createMessageData($subject)),
            Type::STRING => sprintf(
                'HTTP/%s',
                $subject->getProtocolVersion()
            ),
        };
    }

    /**
     * @param UriInterface $subject
     * @return TypeUri
     */
    private function createUriData(UriInterface $subject): array
    {
        return [
            'scheme' => $subject->getScheme(),
            'authority' => $subject->getAuthority(),
            'userInfo' => $subject->getUserInfo(),
            'host' => $subject->getHost(),
            'port' => $subject->getPort(),
            'path' => $subject->getPath(),
            'query' => $subject->getQuery(),
            'fragment' => $subject->getFragment(),
        ];
    }

    /**
     * @param RequestInterface $subject
     * @return TypeRequest
     */
    private function createRequestData(RequestInterface $subject): array
    {
        return array_merge($this->createMessageData($subject), [
            'requestTarget' => $subject->getRequestTarget(),
            'method' => $subject->getMethod(),
            'uri' => $this->subresolve ? $this->createUriData($subject->getUri()) : $subject->getUri(),
        ]);
    }

    /**
     * @param ServerRequestInterface $subject
     * @return TypeServerRequest
     */
    private function createServerRequestData(ServerRequestInterface $subject): array
    {
        return array_merge($this->createRequestData($subject), [
            'serverParams' => $subject->getServerParams(),
            'cookieParams' => $subject->getCookieParams(),
            'queryParams' => $subject->getQueryParams(),
            'attributes' => $subject->getAttributes(),
        ]);
    }

    /**
     * @param ResponseInterface $subject
     * @return TypeResponse
     */
    private function createResponseData(ResponseInterface $subject): array
    {
        return array_merge($this->createMessageData($subject), [
            'statusCode' => $subject->getStatusCode(),
            'reasonPhrase' => $subject->getReasonPhrase(),
        ]);
    }

    /**
     * @param MessageInterface $subject
     * @return TypeMessage
     */
    private function createMessageData(MessageInterface $subject): array
    {
        return [
            'protocolVersion' => $subject->getProtocolVersion(),
            'headers' => array_map(function (array $value) {
                return implode(', ', $value);
            }, $subject->getHeaders()),
            'body' => $subject->getBody()->__toString(),
        ];
    }

    /**
     * @param TypeUri|TypeMessage|TypeRequest|TypeServerRequest|TypeResponse|TypeHeaders $data
     */
    private function toObject(array $data): object
    {
        return (object)array_map(function (mixed $value) {
            return is_array($value) ? $this->toObject($value) : $value;
        }, $data);
    }
}

<?php

namespace Firebed\AadeMyData\Http;

use Firebed\AadeMyData\Exceptions\ErrorResponseException;
use Firebed\AadeMyData\Exceptions\MyDataAuthenticationException;
use Firebed\AadeMyData\Exceptions\MyDataConnectionException;
use Firebed\AadeMyData\Exceptions\MyDataException;
use Firebed\AadeMyData\Exceptions\MyDataTimeoutException;
use Firebed\AadeMyData\Exceptions\RateLimitExceededException;
use Firebed\AadeMyData\Exceptions\TransmissionFailedException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\NetworkTimeoutException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ResponseException;
use GuzzleHttp\Exception\ResponseTimeoutException;
use Psr\Http\Message\ResponseInterface;

/**
 * Default gateway: sends requests to the AADE myDATA REST API using Guzzle.
 */
class GuzzleGateway implements Gateway
{
    /**
     * @param array<string, mixed> $query
     * @throws MyDataAuthenticationException|MyDataException
     */
    public function get(MyDataRequest $request, array $query): string
    {
        return $this->send('GET', $request, ['query' => $query]);
    }

    /**
     * @param array<string, mixed>|null $query
     * @throws MyDataAuthenticationException|MyDataException
     */
    public function post(MyDataRequest $request, ?array $query = null, ?string $body = null): string
    {
        return $this->send('POST', $request, array_filter(['query' => $query, 'body' => $body]));
    }

    /**
     * @param array<string, mixed> $options Guzzle request options
     * @throws MyDataAuthenticationException|MyDataException
     */
    protected function send(string $method, MyDataRequest $request, array $options): string
    {
        $this->validateCredentials();

        try {
            $response = MyDataRequest::httpClient()->request($method, $request->getUrl(), $options);

            return $response->getBody()->getContents();
        } catch (GuzzleException $e) {
            $this->handleTransmissionException($e);
        }
    }

    /**
     * @throws MyDataAuthenticationException
     */
    protected function validateCredentials(): void
    {
        if (! MyDataRequest::hasCredentials()) {
            throw new MyDataAuthenticationException(401);
        }
    }

    /**
     * Authorization errors, bad request, communication errors,
     * myDATA server errors, rate limits, connection timeout, etc.
     *
     * @throws MyDataAuthenticationException|MyDataException
     */
    protected function handleTransmissionException(GuzzleException $exception): never
    {
        // Connection with myDATA was established, but the response took too long
        if ($this->isTimeout($exception)) {
            throw new MyDataTimeoutException(previous: $exception);
        }

        // In case the endpoint url is wrong or the connection timed out, myDATA is unreachable
        if ($exception->getCode() === 0) {
            throw new MyDataConnectionException($exception->getCode(), $exception);
        }

        // Authentication with myDATA failed, or the user is not authorized for the
        // requested resource. Keep the reason myDATA gives so the two can be told apart.
        $body = $this->body($exception);

        if ($exception->getCode() === 401) {
            throw new MyDataAuthenticationException($exception->getCode(), $exception, $this->reason($body));
        }

        // Rate limit exception
        if ($exception->getCode() === 429) {
            throw new RateLimitExceededException($exception->getMessage(), $exception->getCode(), $exception);
        }

        // myDATA can also answer a bad request with an error document (e.g. HTTP 400).
        // The HTTP status stays the exception code, as it was before the errors were read.
        throw ErrorResponseException::fromXml($body, $exception->getCode(), $exception)
            ?? new TransmissionFailedException($body ?: $exception->getMessage(), $exception->getCode(), $exception);
    }

    /**
     * Whether the request timed out after the connection with myDATA was established.
     *
     * Guzzle 8 reports these with dedicated exception classes; Guzzle 7 only exposes
     * the cURL error number (28) in the handler context of a RequestException.
     */
    private function isTimeout(GuzzleException $exception): bool
    {
        if ($exception instanceof NetworkTimeoutException || $exception instanceof ResponseTimeoutException) {
            return true;
        }

        return $exception instanceof RequestException
            && method_exists($exception, 'getHandlerContext')
            && ($exception->getHandlerContext()['errno'] ?? null) === 28;
    }

    /**
     * The response of a failed request, if one was received. Guzzle 8 exposes it on
     * ResponseException only; Guzzle 7 on every RequestException (nullable).
     */
    private function response(GuzzleException $exception): ?ResponseInterface
    {
        if ($exception instanceof ResponseException) {
            return $exception->getResponse();
        }

        if ($exception instanceof RequestException && method_exists($exception, 'getResponse')) {
            return $exception->getResponse();
        }

        return null;
    }

    /**
     * The body of a failed response, read from the start even if it has been read before.
     */
    private function body(GuzzleException $exception): string
    {
        return trim((string) $this->response($exception)?->getBody());
    }

    /**
     * The error message of a failed response: plain text from myDATA, the "message"
     * of the JSON error returned by the API gateway, or any other JSON as it is.
     * Markup (e.g. an HTML error page from a proxy) is not a usable reason.
     */
    private function reason(string $body): ?string
    {
        $json = json_decode($body, true);
        if (is_array($json) && is_string($json['message'] ?? null)) {
            $body = trim($json['message']);
        } elseif (is_string($json)) {
            $body = trim($json);
        }

        if ($body === '' || str_starts_with($body, '<')) {
            return null;
        }

        // Keep the exception message readable
        return mb_strlen($body) > 500 ? mb_substr($body, 0, 500) . '…' : $body;
    }
}

<?php

namespace Phare\Testing;

use Phalcon\Http\Response;
use Phalcon\Http\ResponseInterface;
use PHPUnit\Framework\Assert as PHPUnit;

class TestResponse extends Assert
{
    protected ?int $statusCode;

    protected Response\HeadersInterface $headers;

    /**
     * Create a new test response instance.
     */
    public function __construct(protected ResponseInterface $baseResponse)
    {
        $this->headers = $this->baseResponse->getHeaders();

        $this->statusCode = $this->baseResponse->getStatusCode();
    }

    protected function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getContent(): string
    {
        return $this->baseResponse->getContent();
    }

    public function getJsonContent(): array
    {
        return json_decode($this->getContent(), true);
    }

    public function assertHeader($headerName, $value = false)
    {
        PHPUnit::assertTrue(
            $this->headers->has($headerName),
            "Header [{$headerName}] not present on response."
        );

        if ($value !== false) {
            $actual = $this->headers->get($headerName);

            PHPUnit::assertEquals($value,
                $actual,
                "Header [{$headerName}] was found, but value [{$actual}] does not match [{$value}]."
            );
        }

        return $this;
    }

    public function assertSee($value)
    {
        PHPUnit::assertStringContainsString($value, $this->baseResponse->getContent());

        return $this;
    }

    /**
     * Assert that the response has a successful status code.
     *
     * @return $this
     */
    public function assertSuccessful()
    {
        $this->assertTrue(
            $this->isSuccessful(),
            $this->statusMessageWithDetails('>=200, <300', $this->getStatusCode())
        );

        return $this;
    }

    /**
     * Is response successful?
     *
     * @final
     */
    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Get an assertion message for a status assertion containing extra details when available.
     *
     * @param string|int $expected
     * @param string|int $actual
     * @return string
     */
    protected function statusMessageWithDetails($expected, $actual)
    {
        return "Expected response status code [{$expected}] but received {$actual}.";
    }

    /**
     * Assert that the response has a 200 status code.
     *
     * @return $this
     */
    public function assertOk()
    {
        return $this->assertStatus(200);
    }

    /**
     * Assert that the response has the given status code.
     *
     * @param int $status
     * @return $this
     */
    public function assertStatus($status)
    {
        $message = $this->statusMessageWithDetails($status, $actual = $this->getStatusCode());

        PHPUnit::assertSame($actual, $status, $message);

        return $this;
    }

    /**
     * Assert that the response has a 201 status code.
     *
     * @return $this
     */
    public function assertCreated()
    {
        return $this->assertStatus(201);
    }

    /**
     * Assert that the response has the given status code and no content.
     *
     * @param int $status
     * @return $this
     */
    public function assertNoContent($status = 204)
    {
        $this->assertStatus($status);

        PHPUnit::assertEmpty($this->getContent(), 'Response content is not empty.');

        return $this;
    }

    /**
     * Assert that the response has a not found status code.
     *
     * @return $this
     */
    public function assertNotFound()
    {
        return $this->assertStatus(404);
    }

    /**
     * Assert that the response has a forbidden status code.
     *
     * @return $this
     */
    public function assertForbidden()
    {
        return $this->assertStatus(403);
    }

    /**
     * Assert that the response has an unauthorized status code.
     *
     * @return $this
     */
    public function assertUnauthorized()
    {
        return $this->assertStatus(401);
    }

    /**
     * Assert that the response has a 422 status code.
     *
     * @return $this
     */
    public function assertUnprocessable()
    {
        return $this->assertStatus(422);
    }

    /**
     * Assert that the response is a server error.
     *
     * @return $this
     */
    public function assertServerError()
    {
        PHPUnit::assertTrue(
            $this->isServerError(),
            $this->statusMessageWithDetails('>=500, < 600', $this->getStatusCode())
        );

        return $this;
    }

    /**
     * Was there a server side error?
     *
     * @final
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    /**
     * Is response invalid?
     *
     * @see https://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
     *
     * @final
     */
    public function isInvalid(): bool
    {
        return $this->statusCode < 100 || $this->statusCode >= 600;
    }

    /**
     * Is response informative?
     *
     * @final
     */
    public function isInformational(): bool
    {
        return $this->statusCode >= 100 && $this->statusCode < 200;
    }

    /**
     * Is the response a redirect?
     *
     * @final
     */
    public function isRedirection(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    /**
     * Is there a client error?
     *
     * @final
     */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Is the response OK?
     *
     * @final
     */
    public function isOk(): bool
    {
        return $this->statusCode === 200;
    }

    /**
     * Is the response forbidden?
     *
     * @final
     */
    public function isForbidden(): bool
    {
        return $this->statusCode === 403;
    }

    /**
     * Is the response a not found error?
     *
     * @final
     */
    public function isNotFound(): bool
    {
        return $this->statusCode === 404;
    }
}

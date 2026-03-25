<?php

declare(strict_types=1);

namespace CzdsPhp;

use JsonException;

final class HttpResponse
{
	public function __construct(
		private readonly int $statusCode,
		private readonly array $headers,
		private readonly string $body = ''
	) {}

	public function getStatusCode(): int
	{
		return $this->statusCode;
	}

	public function getHeader(string $name): ?string
	{
		$normalizedName = strtolower($name);
		return $this->headers[$normalizedName] ?? null;
	}

	public function getHeaders(): array
	{
		return $this->headers;
	}

	public function getBody(): string
	{
		return $this->body;
	}

	public function decodeJson(): array
	{
		try {
			$decoded = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			throw new \RuntimeException('Response body is not valid JSON: ' . $exception->getMessage(), 0, $exception);
		}

		if (!is_array($decoded)) {
			throw new \RuntimeException('Response body is not a JSON object or array');
		}

		return $decoded;
	}
}

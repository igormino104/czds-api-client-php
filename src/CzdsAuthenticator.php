<?php

declare(strict_types=1);

namespace CzdsPhp;

use RuntimeException;

final class CzdsAuthenticator
{
	public function __construct(
		private readonly HttpClient $httpClient,
		private readonly string $authenticationBaseUrl
	) {}

	public function authenticate(string $username, string $password): string
	{
		$response = $this->httpClient->request(
			'POST',
			$this->authenticationBaseUrl . '/api/authenticate',
			[
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
			],
			json_encode(
				[
					'username' => $username,
					'password' => $password,
				],
				JSON_THROW_ON_ERROR
			)
		);

		return match ($response->getStatusCode()) {
			200 => $this->extractAccessToken($response),
			401 => throw new RuntimeException('Invalid username/password. Please reset your password via web'),
			404 => throw new RuntimeException('Invalid url ' . $this->authenticationBaseUrl . '/api/authenticate'),
			500 => throw new RuntimeException('Internal server error. Please try again later'),
			503 => throw new RuntimeException(sprintf(
				"ICANN authentication API is temporarily unavailable or returned an edge maintenance/challenge page for user %s (HTTP 503). Please retry later or verify the request is not blocked by ICANN/Cloudflare.\n\nResponse Body: %s",
				$username,
				$this->summarizeResponseBody($response->getBody())
			)),
			default => throw new RuntimeException(sprintf(
				"Failed to authenticate user %s with error code %d\n\nResponse Body: %s",
				$username,
				$response->getStatusCode(),
				$response->getBody()
			)),
		};
	}

	private function extractAccessToken(HttpResponse $response): string
	{
		$payload = $response->decodeJson();
		$accessToken = $payload['accessToken'] ?? null;
		if (!is_string($accessToken) || $accessToken === '') {
			throw new RuntimeException('Authentication succeeded but no accessToken was returned');
		}

		return $accessToken;
	}

	private function summarizeResponseBody(string $body): string
	{
		$body = trim($body);
		if (strlen($body) <= 500) {
			return $body;
		}

		return substr($body, 0, 500) . '...';
	}
}

<?php

declare(strict_types=1);

namespace CzdsPhp;

use CzdsPhp\Exception\HttpException;

final class HttpClient
{
	private const USER_AGENT = 'czds-api-client-php/1.0 (+https://github.com/igormino104/czds-api-client-php)';

	public function request(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse
	{
		$responseHeaders = [];
		$curl = $this->createHandle($method, $url, $headers, $responseHeaders);

		if ($body !== null) {
			curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
		}

		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

		$responseBody = curl_exec($curl);
		if ($responseBody === false) {
			$message = curl_error($curl);
			curl_close($curl);
			throw new HttpException(sprintf('HTTP request failed for %s: %s', $url, $message));
		}

		$statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		curl_close($curl);

		return new HttpResponse($statusCode, $responseHeaders, $responseBody);
	}

	public function downloadToFile(string $url, array $headers, string $destinationPath): HttpResponse
	{
		$responseHeaders = [];
		$temporaryPath = $destinationPath . '.part';
		$directory = dirname($temporaryPath);

		if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
			throw new HttpException(sprintf('Failed to create directory %s', $directory));
		}

		$handle = @fopen($temporaryPath, 'wb');
		if ($handle === false) {
			throw new HttpException(sprintf('Failed to open %s for writing', $temporaryPath));
		}

		$curl = $this->createHandle('GET', $url, $headers, $responseHeaders);
		curl_setopt($curl, CURLOPT_FILE, $handle);

		$result = curl_exec($curl);
		if ($result === false) {
			$message = curl_error($curl);
			curl_close($curl);
			fclose($handle);
			@unlink($temporaryPath);
			throw new HttpException(sprintf('HTTP download failed for %s: %s', $url, $message));
		}

		$statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		curl_close($curl);
		fclose($handle);

		if ($statusCode === 200) {
			if (is_file($destinationPath) && !@unlink($destinationPath)) {
				@unlink($temporaryPath);
				throw new HttpException(sprintf('Failed to replace existing file %s', $destinationPath));
			}

			if (!@rename($temporaryPath, $destinationPath)) {
				@unlink($temporaryPath);
				throw new HttpException(sprintf('Failed to move %s to %s', $temporaryPath, $destinationPath));
			}
		} else {
			@unlink($temporaryPath);
		}

		return new HttpResponse($statusCode, $responseHeaders);
	}

	private function createHandle(string $method, string $url, array $headers, array &$responseHeaders): \CurlHandle
	{
		$curl = curl_init($url);
		if ($curl === false) {
			throw new HttpException(sprintf('Failed to initialize cURL for %s', $url));
		}

		$normalizedHeaders = [];
		foreach ($headers as $name => $value) {
			$normalizedHeaders[] = sprintf('%s: %s', $name, $value);
		}

		curl_setopt_array($curl, [
			CURLOPT_CUSTOMREQUEST => strtoupper($method),
			CURLOPT_HTTPHEADER => $normalizedHeaders,
			CURLOPT_USERAGENT => self::USER_AGENT,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_CONNECTTIMEOUT => 30,
			CURLOPT_TIMEOUT => 300,
			CURLOPT_FAILONERROR => false,
			CURLOPT_HEADERFUNCTION => static function ($curlHandle, string $headerLine) use (&$responseHeaders): int {
				$trimmed = trim($headerLine);

				if ($trimmed === '') {
					return strlen($headerLine);
				}

				if (str_starts_with($trimmed, 'HTTP/')) {
					$responseHeaders = [];
					return strlen($headerLine);
				}

				$delimiterPosition = strpos($headerLine, ':');
				if ($delimiterPosition === false) {
					return strlen($headerLine);
				}

				$headerName = strtolower(trim(substr($headerLine, 0, $delimiterPosition)));
				$headerValue = trim(substr($headerLine, $delimiterPosition + 1));
				$responseHeaders[$headerName] = $headerValue;

				return strlen($headerLine);
			},
		]);

		return $curl;
	}
}

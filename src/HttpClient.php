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

		$curl = $this->createHandle('GET', $url, $headers, $responseHeaders);
		$resumeOffset = $this->getFileSize($temporaryPath);
		$handle = null;

		if ($resumeOffset > 0) {
			curl_setopt($curl, CURLOPT_RANGE, $resumeOffset . '-');
		}

		curl_setopt_array($curl, [
			CURLOPT_TIMEOUT => 0,
			CURLOPT_WRITEFUNCTION => static function ($curlHandle, string $chunk) use (&$handle, $temporaryPath, $resumeOffset): int {
				$statusCode = (int) curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);
				if ($statusCode !== 200 && $statusCode !== 206) {
					return strlen($chunk);
				}

				if ($handle === null) {
					$mode = $resumeOffset > 0 && $statusCode === 206 ? 'ab' : 'wb';
					$handle = @fopen($temporaryPath, $mode);
					if ($handle === false) {
						return 0;
					}
				}

				$written = fwrite($handle, $chunk);
				return $written === false ? 0 : $written;
			},
		]);

		$result = curl_exec($curl);
		if ($result === false) {
			$message = curl_error($curl);
			curl_close($curl);
			if (is_resource($handle)) {
				fclose($handle);
			}
			throw new HttpException(sprintf('HTTP download failed for %s: %s', $url, $message));
		}

		$statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		curl_close($curl);
		if (is_resource($handle)) {
			fclose($handle);
		}

		if ($statusCode === 200 || $statusCode === 206) {
			if (is_file($destinationPath) && !@unlink($destinationPath)) {
				throw new HttpException(sprintf('Failed to replace existing file %s', $destinationPath));
			}

			if (!@rename($temporaryPath, $destinationPath)) {
				throw new HttpException(sprintf('Failed to move %s to %s', $temporaryPath, $destinationPath));
			}
		}

		return new HttpResponse($statusCode, $responseHeaders);
	}

	private function getFileSize(string $path): int
	{
		if (!is_file($path)) {
			return 0;
		}

		$size = filesize($path);
		return is_int($size) && $size > 0 ? $size : 0;
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

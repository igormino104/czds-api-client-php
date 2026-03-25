<?php

declare(strict_types=1);

namespace CzdsPhp;

use RuntimeException;

final class CzdsDownloader
{
	private ?string $accessToken = null;

	public function __construct(
		private readonly HttpClient $httpClient,
		private readonly CzdsAuthenticator $authenticator,
		private readonly Config $config
	) {}

	public function run(): void
	{
		$this->log(sprintf('Authenticate user %s', $this->config->getUsername()));
		$this->accessToken = $this->authenticator->authenticate(
			$this->config->getUsername(),
			$this->config->getPassword()
		);
		$this->log('Authentication successful. Access token received.');

		$zoneLinks = $this->getZoneLinks();
		$filteredLinks = $this->filterLinks($zoneLinks);

		$this->log(sprintf(
			'The number of zone files to be downloaded is %d',
			count($filteredLinks)
		));

		if ($filteredLinks === []) {
			$this->log('No zone files matched the configured TLD filter.');
			return;
		}

		$outputDirectory = $this->config->getZoneFilesDirectory();
		if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
			throw new RuntimeException(sprintf('Failed to create output directory %s', $outputDirectory));
		}

		$startTime = microtime(true);
		foreach ($filteredLinks as $link) {
			$this->downloadOneZone($link, $outputDirectory);
		}

		$elapsedSeconds = microtime(true) - $startTime;
		$this->log(sprintf(
			'DONE DONE. Completed downloading all zone files. Time spent: %s',
			$this->formatDuration($elapsedSeconds)
		));
	}

	private function getZoneLinks(): array
	{
		$linksUrl = $this->config->getCzdsBaseUrl() . '/czds/downloads/links';

		$response = $this->authorizedRequest(fn(array $headers): HttpResponse => $this->httpClient->request(
			'GET',
			$linksUrl,
			$headers
		));

		return match ($response->getStatusCode()) {
			200 => $this->extractZoneLinks($response, $linksUrl),
			default => throw new RuntimeException(sprintf(
				'Failed to get zone links from %s with error code %d',
				$linksUrl,
				$response->getStatusCode()
			)),
		};
	}

	private function downloadOneZone(string $url, string $outputDirectory): void
	{
		$this->log(sprintf('Downloading zone file from %s', $url));

		$filename = $this->guessFilenameFromUrl($url);
		$destinationPath = $outputDirectory . DIRECTORY_SEPARATOR . $filename;

		$response = $this->authorizedRequest(fn(array $headers): HttpResponse => $this->httpClient->downloadToFile(
			$url,
			$headers,
			$destinationPath
		));

		if ($response->getStatusCode() === 200) {
			$contentDisposition = $response->getHeader('content-disposition');
			$resolvedFilename = $this->extractFilenameFromContentDisposition($contentDisposition) ?? $filename;

			if ($resolvedFilename !== basename($destinationPath)) {
				$newDestinationPath = $outputDirectory . DIRECTORY_SEPARATOR . $resolvedFilename;
				if (is_file($newDestinationPath)) {
					@unlink($newDestinationPath);
				}

				if (!@rename($destinationPath, $newDestinationPath)) {
					throw new RuntimeException(sprintf(
						'Failed to rename downloaded file %s to %s',
						$destinationPath,
						$newDestinationPath
					));
				}

				$destinationPath = $newDestinationPath;
			}

			$this->log(sprintf('Completed downloading zone to file %s', $destinationPath));
			return;
		}

		if ($response->getStatusCode() === 404) {
			$this->log(sprintf('No zone file found for %s', $url));
			return;
		}

		throw new RuntimeException(sprintf(
			'Failed to download zone from %s with code %d',
			$url,
			$response->getStatusCode()
		));
	}

	private function authorizedRequest(callable $requestCallback): HttpResponse
	{
		$response = $requestCallback($this->bearerHeaders());
		if ($response->getStatusCode() !== 401) {
			return $response;
		}

		$this->log(sprintf(
			'The access token has expired. Re-authenticate user %s',
			$this->config->getUsername()
		));

		$this->accessToken = $this->authenticator->authenticate(
			$this->config->getUsername(),
			$this->config->getPassword()
		);

		return $requestCallback($this->bearerHeaders());
	}

	private function bearerHeaders(): array
	{
		if ($this->accessToken === null || $this->accessToken === '') {
			throw new RuntimeException('No access token is available for the request');
		}

		return [
			'Content-Type' => 'application/json',
			'Accept' => 'application/json',
			'Authorization' => 'Bearer ' . $this->accessToken,
		];
	}

	private function extractZoneLinks(HttpResponse $response, string $linksUrl): array
	{
		$payload = $response->decodeJson();
		foreach ($payload as $link) {
			if (!is_string($link) || $link === '') {
				throw new RuntimeException(sprintf('Zone links response from %s contains an invalid entry', $linksUrl));
			}
		}

		return array_values($payload);
	}

	private function filterLinks(array $zoneLinks): array
	{
		$tlds = $this->config->getTlds();
		if ($tlds === []) {
			return $zoneLinks;
		}

		return array_values(array_filter(
			$zoneLinks,
			static function (string $link) use ($tlds): bool {
				foreach ($tlds as $tld) {
					if (str_ends_with($link, $tld . '.zone')) {
						return true;
					}
				}

				return false;
			}
		));
	}

	private function guessFilenameFromUrl(string $url): string
	{
		$path = parse_url($url, PHP_URL_PATH);
		if (!is_string($path) || $path === '') {
			throw new RuntimeException(sprintf('Unable to determine filename from url %s', $url));
		}

		$basename = basename($path);
		$filenameWithoutExtension = pathinfo($basename, PATHINFO_FILENAME);
		if ($filenameWithoutExtension === '') {
			throw new RuntimeException(sprintf('Unable to determine filename from url %s', $url));
		}

		return $filenameWithoutExtension . '.txt.gz';
	}

	private function extractFilenameFromContentDisposition(?string $headerValue): ?string
	{
		if ($headerValue === null || $headerValue === '') {
			return null;
		}

		if (preg_match('/filename\*=UTF-8\'\'([^;]+)/i', $headerValue, $matches) === 1) {
			$filename = basename(rawurldecode(trim($matches[1], "\"'")));
			return $filename !== '' ? $filename : null;
		}

		if (preg_match('/filename="?([^";]+)"?/i', $headerValue, $matches) === 1) {
			$filename = basename(trim($matches[1]));
			return $filename !== '' ? $filename : null;
		}

		return null;
	}

	private function formatDuration(float $elapsedSeconds): string
	{
		$seconds = (int) floor($elapsedSeconds);
		$milliseconds = (int) round(($elapsedSeconds - $seconds) * 1000);

		return sprintf('%d.%03ds', $seconds, $milliseconds);
	}

	private function log(string $message): void
	{
		fwrite(STDOUT, sprintf("%s: %s%s", date('Y-m-d H:i:s'), $message, PHP_EOL));
	}
}

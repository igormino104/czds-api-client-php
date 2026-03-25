<?php

declare(strict_types=1);

namespace CzdsPhp;

use CzdsPhp\Exception\ConfigException;

final class Config
{
	public function __construct(
		private readonly string $username,
		private readonly string $password,
		private readonly string $authenticationBaseUrl,
		private readonly string $czdsBaseUrl,
		private readonly string $workingDirectory,
		private readonly array $tlds
	) {}

	public static function fromArray(array $config): self
	{
		$requiredKeys = [
			'icann.account.username',
			'icann.account.password',
			'authentication.base.url',
			'czds.base.url',
		];

		foreach ($requiredKeys as $requiredKey) {
			$value = $config[$requiredKey] ?? null;
			if (!is_string($value) || trim($value) === '') {
				throw new ConfigException(sprintf("'%s' parameter not found in the config.json file", $requiredKey));
			}
		}

		$workingDirectory = $config['working.directory'] ?? (getcwd() ?: '.');
		if (!is_string($workingDirectory) || trim($workingDirectory) === '') {
			throw new ConfigException("'working.directory' must be a non-empty string when provided");
		}

		$tlds = $config['tlds'] ?? [];
		if (!is_array($tlds)) {
			throw new ConfigException("'tlds' must be an array when provided");
		}

		$normalizedTlds = [];
		foreach ($tlds as $tld) {
			if (!is_string($tld) || trim($tld) === '') {
				throw new ConfigException("'tlds' must contain only non-empty strings");
			}

			$normalizedTlds[] = ltrim(trim($tld), '.');
		}

		return new self(
			trim($config['icann.account.username']),
			$config['icann.account.password'],
			rtrim(trim($config['authentication.base.url']), '/'),
			rtrim(trim($config['czds.base.url']), '/'),
			rtrim($workingDirectory, "/\\"),
			$normalizedTlds
		);
	}

	public function getUsername(): string
	{
		return $this->username;
	}

	public function getPassword(): string
	{
		return $this->password;
	}

	public function getAuthenticationBaseUrl(): string
	{
		return $this->authenticationBaseUrl;
	}

	public function getCzdsBaseUrl(): string
	{
		return $this->czdsBaseUrl;
	}

	public function getWorkingDirectory(): string
	{
		return $this->workingDirectory;
	}

	public function getZoneFilesDirectory(): string
	{
		return $this->workingDirectory . DIRECTORY_SEPARATOR . 'zonefiles';
	}

	public function getTlds(): array
	{
		return $this->tlds;
	}
}

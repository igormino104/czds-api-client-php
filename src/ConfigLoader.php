<?php

declare(strict_types=1);

namespace CzdsPhp;

use CzdsPhp\Exception\ConfigException;

final class ConfigLoader
{
	public function __construct(private readonly string $projectRoot) {}

	public function load(?string $explicitPath = null): Config
	{
		if ($explicitPath !== null && trim($explicitPath) !== '') {
			$configPath = $explicitPath;
		} else {
			$inlineConfig = getenv('CZDS_CONFIG');
			if ($inlineConfig !== false && trim($inlineConfig) !== '') {
				$decoded = json_decode($inlineConfig, true);
				if (!is_array($decoded)) {
					throw new ConfigException('Error loading config.json file: invalid JSON in CZDS_CONFIG');
				}

				return Config::fromArray($decoded);
			}

			$configPath = $this->resolveConfigPath(null);
		}

		$contents = @file_get_contents($configPath);
		if ($contents === false) {
			throw new ConfigException(sprintf('Error loading config.json file: unable to read %s', $configPath));
		}

		$decoded = json_decode($contents, true);
		if (!is_array($decoded)) {
			throw new ConfigException(sprintf('Error loading config.json file: invalid JSON in %s', $configPath));
		}

		return Config::fromArray($decoded);
	}

	private function resolveConfigPath(?string $explicitPath): string
	{
		if ($explicitPath !== null && trim($explicitPath) !== '') {
			return $explicitPath;
		}

		$candidates = [];
		$workingDirectory = getcwd();
		if (is_string($workingDirectory) && $workingDirectory !== '') {
			$candidates[] = $workingDirectory . DIRECTORY_SEPARATOR . 'config.json';
		}

		$candidates[] = $this->projectRoot . DIRECTORY_SEPARATOR . 'config.json';

		foreach ($candidates as $candidate) {
			if (is_file($candidate)) {
				return $candidate;
			}
		}

		return $candidates[0] ?? ($this->projectRoot . DIRECTORY_SEPARATOR . 'config.json');
	}
}

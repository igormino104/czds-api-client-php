#!/usr/bin/env php
<?php

declare(strict_types=1);

$projectRoot = __DIR__;
$composerAutoload = $projectRoot . '/vendor/autoload.php';

if (is_file($composerAutoload)) {
	require $composerAutoload;
} else {
	spl_autoload_register(static function (string $class) use ($projectRoot): void {
		$prefix = 'CzdsPhp\\';
		if (!str_starts_with($class, $prefix)) {
			return;
		}

		$relativeClass = substr($class, strlen($prefix));
		$path = $projectRoot . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';
		if (is_file($path)) {
			require $path;
		}
	});
}

use CzdsPhp\ConfigLoader;
use CzdsPhp\CzdsAuthenticator;
use CzdsPhp\CzdsDownloader;
use CzdsPhp\HttpClient;

function print_usage(): void
{
	$scriptName = basename(__FILE__);
	$lines = [
		"Usage:",
		"  php {$scriptName}",
		"  php {$scriptName} --config=/path/to/config.json",
		"",
		"Configuration resolution order:",
		"  1. --config=/path/to/config.json",
		"  2. CZDS_CONFIG environment variable containing JSON",
		"  3. config.json in the current working directory",
		"  4. config.json next to this script",
	];

	fwrite(STDOUT, implode(PHP_EOL, $lines) . PHP_EOL);
}

try {
	$argv = $_SERVER['argv'] ?? [];
	if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
		print_usage();
		exit(0);
	}

	$configPath = null;
	foreach ($argv as $argument) {
		if (str_starts_with($argument, '--config=')) {
			$configPath = substr($argument, strlen('--config='));
		}
	}

	$configLoader = new ConfigLoader($projectRoot);
	$config = $configLoader->load($configPath);

	$httpClient = new HttpClient();
	$authenticator = new CzdsAuthenticator($httpClient, $config->getAuthenticationBaseUrl());
	$downloader = new CzdsDownloader($httpClient, $authenticator, $config);

	$downloader->run();
	exit(0);
} catch (Throwable $exception) {
	fwrite(STDERR, $exception->getMessage() . PHP_EOL);
	exit(1);
}

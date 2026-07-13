# Repository Guidelines

## Project Structure & Module Organization

This is a standalone PHP CLI client for downloading ICANN CZDS zone files.

- `download.php` is the CLI entrypoint and includes a fallback PSR-4 autoloader.
- `src/` contains application classes under the `CzdsPhp\` namespace.
- `src/Exception/` contains domain-specific exception classes.
- `composer.json` defines PHP 8.1 requirements and Composer autoload metadata.
- `config.sample.json` documents configuration; `config.json` is local runtime configuration.
- `downloaded/` and configured `zonefiles` directories are generated output.

## Build, Test, and Development Commands

- `composer install` installs Composer metadata and generates `vendor/autoload.php`.
- `php download.php --help` prints CLI usage and config resolution order.
- `php download.php --config=/path/to/config.json` runs with an explicit config file.
- `php -l download.php` checks the entrypoint for PHP syntax errors.
- `php -l src/Config.php` checks an individual source file; repeat for edited PHP files.

There is no build step. The project can run without Composer.

## Coding Style & Naming Conventions

- Start PHP files with `<?php` and `declare(strict_types=1);`.
- Use the `CzdsPhp` namespace and PSR-4 class-to-file mapping, for example `CzdsPhp\HttpClient` in `src/HttpClient.php`.
- Keep classes `final` unless extension is required.
- Prefer typed properties, return types, constructor property promotion, and small private helper methods.
- Existing files use tabs for indentation; preserve that style when editing.
- Use descriptive exception messages and avoid logging secrets such as access tokens or passwords.

## Testing Guidelines

No automated test framework is configured. At minimum, run syntax checks on every edited PHP file:

```bash
php -l download.php
php -l src/CzdsDownloader.php
```

When changing download, authentication, or config behavior, validate manually with safe credentials and a limited `tlds` list.

## Commit & Pull Request Guidelines

Recent commits use short summaries such as `added response body for better debug after failed of authenticate`. Keep subjects concise and focused on one behavior change.

Pull requests should include:

- A short description of the user-visible change.
- Any config keys, environment variables, or command examples affected.
- Verification steps and command output, especially `php -l` results.
- Notes on API-facing behavior changes, retries, output paths, or credential handling.

## Security & Configuration Tips

Do not commit real credentials in `config.json`. Use `config.sample.json` for documentation and `CZDS_CONFIG` or a local untracked config for secrets. Keep downloaded zone files out of source changes unless explicitly required.

# CZDS API Client in PHP

This directory contains a standalone CLI PHP rewrite of the original Python example client for downloading CZDS (Centralized Zone Data Service) zone files through the ICANN REST API.

## What It Does

The PHP client performs the same functional flow as the Python project:

1. Loads configuration from `config.json` or the `CZDS_CONFIG` environment variable.
2. Authenticates against the ICANN account API and obtains a bearer token.
3. Requests the list of downloadable CZDS zone file links.
4. Optionally filters links by configured TLDs.
5. Downloads the matching zone files into `working.directory/zonefiles`.

## Requirements

- PHP 8.1 or newer
- PHP `curl` extension
- PHP `json` extension
- Optional: Composer, if you want standard autoload metadata available

## Installation

1. Change into the PHP project directory:

   ```bash
   cd php
   ```

2. Copy the sample config:

   ```bash
   cp config.sample.json config.json
   ```

   On Windows PowerShell:

   ```powershell
   Copy-Item config.sample.json config.json
   ```

3. Edit `config.json` and fill in your ICANN credentials and preferred output location.

4. Optional: install Composer metadata locally:

   ```bash
   composer install
   ```

   The project also runs without Composer because `download.php` contains a fallback autoloader.

## Configuration

The PHP version keeps the same JSON structure and key names as the original Python sample:

```json
{
  "icann.account.username": "username@example.com",
  "icann.account.password": "Abcdef#12345678",
  "authentication.base.url": "https://account-api.icann.org",
  "czds.base.url": "https://czds-api.icann.org",
  "working.directory": "/where/zonefiles/will/be/saved",
  "_comment": "Optional tlds: to specify a subset of tlds to download. Missing or empty [] means downloading all APPROVED tlds.",
  "tlds": []
}
```

Notes:

- `working.directory` is optional. If missing, the current working directory is used, matching the Python behavior.
- `tlds` is optional. Missing or empty `[]` means download all approved zone files.
- `CZDS_CONFIG` can be used to pass the entire configuration as a JSON string.

## Running

Basic execution:

```bash
php download.php
```

Explicit config file:

```bash
php download.php --config=/path/to/config.json
```

Help:

```bash
php download.php --help
```

The zone files are written into:

```text
<working.directory>/zonefiles
```

## Examples

Download all approved zone files:

```bash
php download.php
```

Download only selected TLDs by setting `tlds` in `config.json`:

```json
{
  "tlds": ["com", "net", "org"]
}
```

Run with inline JSON config:

```bash
export CZDS_CONFIG='{"icann.account.username":"user@example.com","icann.account.password":"secret","authentication.base.url":"https://account-api.icann.org","czds.base.url":"https://czds-api.icann.org","working.directory":"/tmp","tlds":["com"]}'
php download.php
```

## Differences From The Python Version

- Authentication, download handling, and retries are preserved, but the PHP rewrite avoids printing the raw access token to the console.
- The PHP entrypoint supports `--config=/path/to/config.json` as a practical CLI addition.
- Config lookup is slightly more forgiving: after `CZDS_CONFIG`, it checks `config.json` in the current working directory and then next to `download.php`.
- HTTP 401 responses trigger a single re-authentication and retry, which fixes the recursive retry edge case present in the Python version.

## Project Structure

- `download.php`: CLI entrypoint
- `src/Config.php`: validated runtime configuration object
- `src/ConfigLoader.php`: config resolution and JSON loading
- `src/HttpClient.php`: cURL-based HTTP communication
- `src/HttpResponse.php`: HTTP response wrapper
- `src/CzdsAuthenticator.php`: authentication flow
- `src/CzdsDownloader.php`: zone link retrieval, filtering, and downloads

## Mapping From The Python Project

- `download.py` -> `download.php` + `src/CzdsDownloader.php`
- `do_authentication.py` -> `src/CzdsAuthenticator.php`
- `do_http_get.py` -> `src/HttpClient.php`
- `config.sample.json` -> `config.sample.json`

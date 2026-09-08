# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-07-09

This is a major release introducing multiple channel logging capabilities (Kafka, Elasticsearch, Database, File), asynchronous job dispatching with robust error fallback logging, and support for Laravel 12.x and PHP 8.2+.

### Added
- **Multiple Logging Channels**: Support for sending request/response logs to multiple channels simultaneously (configured via `FOOTPRINTS_CHANNELS`).
- **Kafka Integration**: Native Kafka channel driver for streaming footprints to Apache Kafka brokers (requires `ext-rdkafka` PHP extension).
- **Elasticsearch Integration**: Native Elasticsearch channel driver supporting indexing and push to data streams.
- **File Logging Channel**: Dedicated file channel driver to persist request footprints locally, with validation to prevent disk exhaustion (`FileChannelValidator`).
- **Service Reachability & Health Monitors**: Added robust TCP checks for Kafka brokers and HTTP checks for Elasticsearch nodes to ensure connectivity before logging.
- **Fallback Logging**: Implemented a fallback mechanism in `ProcessFootprintJob` that automatically falls back to local file logging if all primary logging channels fail.
- **Rich Request Metadata**: Expanded logged request details to include `request_headers`, `environment`, `user_agent`, `host_name`, `host_ip`, and `ip_address`.
- **Dynamic Table Naming**: Configurable database table name using the `FOOTPRINTS_TABLE_NAME` environment variable.
- **Migration Renaming on Publish**: Middleware/Service provider now renames migrations upon publishing (`php artisan vendor:publish`) to match the latest timestamp and avoid migration order clashes.
- **Unit and Integration Tests**: Added a full test suite covering `CaptureFootprintsMiddleware`, `ProcessFootprintJob`, header redaction, and Service Provider registration.

### Changed
- **Modernized Middleware**: Replaced legacy `WriteFootprints` middleware with `CaptureFootprintsMiddleware` featuring cleaner payload construction and automatic `X-Request-ID` generation.
- **Configuration Overhaul**: Renamed the config file from `src/config/config.php` to `src/config/footprints.php` and structured config keys for all channel drivers.
- **Direct Query Builder Insertion**: Replaced Eloquent model logging with raw Query Builder inserts in `DatabaseChannel` to avoid model overhead and improve logging speed.
- **Dependencies Upgrade**:
  - Required PHP version bumped to `^8.2`.
  - Added support for Illuminate components `^9.0|^10.0|^11.0|^12.0` (Laravel 9.x - 12.x).
  - Configured `orchestra/testbench` up to `^10.0` and PHPUnit up to `^10.0` for testing compatibility.
- **Improved Redaction Engine**: Upgraded sensitive data masking to recursively mask nested arrays, support hyphenated header/parameter matching, and handle multiple config-defined mask keys.

### Removed
- **Eloquent Model**: Deleted the `Footprint` Eloquent model and associated factory (`FootprintFactory`) in favor of direct Query Builder insertion.
- **Legacy Files**: Cleaned up the old `WriteFootprints` middleware, config layout, and unused editor configuration files.

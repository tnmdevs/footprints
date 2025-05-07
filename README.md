## Prerequisites

```bash
composer require elasticsearch/elasticsearch monolog/monolog
```

## Create/Configure the `.env` File

Add the following environment variables:

```env
ES_SCHEME=http
ES_USERNAME=your_username
ES_PASSWORD=your_password
ES_NODES=<HOST IP/NAME>
ES_PORT=9200
```

Replace `your_username`, `your_password`, and `HOST IP/NAME` with your actual Elasticsearch credentials and elastic host IP or name.

```env
FOOTPRINT_LOG_CHANNELS=file
```

Note 1: If you want to log to multiple channels, you can separate them with commas in the `FOOTPRINT_LOG_CHANNELS` environment variable. For example:

```env
FOOTPRINT_LOG_CHANNELS=file,database
```

Note 2: Removing `file` from the list will disable file logging of the footprints.

## Logging Configuration

Add the following channels to your `config/logging.php` file:

```php
'footprints' => ConfigUtils::getEsChannelConfig(indexName: 'footprints_es_index'),
'footprint_stack' => [
    'driver' => 'stack',
    'channels' => 'footprints',
    'ignore_exceptions' => true,
]
```

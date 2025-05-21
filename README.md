## Prerequisites

Install the Elasticsearch PHP client and Monolog:

```bash
composer require elasticsearch/elasticsearch monolog/monolog
````

---

## Configure Your Environment (`.env`)

Create or update your project’s `.env` file with:

```env
# Elasticsearch connection
ES_SCHEME=http
ES_USERNAME=your_username
ES_PASSWORD=your_password
ES_NODES=your_host_or_ip
ES_PORT=9200

# Choose one (uncomment the appropriate line):
ES_OPERATION_TYPE=create   # for data-stream–based operations
# ES_OPERATION_TYPE=index  # for index-based operations

# Logging channels for footprints
# Separate multiple channels with commas (no spaces):
FOOTPRINT_LOG_CHANNELS=file,database
````

> Tip: Removing `file` from `FOOTPRINT_LOG_CHANNELS` will disable file logging of footprints.

---

## Logging Channel Configuration (`config/logging.php`)

Open `config/logging.php` and add the new channels under the existing `channels` array:

```php
return [
    // …

    'channels' => [
        // … existing channels …

        'footprints' => ConfigUtils::getEsChannelConfig(
            indexName: 'footprints_es_index'
        ),

        'footprint_stack' => [
            'driver'            => 'stack',
            'channels'          => ['footprints'],
            'ignore_exceptions' => true,
        ],
    ],

    // …
];
```

* **`footprints`**: sends logs directly to Elasticsearch.
* **`footprint_stack`**: wraps the `footprints` channel in a stack so you can easily add more channels (e.g. file, database) later without breaking things.

---

## How to Verify

1. **Generate a test log**
   In your application code (e.g. in a controller or command), add:

   ```php
   use Illuminate\Support\Facades\Log;

   Log::channel('footprint_stack')->info('Test footprint log', [
       'user_id'   => 123,
       'action'    => 'verify_es_logging',
       'timestamp' => now()->toIso8601String(),
   ]);
   ```

2. **Check your logs**

   * **File**: if `file` is enabled, open `storage/logs/laravel.log` and look for the “Test footprint log” entry.
   * **Elasticsearch**: run a quick curl from your terminal:

     ```bash
     curl -u your_username:your_password \
          -X GET "http://your_host_or_ip:9200/footprints_es_index/_search?q=verify_es_logging&pretty"
     ```


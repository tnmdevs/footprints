<?php
namespace TNM\Footprints\Utils;

use Elastic\Elasticsearch\ClientBuilder;
use Monolog\Formatter\ElasticsearchFormatter;
use TNM\Footprints\Logging\ElasticsearchHandler;

final class ConfigUtils
{
    private function __construct() // Disable direct class instantiation
    {
    }

    public static function getEsChannelConfig(string $indexName): array
    {
        $client = ClientBuilder::create()
            ->setHosts([
                env('ES_SCHEME', 'http') . '://' .
                env('ES_USERNAME', 'elastic') . ':' .
                env('ES_PASSWORD', 'changeme') . '@' .
                env('ES_NODES', 'localhost') . ':' .
                env('ES_PORT', 9200),
            ])
            ->build();

        return [
            'driver' => 'monolog',
            'level' => 'debug',
            'handler' => ElasticsearchHandler::class,
            'formatter' => ElasticsearchFormatter::class,
            'formatter_with' => [
                'index' => $indexName,
                'type' => '_doc',
            ],
            'handler_with' => [
                'client' => $client,
            ],
        ];
    }

}
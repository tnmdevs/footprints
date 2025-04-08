<?php
namespace TNM\Footprints\Utils;

use Elastic\Elasticsearch\ClientBuilder;
use Monolog\Handler\ElasticsearchHandler;
use Monolog\Formatter\ElasticsearchFormatter;

final class ConfigUtils
{
    private function __construct() // Disable direct class instantiation
    {
    }

    public static function getEsChannelConfig(string $indexName): array
    {
        $client = ClientBuilder::create()
            ->setHosts([
                env('ES_SCHEME') . '://' .
                env('ES_USERNAME') . ':' .
                env('ES_PASSWORD') . '@' .
                env('ES_NODES') . ':' .
                env('ES_PORT'),
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
<?php
namespace TNM\Footprints\Logging;

use Throwable;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\ElasticsearchHandler as BaseHandler;

final class ElasticsearchHandler extends BaseHandler
{
    protected function bulkSend(array $records): void
    {
        try {
            parent::bulkSend($records);
        } catch (Throwable $e) {
            Log::channel('daily')
                ->warning('Elasticsearch logging failed: ' . $e->getMessage(), [
                    'exception' => $e,
                ]);
        }
    }
}
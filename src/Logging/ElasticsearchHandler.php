<?php
namespace TNM\Footprints\Logging;

use Throwable;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\ElasticsearchHandler as BaseHandler;

final class ElasticsearchHandler extends BaseHandler
{
    protected function bulkSend(array $records): void
    {
        try {

            if (isset($records[0])) {
                $datetime = $records[0]['datetime'] ?? (new DateTimeImmutable())
                    ->format(DateTimeInterface::ATOM);

                unset($records[0]['datetime']);

                $records[0]['@timestamp'] = $datetime;
            }

            parent::bulkSend($records);

        } catch (Throwable $e) {
            Log::channel('daily')
                ->warning('Elasticsearch logging failed: ' . $e->getMessage(), [
                    'exception' => $e,
                ]);
        }
    }
}
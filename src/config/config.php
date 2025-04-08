<?php

return [
    'retention_days' => env('FOOTPRINTS_RETENTION_DAYS', 30),
    'hidden_fields' => env('FOOTPRINTS_HIDDEN_FIELDS', 'password,pin,new_pin'),
    'log_footprints_to_file' => env('LOG_FOOTPRINT_TO_FILE',  true)
];
<?php

return [
    'retention_days' => env('FOOTPRINTS_RETENTION_DAYS', 30),
    'hidden_fields' => env('FOOTPRINTS_HIDDEN_FIELDS', 'password,pin,new_pin'),
];
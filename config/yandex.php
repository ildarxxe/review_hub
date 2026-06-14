<?php

return [
    'max_pages' => (int) env('YANDEX_MAPS_MAX_PAGES', 12),
    'timeout' => (int) env('YANDEX_MAPS_TIMEOUT', 20),
    'connect_timeout' => (int) env('YANDEX_MAPS_CONNECT_TIMEOUT', 5),
    'retry_times' => (int) env('YANDEX_MAPS_RETRY_TIMES', 2),
    'retry_delay' => (int) env('YANDEX_MAPS_RETRY_DELAY', 500),
];

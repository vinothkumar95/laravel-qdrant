<?php

return [
    'host' => env('QDRANT_HOST', 'http://localhost:6333'),
    'api_key' => env('QDRANT_API_KEY'),
    'timeout' => env('QDRANT_TIMEOUT', 10), // Default 10 seconds
];

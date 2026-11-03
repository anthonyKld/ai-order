<?php

declare(strict_types=1);

return [
    'key' => trim((string) env('GEMINI_API_KEY', '')),
    'model' => trim((string) env('GEMINI_MODEL', 'gemini-3.5-flash-lite')),
];

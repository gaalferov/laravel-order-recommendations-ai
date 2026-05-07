<?php

return [
    'ai_model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    'count' => (int) env('RECOMMENDATIONS_COUNT', 3),
];

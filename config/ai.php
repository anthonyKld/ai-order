<?php

declare(strict_types=1);

// TODO(Gemini): adicionar seleção de provedor e credenciais próprias; ver docs/TESTES-GEMINI.md.
// A configuração atual aceita somente OpenAI, inclusive nos testes reais.
return [
    'key' => trim((string) env('OPENAI_API_KEY', '')),
    'model' => trim((string) env('OPENAI_MODEL', '')),
];

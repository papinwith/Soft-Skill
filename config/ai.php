<?php
// Local Ollama server running the custom "Soft-Skill" model (built on Typhoon2-8B-Instruct via
// ./Modelfile — run `ollama create Soft-Skill -f Modelfile` to (re)build it).
return [
    'url'             => getenv('OLLAMA_URL') ?: 'http://127.0.0.1:11434/api/generate',
    'model'           => getenv('OLLAMA_MODEL') ?: 'Soft-Skill',
    'connect_timeout' => 3,
    'timeout'         => 60,
];

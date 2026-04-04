<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Default selected model
$my_default_model = 'gemma4:latest'; // Default model in the select box

// Ollama hosts — tried in order, first responding host is used
define('OLLAMA_HOSTS', [
    'http://localhost:11434',
    // 'http://192.168.1.98:11434',
]);

// Force the chatbox to one model
# define('FORCED_MODEL', 'gemma4:latest');// Set to a model name to lock the chatbot to one model, e.g. 'gemma4:latest'.
define('FORCED_MODEL', '');// Empty = allow all.
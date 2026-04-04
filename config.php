<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Default selected model
$my_default_model = 'gemma4:latest'; // Default model in the select box

// Ollama url
define('API_URL', 'http://localhost:11434/api/generate');

// Force the chatbox to one model
# define('FORCED_MODEL', 'gemma4:latest');// Set to a model name to lock the chatbot to one model, e.g. 'gemma4:latest'. 
define('FORCED_MODEL', '');// Empty = allow all.
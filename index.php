<?php
/**
 * Ollama PHP Chatbot
 *
 * This file contains the implementation of the Ollama PHP Chatbot.
 * The chatbot is designed to handle user interactions and provide
 * appropriate responses based on the input received.
 * 
 * Conversations are saved daily in files named with the format (yyyy-mm-dd.txt)
 *
 * @package OllamaPHPChatbot
 * @version 1.0
 * @license MIT License
 */

// Load config
require_once 'config.php';

// Load the OLLAMA library
require_once 'ollama.php';

// Check if debug mode is enabled
$debug_mode = isset($_GET['debug']) && $_GET['debug'] === 'true';

// Initialize the OLLAMA engine with debug mode
$ollama = new Ollama($debug_mode);

// Define the path to the markdown files
$markdown_dir = 'conversations';
if (!file_exists($markdown_dir)) {
    mkdir($markdown_dir, 0777, true);
}

// Get the current date for the conversation file
$current_date = date('Y-m-d');
$conversation_file = "$markdown_dir/$current_date.txt";

// Ensure all errors are caught and returned as JSON
function handleJsonError($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting
        return false;
    }
    
    $error_message = "PHP Error [$errno]: $errstr in $errfile on line $errline";
    error_log($error_message);
    
    // Only output JSON if this is a POST request (API call)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $error_message]);
        exit(1);
    }
    
    // Return true to indicate the error has been handled
    return true;
}

// Set custom error handler
set_error_handler('handleJsonError');

// Handle incoming messages
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ensure we always return JSON, even if there's a PHP error
    header('Content-Type: application/json');
    
    try {
        $raw_input = file_get_contents('php://input');
        if (empty($raw_input)) {
            throw new Exception('No input data received');
        }
        
        $data = json_decode($raw_input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON input: ' . json_last_error_msg());
        }
        
        if (!isset($data['model']) || !isset($data['message'])) {
            throw new Exception('Missing required fields: model and message');
        }
        
        $selected_model = (defined('FORCED_MODEL') && FORCED_MODEL !== '')
            ? htmlspecialchars(FORCED_MODEL)
            : htmlspecialchars($data['model']);
        $message = htmlspecialchars($data['message']);
        
        $response = $ollama->generateResponse($selected_model, $message);
        
        // Append the conversation to the markdown file without HTML color tags
        $conversation = "\n--------------------------------------------------------------------------------\n### $message\n\n".strtoupper($selected_model).":\n\n$response\n\n\n";
        file_put_contents($conversation_file, $conversation, FILE_APPEND);
        
        echo json_encode(['success' => true, 'response' => $response]);
    } catch (Exception $e) {
        error_log('Error in index.php: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Restore default error handler for the rest of the page
restore_error_handler();


// List all the models
if (defined('FORCED_MODEL') && FORCED_MODEL !== '') {
    $model_list = [['name' => FORCED_MODEL, 'description' => '']];
} else {
    $model_list = $ollama->getModelList();

    // Move default model to the beginning of the list
    $default_model_key = array_search($my_default_model, array_column($model_list, 'name'));
    if ($default_model_key !== false) {
        $default_model = $model_list[$default_model_key];
        unset($model_list[$default_model_key]);
        array_unshift($model_list, $default_model);
    }
}

// Get debug information if in debug mode
$debug_info = $debug_mode ? $ollama->getDebugInfo() : null;
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ollama-php-chatbot</title>
    <link rel="stylesheet" href="default.min.css">
    <script src="highlight.min.js"></script>
    <script src="marked.min.js"></script>
    <style>
        :root {
            --bg-color: #deddda;
            --text-color: #333;
            --chat-bg: #e8e8e8;
            --code-bg: #f4f4f4;
            --user-message-color: #800000;
        }

        [data-theme="dark"] {
            --bg-color: #333;
            --text-color: #f4f4f4;
            --chat-bg: #444;
            --code-bg: #222;
            --user-message-color: #ff6b6b;
        }
        
        /* Make markdown links red in dark mode for better visibility */
        [data-theme="dark"] #chat-window a {
            color: #ff3333;
        }

        body { 
            background-color: var(--bg-color); 
            color: var(--text-color);
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 0; 
            height: 94vh; 
            display: flex; 
            flex-direction: column; 
        }
        .container { 
            min-width: 90%; 
            margin: 0 auto; 
            padding: 20px; 
            flex-grow: 1; 
            display: flex; 
            flex-direction: column; 
        }
        @media (max-width: 768px) { .container { max-width: 100%; } }
        #chat-window { 
            background-color: var(--chat-bg); 
            flex-grow: 1; 
            border: 1px solid #ccc; 
            overflow-y: scroll; 
            padding: 10px; 
            margin-bottom: 10px; 
        }
        #chat-input { 
            width: 94%; 
            padding: 10px; 
            margin-bottom: 10px; 
            background-color: var(--bg-color);
            color: var(--text-color);
            border: 1px solid var(--text-color);
        }
        #send-chat, #change-model { padding: 10px 20px; }
        #model-select { 
            padding: 10px; 
            margin-bottom: 10px; 
            background-color: var(--bg-color);
            color: var(--text-color);
            border: 1px solid var(--text-color);
        }
        .error { color: red; }
        .user-message { color: var(--user-message-color); }
        pre { background-color: var(--code-bg); padding: 10px; border-radius: 5px; }
        code { font-family: 'Courier New', Courier, monospace; }
        #theme-toggle {
            position: fixed;
            top: 20px;
            right: 0px;
            background: none;
            border: none;
            font-size: 16px;
            cursor: pointer;
        }
        #debug-info {
            background-color: var(--chat-bg);
            border: 1px solid var(--text-color);
            padding: 10px;
            margin-top: 20px;
            white-space: pre-wrap;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <button id="theme-toggle">💡</button>
    <div class="container">
        <?php if (defined('FORCED_MODEL') && FORCED_MODEL !== ''): ?>
            <input type="hidden" id="model-select" value="<?= htmlspecialchars(FORCED_MODEL) ?>">
        <?php else: ?>
            <select id="model-select">
                <?php foreach ($model_list as $model): ?>
                    <option value="<?= htmlspecialchars($model['name']) ?>"><?= htmlspecialchars($model['name']) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        
        <div id="chat-window"></div>
        <textarea id="chat-input" placeholder="Type a message (Ctrl + Enter to send)"  rows="13"></textarea>
        <button id="send-chat">Send</button>

        <?php if ($debug_mode && $debug_info): ?>
            <div id="debug-info">
                <h3>Debug Information:</h3>
                <pre><?= htmlspecialchars(print_r($debug_info, true)) ?></pre>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const chatWindow = document.getElementById('chat-window');
        const chatInput = document.getElementById('chat-input');
        const sendButton = document.getElementById('send-chat');
        const modelSelect = document.getElementById('model-select');
        const themeToggle = document.getElementById('theme-toggle');
        let currentModel = modelSelect.value; // Set default model to the first option (Llama3.1)

        function appendMessage(sender, message, isError = false, isUser = false) {
            const messageDiv = document.createElement('div');
            if (isUser) {
                message = message.replace(/\r\n|\n/g, '<br>');
                messageDiv.innerHTML = `<br><hr><br><span class="user-message">${message}</span>`;
            } else {
                messageDiv.innerHTML = `<strong>${sender.toUpperCase()}:</strong> ${marked.parse(message)}`;
            }
            if (isError) {
                messageDiv.classList.add('error');
            }
            chatWindow.appendChild(messageDiv);
            chatWindow.appendChild(document.createElement('br')); // Add a line break after each message
            chatWindow.scrollTop = chatWindow.scrollHeight;
            hljs.highlightAll();
        }

        function sendMessage() {
            const message = chatInput.value.trim();
            if (message) {
                appendMessage('Usuario', message, false, true);
                chatInput.value = '';

                fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ model: currentModel, message: message }),
                })
                .then(response => {
                    // Check if the response is valid before trying to parse JSON
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    
                    // First try to get the text content
                    return response.text().then(text => {
                        try {
                            // Try to parse as JSON
                            return JSON.parse(text);
                        } catch (e) {
                            // If parsing fails, throw an error with the raw text
                            console.error('Failed to parse JSON:', text);
                            throw new Error('Server returned invalid JSON: ' + 
                                (text.length > 100 ? text.substring(0, 100) + '...' : text));
                        }
                    });
                })
                .then(data => {
                    if (data.success) {
                        appendMessage(currentModel, data.response);
                    } else {
                        appendMessage('Error', data.error, true);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    appendMessage('Error', 'Failed to send message: ' + error.message, true);
                    
                    // Check if Ollama is running
                    appendMessage('System', 'Make sure Ollama is running on your system. You can start it by running "ollama serve" in a terminal.', true);
                });
            }
        }

        sendButton.addEventListener('click', sendMessage);
        chatInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && e.ctrlKey) {
            e.preventDefault();
            sendMessage();
            }
        });

        modelSelect.addEventListener('change', function() {
            currentModel = modelSelect.value;
            appendMessage('<hr>System', `Changed model to ${currentModel}`);
        });

        // Theme toggle functionality
        function setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            themeToggle.textContent = theme === 'light' ? '💡' : '🌙';
        }

        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            setTheme(newTheme);
        });

        // Check for saved theme preference or use system preference
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            setTheme(savedTheme);
        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            setTheme('dark');
        } else {
            setTheme('light');
        }

        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            if (!localStorage.getItem('theme')) {
                setTheme(e.matches ? 'dark' : 'light');
            }
        });
    </script>
</body>
</html>

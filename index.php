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

// Define the path to the conversation files
$markdown_dir = 'conversations';
$init_warning = null;

if (!file_exists($markdown_dir)) {
    if (!@mkdir($markdown_dir, 0755, true)) {
        $init_warning = "WARNING: Cannot create folder '$markdown_dir/'. Fix with:\n  mkdir $markdown_dir && chown www-data:www-data ./$markdown_dir";
    }
}

if (!$init_warning && !is_writable($markdown_dir)) {
    $init_warning = "WARNING: Folder '$markdown_dir/' is not writable. Fix with:\n  chown www-data:www-data ./$markdown_dir -R";
}

$ext = defined('CONVERSATION_EXT') ? ltrim(CONVERSATION_EXT, '.') : 'md';
$current_date = date('Y-m-d');
$conversation_file = "$markdown_dir/$current_date.$ext";

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
        
        // Append the conversation to the log file
        $model_label = preg_replace('/:latest$/i', '', $selected_model);
        $conversation = "\n--------------------------------------------------------------------------------\n# $message\n\n".strtoupper($model_label).":\n\n$response\n\n\n";
        $write_result = @file_put_contents($conversation_file, $conversation, FILE_APPEND);
        $write_warning = ($write_result === false)
            ? "WARNING: Could not write to '$conversation_file'. Fix with:\n  chown www-data:www-data ./conversations -R"
            : null;

        echo json_encode(['success' => true, 'response' => $response, 'warning' => $write_warning]);
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

// Read version
$app_version = trim(file_get_contents('VERSION.md') ?: 'unknown');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ollama-php-chatbot</title>
    <link rel="stylesheet" href="default.min.css">
    <script src="highlight.min.js"></script>
    <script src="marked.min.js"></script>
    <link id="theme-css" rel="stylesheet" href="theme-dark.css">
    <style>
        body {
            background: var(--bg-body, #deddda);
            color: var(--text-primary, #333);
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
            background-color: var(--bg-content, #e8e8e8);
            flex-grow: 1;
            border: 1px solid var(--border, #ccc);
            overflow-y: scroll;
            padding: 10px;
            margin-bottom: 10px;
        }
        #chat-window a { color: var(--link-color, blue); }
        #chat-input {
            /*width: 94%;*/
            padding: 10px;
            margin-bottom: 10px;
            background-color: var(--bg-input, #deddda);
            color: var(--text-input, #333);
            border: 1px solid var(--border-input, #333);
        }
        #send-chat, #change-model { padding: 10px 20px; }
        #model-select {
            padding: 10px;
            margin-bottom: 10px;
            background-color: var(--bg-select, #deddda);
            color: var(--text-select, #333);
            border: 1px solid var(--border, #333);
        }
        .error { color: red; }
        .user-message { color: var(--url-color, #800000); }
        pre { background-color: var(--bg-code, #f4f4f4); padding: 10px; border-radius: 5px; }
        code { font-family: 'Courier New', Courier, monospace; }
        #debug-info {
            background-color: var(--bg-content, #e8e8e8);
            border: 1px solid var(--border, #ccc);
            padding: 10px;
            margin-top: 20px;
            white-space: pre-wrap;
            font-family: monospace;
        }

        /* Hamburger menu */
        #hamburger-menu {
            position: fixed;
            top: 12px;
            right: 12px;
            z-index: 1000;
        }
        #hamburger-btn {
            background: var(--burger-bg, #444);
            color: var(--burger-bar, white);
            border: none;
            font-size: 20px;
            width: 42px;
            height: 42px;
            border-radius: 6px;
            cursor: pointer;
            line-height: 1;
        }
        #hamburger-btn:hover {
            background: var(--burger-hover-bg, #222);
        }
        #hamburger-panel {
            display: none;
            position: absolute;
            right: 0;
            top: 50px;
            background: var(--bg-dropdown, #fff);
            border: 1px solid var(--border, #ccc);
            border-radius: 8px;
            padding: 16px;
            min-width: 200px;
            box-shadow: 0 4px 16px var(--shadow-dropdown, rgba(0,0,0,0.2));
        }
        #hamburger-panel.open { display: block; }
        #hamburger-panel label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            color: var(--text-secondary, #555);
        }
        #theme-select {
            width: 100%;
            padding: 8px;
            background-color: var(--bg-select, #fff);
            color: var(--text-select, #333);
            border: 1px solid var(--border, #ccc);
            border-radius: 4px;
            cursor: pointer;
        }
        @media (max-width: 480px) {
            #hamburger-panel { min-width: 160px; }
        }

        /* About button inside hamburger */
        #home-link, #docs-link {
            display: block;
            width: 100%;
            margin-bottom: 12px;
            padding: 8px;
            background: var(--bg-select, #eee);
            color: var(--text-select, #333);
            border: 1px solid var(--border, #ccc);
            border-radius: 4px;
            font-size: 13px;
            text-decoration: none;
            text-align: left;
        }
        #home-link:hover, #docs-link:hover { background: var(--bg-hover, #ddd); }
        #about-btn {
            display: block;
            width: 100%;
            margin-top: 12px;
            padding: 8px;
            background: var(--bg-select, #eee);
            color: var(--text-select, #333);
            border: 1px solid var(--border, #ccc);
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            text-align: left;
        }
        #about-btn:hover { background: var(--bg-hover, #ddd); }

        /* About modal */
        #about-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        #about-overlay.open { display: flex; }
        #about-modal {
            background: var(--bg-dropdown, #fff);
            color: var(--text-primary, #333);
            border: 1px solid var(--border, #ccc);
            border-radius: 10px;
            padding: 28px 32px;
            min-width: 260px;
            max-width: 90vw;
            box-shadow: 0 8px 32px var(--shadow-modal, rgba(0,0,0,0.3));
            text-align: center;
        }
        #about-modal h2 {
            margin: 0 0 8px;
            color: var(--text-primary, #333);
            font-size: 1.2em;
        }
        #about-modal .about-version {
            font-size: 0.95em;
            color: var(--text-secondary, #888);
            margin-bottom: 20px;
            font-family: monospace;
        }
        #about-close {
            padding: 8px 24px;
            background: var(--accent, #444);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        #about-close:hover { background: var(--accent-hover, #222); }
    </style>
</head>
<body>
    <div id="hamburger-menu">
        <button id="hamburger-btn" aria-label="Open menu">&#9776;</button>
        <div id="hamburger-panel">
            <a href="index.php" id="home-link">🏠 Homepage</a>
            <?php if (defined('DOCS_SCAN_DIRS') && count(DOCS_SCAN_DIRS) > 0): ?>
            <a href="docs.php" id="docs-link">📂 Conversations</a>
            <?php endif; ?>
            <label for="theme-select">Theme</label>
            <select id="theme-select">
                <option value="theme-dark">Dark</option>
                <option value="theme-dawn">Dawn</option>
                <option value="theme-dusk">Dusk</option>
                <option value="theme-fog">Fog</option>
                <option value="theme-light">Light</option>
                <option value="theme-winter">Winter</option>
            </select>
            <button id="about-btn">&#9432; About</button>
        </div>
    </div>

    <div id="about-overlay">
        <div id="about-modal">
            <h2>Ollama PHP Chatbot</h2>
            <div class="about-version">v<?= htmlspecialchars($app_version) ?></div>
            <button id="about-close">Close</button>
        </div>
    </div>
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
        <textarea id="chat-input" placeholder="Type a message (Ctrl + Enter to send)" rows="13"><?= $init_warning ? htmlspecialchars($init_warning) : '' ?></textarea>
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
                        if (data.warning) {
                            chatInput.value = data.warning;
                        }
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

        // Hamburger menu & theme switcher
        const hamburgerBtn = document.getElementById('hamburger-btn');
        const hamburgerPanel = document.getElementById('hamburger-panel');
        const themeSelect = document.getElementById('theme-select');
        const themeCss = document.getElementById('theme-css');

        hamburgerBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            hamburgerPanel.classList.toggle('open');
        });

        document.addEventListener('click', () => {
            hamburgerPanel.classList.remove('open');
        });

        hamburgerPanel.addEventListener('click', (e) => {
            e.stopPropagation();
        });

        function applyTheme(theme) {
            themeCss.href = theme + '.css';
            themeSelect.value = theme;
            localStorage.setItem('theme', theme);
        }

        themeSelect.addEventListener('change', () => {
            applyTheme(themeSelect.value);
        });

        // Load saved theme or default to dark
        applyTheme(localStorage.getItem('theme') || 'theme-dark');

        // About modal
        const aboutBtn = document.getElementById('about-btn');
        const aboutOverlay = document.getElementById('about-overlay');
        const aboutClose = document.getElementById('about-close');

        aboutBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            hamburgerPanel.classList.remove('open');
            aboutOverlay.classList.add('open');
        });

        aboutClose.addEventListener('click', () => {
            aboutOverlay.classList.remove('open');
        });

        aboutOverlay.addEventListener('click', (e) => {
            if (e.target === aboutOverlay) aboutOverlay.classList.remove('open');
        });
    </script>
</body>
</html>

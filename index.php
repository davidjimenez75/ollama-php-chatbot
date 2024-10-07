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
ini_set('display_errors', 1);
error_reporting(E_ALL);
$my_default_model = 'llama3.2:latest'; // Default model in the select box

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

// Handle incoming messages
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (isset($data['model']) && isset($data['message'])) {
        $selected_model = htmlspecialchars($data['model']);
        $message = htmlspecialchars($data['message']);
        
        try {
            $response = $ollama->generateResponse($selected_model, $message);
            
            // Append the conversation to the markdown file without HTML color tags
            $conversation = "### $message\n\n**$selected_model:** $response\n\n";
            file_put_contents($conversation_file, $conversation, FILE_APPEND);
            
            echo json_encode(['success' => true, 'response' => $response]);
        } catch (Exception $e) {
            error_log('Error in index.php: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}


// List all the models
$model_list = $ollama->getModelList();

// Move Llama3.1 to the beginning of the list
$default_model_key = array_search($my_default_model, array_column($model_list, 'name'));
if ($default_model_key !== false) {
    $default_model = $model_list[$default_model_key];
    unset($model_list[$default_model_key]);
    array_unshift($model_list, $default_model);
}

// Get debug information if in debug mode
$debug_info = $debug_mode ? $ollama->getDebugInfo() : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ollama-php-chatbot</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/default.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
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
        <select id="model-select">
            <?php foreach ($model_list as $model): ?>
                <option value="<?= htmlspecialchars($model['name']) ?>"><?= htmlspecialchars($model['name']) ?></option>
            <?php endforeach; ?>
        </select>
        
        <div id="chat-window"></div>
        <textarea id="chat-input" placeholder="Type a message"  rows="13"></textarea>
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
                messageDiv.innerHTML = `<span class="user-message">${message}</span>`;
            } else {
                messageDiv.innerHTML = `<strong>${sender}:</strong> ${marked.parse(message)}`;
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
                .then(response => response.json())
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
                });
            }
        }

        sendButton.addEventListener('click', sendMessage);
        chatInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        modelSelect.addEventListener('change', function() {
            currentModel = modelSelect.value;
            appendMessage('System', `Changed model to ${currentModel}`);
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

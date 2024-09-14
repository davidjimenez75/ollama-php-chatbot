<?php

class Ollama {
    private $models;
    private $apiUrl = 'http://localhost:11434/api/generate';
    private $debug = false;

    // Constructor
    public function __construct($debug = false) {
        $this->debug = $debug;
        $this->loadModels();
    }

    // Load the list of available models
    private function loadModels() {
        $command = $this->getOllamaListCommand();
        $output = $this->executeCommand($command);
        if ($output === false) {
            throw new Exception("Failed to execute Ollama list command. Please ensure Ollama is installed and accessible.");
        }
        $this->models = $this->parseOllamaOutput($output);
    }

    // Get the appropriate command to list Ollama models based on the operating system
    private function getOllamaListCommand() {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows command
            return 'ollama list';
        } else {
            // Linux/Unix command
            return 'HOME=${HOME:-/root} ollama list 2>&1';
        }
    }

    // Execute a shell command safely and return its output
    private function executeCommand($command) {
        $output = shell_exec($command);
        if ($output === null && !empty(error_get_last())) {
            error_log("Error executing command: " . $command . ". Error: " . print_r(error_get_last(), true));
            return false;
        }
        if ($this->debug) {
            error_log("Raw output from Ollama command: " . $output);
        }
        return $output;
    }

    // Parse the output of the Ollama list command
    private function parseOllamaOutput($output) {
        $models = [];
        $lines = explode("\n", trim($output));
        
        // Skip the header line
        array_shift($lines);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Parse the line using tab as delimiter
            $parts = explode("\t", $line);
            if (count($parts) >= 4) {
                $modelName = trim($parts[0]);
                $modelSize = trim($parts[2]);
                $modelModified = trim($parts[3]);
                
                $models[] = [
                    'name' => $modelName,
                    'description' => "Size: $modelSize, Modified: $modelModified"
                ];
            }
        }
        
        if (empty($models)) {
            error_log("No valid models found in Ollama output: " . $output);
        }
        
        return $models;
    }

    // Get a list of available models
    public function getModelList() {
        return $this->models;
    }

    // Generate a response from the Ollama API
    public function generateResponse($modelName, $prompt) {
        $data = [
            'model' => $modelName,
            'prompt' => $prompt,
            'stream' => false
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            error_log('Failed to generate response: ' . curl_error($ch));
            throw new Exception('Failed to generate response: ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("HTTP Error: $httpCode. Response: $response");
            throw new Exception("HTTP Error: $httpCode");
        }

        $responseData = json_decode($response, true);
        if (isset($responseData['response'])) {
            return $responseData['response'];
        } else {
            error_log('Invalid response from Ollama API: ' . $response);
            throw new Exception('Invalid response from Ollama API: ' . $response);
        }
    }

    // Get debug information
    public function getDebugInfo() {
        return [
            'raw_output' => $this->executeCommand($this->getOllamaListCommand()),
            'parsed_models' => $this->models,
            'os' => PHP_OS,
            'command' => $this->getOllamaListCommand(),
            'home_env' => getenv('HOME'),
            'current_user' => get_current_user(),
            'php_user' => exec('whoami')
        ];
    }
}

// Test the Ollama class
if (php_sapi_name() === 'cli') {
    try {
        $ollama = new Ollama(true);  // Enable debug mode
        $models = $ollama->getModelList();
        echo "Installed Ollama models:\n";
        foreach ($models as $model) {
            echo "{$model['name']} - {$model['description']}\n";
        }
        
        // Print debug info
        $debugInfo = $ollama->getDebugInfo();
        echo "\nDebug Information:\n";
        echo "Raw output: " . $debugInfo['raw_output'] . "\n";
        echo "Parsed models: " . print_r($debugInfo['parsed_models'], true) . "\n";
        echo "OS: " . $debugInfo['os'] . "\n";
        echo "Command: " . $debugInfo['command'] . "\n";
        echo "HOME env: " . $debugInfo['home_env'] . "\n";
        echo "Current user: " . $debugInfo['current_user'] . "\n";
        echo "PHP user: " . $debugInfo['php_user'] . "\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

?>

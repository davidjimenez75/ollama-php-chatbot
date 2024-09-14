<?php

class Ollama {
    private $models;
    private $apiUrl = 'http://localhost:11434/api/generate';

    // Constructor
    public function __construct() {
        $this->loadModels();
    }

    // Load the list of available models
    private function loadModels() {
        $command = $this->getOllamaListCommand();
        $output = shell_exec($command);
        $this->models = $this->parseOllamaOutput($output);
    }

    // Get the appropriate command to list Ollama models based on the operating system
    private function getOllamaListCommand() {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows command
            return 'ollama list';
        } else {
            // Linux/Unix command
            return 'ollama list 2>&1';
        }
    }

    // Parse the output of the Ollama list command
    private function parseOllamaOutput($output) {
        $models = [];
        $lines = explode("\n", trim($output));
        
        // Skip the header line
        array_shift($lines);
        
        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line), 2);
            if (count($parts) >= 2) {
                $models[] = [
                    'name' => $parts[0],
                    'description' => $parts[1]
                ];
            }
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
}

// Test the Ollama class
if (php_sapi_name() === 'cli') {
    $ollama = new Ollama();
    $models = $ollama->getModelList();
    echo "Installed Ollama models:\n";
    foreach ($models as $model) {
        echo "{$model['name']} - {$model['description']}\n";
    }
}

?>

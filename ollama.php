<?php

class Ollama {
    private $models;
    private $apiUrl = 'http://localhost:11434/api/generate';

    public function __construct() {
        $this->loadModels();
    }

    private function loadModels() {
        $this->models = [
            ['name' => 'llama3:latest', 'description' => 'Llama 3 model'],
            ['name' => 'llama3.1:latest', 'description' => 'Llama 3.1 model'],
            ['name' => 'starcoder2:3b', 'description' => 'Starcoder 2 3B model'],
            ['name' => 'nomic-embed-text:latest', 'description' => 'Nomic Embed Text model']
        ];
    }

    public function getModelList() {
        return $this->models;
    }

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

?>

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
class Ollama {
	private $debug = false;
	private $models;
	private $hosts;
	private $activeHost;

	// Constructor
	public function __construct($debug = false) {
		$this->debug = $debug;
		$this->hosts = defined('OLLAMA_HOSTS') ? OLLAMA_HOSTS : ['http://localhost:11434'];
		$this->activeHost = $this->findWorkingHost();
		$this->loadModels();
	}

	// Iterate hosts and return the first one that responds to /api/tags
	private function findWorkingHost() {
		foreach ($this->hosts as $host) {
			$ch = curl_init(rtrim($host, '/') . '/api/tags');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
			curl_setopt($ch, CURLOPT_TIMEOUT, 5);
			$response = curl_exec($ch);
			$curlError = curl_errno($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if (!$curlError && $httpCode === 200) {
				if ($this->debug) {
					error_log("Active Ollama host: $host");
				}
				return $host;
			}

			if ($this->debug) {
				error_log("Host unreachable: $host (curl error: $curlError, http: $httpCode)");
			}
		}
		throw new Exception("No reachable Ollama host found. Tried: " . implode(', ', $this->hosts));
	}

	// Load the list of available models via REST API
	private function loadModels() {
		$url = rtrim($this->activeHost, '/') . '/api/tags';
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		$response = curl_exec($ch);
		$curlError = curl_errno($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($curlError || $httpCode !== 200) {
			throw new Exception("Failed to fetch model list from $url");
		}

		$data = json_decode($response, true);
		if (json_last_error() !== JSON_ERROR_NONE || !isset($data['models'])) {
			throw new Exception("Invalid response from /api/tags: " . $response);
		}

		$this->models = [];
		foreach ($data['models'] as $model) {
			$size = isset($model['size']) ? round($model['size'] / 1073741824, 1) . ' GB' : 'unknown';
			$modified = isset($model['modified_at']) ? substr($model['modified_at'], 0, 10) : '';
			$this->models[] = [
				'name' => $model['name'],
				'description' => "Size: $size, Modified: $modified",
			];
		}

		if ($this->debug) {
			error_log("Loaded " . count($this->models) . " models from $url");
		}
	}

	// Get a list of available models
	public function getModelList() {
		return $this->models;
	}

	// Generate a response from the Ollama API, with fallback to next host on failure
	public function generateResponse($modelName, $prompt) {
		$data = [
			'model'  => $modelName,
			'prompt' => $prompt,
			'stream' => false,
		];

		$remainingHosts = $this->hosts;

		// Try active host first, then fall back to others
		array_unshift($remainingHosts, $this->activeHost);
		$remainingHosts = array_unique($remainingHosts);

		foreach ($remainingHosts as $host) {
			$url = rtrim($host, '/') . '/api/generate';
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
			curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
			curl_setopt($ch, CURLOPT_TIMEOUT, 120);

			$response = curl_exec($ch);
			$curlError = curl_errno($ch);
			$curlErrorMessage = curl_error($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if ($curlError) {
				error_log("Host $host failed: $curlErrorMessage — trying next host.");
				continue;
			}

			if ($httpCode !== 200) {
				error_log("Host $host returned HTTP $httpCode — trying next host.");
				continue;
			}

			$responseData = json_decode($response, true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				error_log("Host $host returned invalid JSON — trying next host.");
				continue;
			}

			if (!isset($responseData['response'])) {
				error_log("Host $host response missing 'response' field — trying next host.");
				continue;
			}

			// Update active host to the one that worked
			$this->activeHost = $host;
			return $responseData['response'];
		}

		throw new Exception("All Ollama hosts failed to generate a response. Tried: " . implode(', ', $remainingHosts));
	}

	// Get debug information
	public function getDebugInfo() {
		return [
			'hosts'       => $this->hosts,
			'active_host' => $this->activeHost,
			'models'      => $this->models,
			'os'          => PHP_OS,
			'php_user'    => exec('whoami'),
		];
	}
}

// Test the Ollama class
if (php_sapi_name() === 'cli') {
	try {
		$ollama = new Ollama(true);
		$models = $ollama->getModelList();
		echo "Installed Ollama models:\n";
		foreach ($models as $model) {
			echo "{$model['name']} - {$model['description']}\n";
		}

		$debugInfo = $ollama->getDebugInfo();
		echo "\nDebug Information:\n";
		echo "Active host: " . $debugInfo['active_host'] . "\n";
		echo "All hosts: " . implode(', ', $debugInfo['hosts']) . "\n";
		echo "OS: " . $debugInfo['os'] . "\n";
		echo "PHP user: " . $debugInfo['php_user'] . "\n";
	} catch (Exception $e) {
		echo "Error: " . $e->getMessage() . "\n";
	}
}

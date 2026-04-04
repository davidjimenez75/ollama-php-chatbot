# Ollama PHP Chatbot

Welcome to **Ollama PHP Chatbot**, a mini program designed to facilitate chatting with Ollama. 

This application allows you to switch between different chat models seamlessly. 

Whether you're looking to integrate a chatbot into your website or simply experiment with various chat models, Ollama PHP Chatbot provides a straightforward and flexible solution.

## Features

- **Multiple Chat Models**: Easily switch between different chat models to suit your needs.
- **Simple Integration**: Integrate the chatbot into your PHP projects with minimal effort.
- **User-Friendly**: Designed to be easy to use and configure.
- **Conversation Logging**: All conversations are stored in markdown format by day, with files named `YYYY-MM-DD.txt`.

Get started with Ollama PHP Chatbot and enhance your chat experience today!

## Offline Usage

This application works **100% offline** — no internet connection required. All JavaScript and CSS assets (`highlight.min.js`, `marked.min.js`, `default.min.css`) are bundled in the project root. As long as Ollama is running locally, you can use the chatbot at `http://127.0.0.1` without any external dependencies.

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/davidjimenez75/ollama-php-chatbot.git
cd ollama-php-chatbot
```

### 2. Set write permissions on the conversations folder

The application stores daily conversation logs in `./conversations/`. Grant write access to your web server user:

```bash
chown www-data:www-data ./conversations -R
```

> If the `conversations/` folder does not exist yet, the application will create it automatically on first use — but you may need to run the `chown` command afterwards.

### 3. Configure (optional)

Edit `config.php` to adjust settings:

- `$my_default_model` — default model selected in the dropdown.
- `FORCED_MODEL` — set to a model name (e.g. `'gemma4:latest'`) to lock the chatbot to a single model and hide the selector. Leave empty to allow all models.

### 4. Start Ollama

```bash
ollama serve
```

Then open `http://127.0.0.1` in your browser.


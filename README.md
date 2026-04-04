# Ollama PHP Chatbot

Welcome to **Ollama PHP Chatbot**, a self-hosted PHP chat interface for [Ollama](https://ollama.com).

Connect to one or more local/intranet Ollama instances, chat with any installed model, and browse your conversation history — all from the browser with no internet required.

## Features

- **Multiple AI models** — switch between any model installed in Ollama
- **Multi-host support** — configure multiple Ollama hosts in `config.php`; the app automatically falls back to the next host if one is unreachable
- **Forced model** — optionally lock the chatbot to a single model via `FORCED_MODEL`
- **Conversation logging** — daily logs saved as `conversations/YYYY-MM-DD.md` (extension configurable)
- **Docs viewer** (`docs.php`) — browse conversation logs and thematic folders (`.md`, `.txt`, `.csv`, `.mermaid`) with a resizable sidebar, sort toggle, and auto-generated TOC
- **6 themes** — Dark, Light, Dawn, Dusk, Fog, Winter; selected from the hamburger menu
- **Hamburger menu** — Homepage, Conversations viewer, Theme selector, About (shows version)
- **100% offline** — all JS/CSS assets bundled locally, no CDN dependencies

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

> If the `conversations/` folder does not exist yet, the application will create it automatically on first use — but you may need to run the `chown` command afterwards. Permission errors are shown directly in the chat input box.

### 3. Configure (optional)

Edit `config.php` to adjust settings:

| Constant | Default | Description |
|---|---|---|
| `$my_default_model` | `'gemma4:latest'` | Default model pre-selected in the dropdown |
| `OLLAMA_HOSTS` | `['http://localhost:11434']` | List of Ollama hosts tried in order |
| `FORCED_MODEL` | `''` | Lock to one model and hide the selector. Empty = allow all |
| `CONVERSATION_EXT` | `'md'` | Log file extension: `'md'` or `'txt'` |
| `DOCS_SCAN_DIRS` | `['conversations']` | Folders shown in `docs.php` sidebar |

### 4. Start Ollama

```bash
ollama serve
```

Then open `http://127.0.0.1` in your browser.

## Docs viewer

Open `docs.php` to browse your conversation logs and any thematic folders you add (e.g. `rust/`, `php/`, `linux/`). Configure which folders appear by editing `DOCS_SCAN_DIRS` in `config.php`:

```php
define('DOCS_SCAN_DIRS', [
    'conversations',
    // 'rust',
    // 'php',
]);
```

Supported file types: `.md` (rendered), `.txt` (plain), `.csv` (table), `.mermaid` (diagram).

## Offline usage

This application works **100% offline** — no internet connection required. All assets (`highlight.min.js`, `marked.min.js`, `mermaid.min.js`, `default.min.css`) are bundled in the project root. As long as Ollama is running locally, the full chatbot and docs viewer work at `http://127.0.0.1`.

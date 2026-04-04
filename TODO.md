# TODO.md

---

### - [x] 2. [[docs/themes.md]] Create multiple theme-*.css on root folders

---

### - [x] 4. [[docs/multi-host.md]] Support multiple Ollama hosts with automatic fallback

Replace the single `API_URL` string constant with an `OLLAMA_HOSTS` array of base URLs in `config.php`:

```php
define('OLLAMA_HOSTS', [
    'http://localhost:11434',
    'http://192.168.1.10:11434',
]);
```

**Implementation notes:**

- Drop `shell_exec('ollama list')` in `ollama.php` — it only works locally. Replace `loadModels()` with a REST call to `GET /api/tags` on the active host, which works both locally and remotely.
- Add `findWorkingHost()` in the `Ollama` class: iterate `OLLAMA_HOSTS`, call `GET {host}/api/tags`, return and cache the first host that responds as `$this->activeHost`.
- Derive all endpoint URLs dynamically: `$this->activeHost . '/api/generate'` and `$this->activeHost . '/api/tags'`.
- On `generateResponse()` curl failure, remove the failed host from the list and retry with the next one.
- Remove the old `API_URL` constant from `config.php`.

---

### - [x] 3. [[docs/offline-assets.md]] Download all external JS and CSS files to the project root for offline use

Currently loaded from CDN in `index.php`:
- `https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/default.min.css`
- `https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js`
- `https://cdn.jsdelivr.net/npm/marked/marked.min.js`

Download them directly into the project root (`./`) and update the `<link>` and `<script>` tags in `index.php` to use local paths.

---

### - [x] 1. [[docs/config.php.md]] Create a constant on the config.php to force the chatbox only to one model

---


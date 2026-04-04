# TODO.md

---

### - [x] 6. [[docs/docs.md]] docs.php — document viewer for conversations and thematic folders

- Sidebar file tree with collapsible folders and sort toggle (▼/▲, saved in localStorage)
- Sidebar toggle button (◀/▶) in the content header to show/hide the tree panel
- Resizable sidebar — draggable with mouse and touch (tablet support)
- Supported file types: `.md`, `.txt`, `.csv`, `.mermaid`
- `.csv` renders as an HTML table; `.mermaid` renders as a diagram
- Auto-generated TOC from H1 headings (numbered, 2-line clamp, smooth scroll)
- Auto-loads today's conversation (`conversations/YYYY-MM-DD.md`) on startup if it exists
- 6 themes selectable from the content header (persisted in localStorage)
- Configurable scan folders via `DOCS_SCAN_DIRS` in `config.php`

---

### - [x] 5. [[docs/toc.md]] Auto-generate table of contents in docs.php from H1 headings

---

### - [x] 4. [[docs/multi-host.md]] Support multiple Ollama hosts with automatic fallback

- `OLLAMA_HOSTS` array in `config.php` replaces single `API_URL`
- `findWorkingHost()` probes each host via `GET /api/tags`, caches the first responding
- `loadModels()` switched from `shell_exec('ollama list')` to REST API — works remotely
- `generateResponse()` retries with next host on curl failure

---

### - [x] 3. [[docs/offline-assets.md]] Download all external JS and CSS files to the project root for offline use

- `highlight.min.js`, `marked.min.js`, `default.min.css` bundled in project root
- `mermaid.min.js` bundled for docs.php

---

### - [x] 2. [[docs/themes.md]] Create multiple theme-*.css on root folders

- 6 themes: `dark`, `light`, `dawn`, `dusk`, `fog`, `winter`
- Theme selector in hamburger menu (index.php) and content header (docs.php)
- Fog and Winter: removed gradients, fixed TOC link contrast

---

### - [x] 1. [[docs/config.php.md]] Create a constant on the config.php to force the chatbox only to one model

---

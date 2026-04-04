# TODO.md

---

### - [ ] 2. [[docs/themes.md]] Create multiple theme-*.css on root folders

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


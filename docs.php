<?php
/**
 * docs.php — Markdown & Mermaid viewer for all *.md and *.mermaid files in the project.
 *
 * Modes:
 *   GET (no params)         → render the page shell
 *   GET ?file=path/to/file  → return raw file content as JSON
 */

require_once __DIR__ . '/config.php';

const EXCLUDE_DIRS   = ['.git', 'node_modules', 'BookReader', 'BookReaderDemo', '.claude', 'lib'];
const SUPPORTED_EXTS = ['md', 'mermaid', 'txt', 'csv'];
const ROOT           = __DIR__;

// Scan theme-*.css files dynamically
$themeFiles = glob(__DIR__ . '/theme-*.css') ?: [];
$themeFiles = array_map('basename', $themeFiles);
sort($themeFiles, SORT_NATURAL | SORT_FLAG_CASE);

// --- AJAX mode: serve raw file content ---
if (isset($_GET['file']) && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
    header('Content-Type: application/json; charset=utf-8');
    $rel = $_GET['file'];
    $abs = realpath(ROOT . '/' . $rel);
    if ($abs === false || strpos($abs, ROOT) !== 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    if (!in_array($ext, SUPPORTED_EXTS, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Unsupported file type']);
        exit;
    }
    if (!is_file($abs)) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
        exit;
    }
    echo json_encode(['content' => file_get_contents($abs), 'ext' => $ext]);
    exit;
}

// --- Build file tree ---
function scanDocs(string $dir, string $root): array {
    $result = [];
    $items  = @scandir($dir);
    if ($items === false) return $result;

    $files   = [];
    $subdirs = [];

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $fullPath = $dir . '/' . $item;
        $rel      = ltrim(str_replace($root, '', $fullPath), '/');
        if (is_dir($fullPath)) {
            if (!in_array($item, EXCLUDE_DIRS, true)) {
                $subdirs[] = ['name' => $item, 'path' => $fullPath, 'rel' => $rel];
            }
        } elseif (is_file($fullPath)) {
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            if (in_array($ext, SUPPORTED_EXTS, true)) {
                $files[] = ['name' => $item, 'rel' => $rel, 'ext' => $ext];
            }
        }
    }

    usort($files,   fn($a, $b) => strcasecmp($a['name'], $b['name']));
    usort($subdirs, fn($a, $b) => strcasecmp($a['name'], $b['name']));

    // Root-level files first
    foreach ($files as $f) {
        $result[] = ['type' => 'file', 'name' => $f['name'], 'rel' => $f['rel'], 'ext' => $f['ext']];
    }
    foreach ($subdirs as $sd) {
        $children = scanDocs($sd['path'], $root);
        if ($children) {
            $result[] = ['type' => 'dir', 'name' => $sd['name'], 'rel' => $sd['rel'], 'children' => $children];
        }
    }
    return $result;
}

// Root-level *.md files only (no subdirs)
$rootFiles = [];
foreach (@scandir(ROOT) ?: [] as $item) {
    if ($item === '.' || $item === '..') continue;
    $fullPath = ROOT . '/' . $item;
    $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
    if (is_file($fullPath) && in_array($ext, SUPPORTED_EXTS, true)) {
        $rootFiles[] = ['type' => 'file', 'name' => $item, 'rel' => $item, 'ext' => $ext];
    }
}
usort($rootFiles, fn($a, $b) => strcasecmp($a['name'], $b['name']));

$tree = $rootFiles;

// Add configured scan directories
$scanDirs = defined('DOCS_SCAN_DIRS') ? DOCS_SCAN_DIRS : [];
foreach ($scanDirs as $dirName) {
    $dirPath = ROOT . '/' . $dirName;
    if (is_dir($dirPath)) {
        $children = scanDocs($dirPath, ROOT);
        if ($children) {
            $tree[] = ['type' => 'dir', 'name' => $dirName, 'rel' => $dirName, 'children' => $children];
        }
    }
}

function renderTree(array $nodes, int $depth = 0): string {
    $html = '';
    foreach ($nodes as $node) {
        if ($node['type'] === 'file') {
            $icon = match($node['ext']) {
                'mermaid' => '🔗',
                'csv'     => '📊',
                'txt'     => '📝',
                default   => '📄',
            };
            $html .= '<div class="doc-file" data-rel="' . htmlspecialchars($node['rel']) . '" data-ext="' . htmlspecialchars($node['ext']) . '" style="padding-left:' . ($depth * 14) . 'px">'
                   . '<span class="file-icon">' . $icon . '</span>'
                   . '<span class="file-name">' . htmlspecialchars($node['name']) . '</span>'
                   . '</div>';
        } else {
            $html .= '<div class="doc-dir" style="padding-left:' . ($depth * 14) . 'px">'
                   . '<span class="dir-toggle">▶</span>'
                   . '<span class="dir-name">' . htmlspecialchars($node['name']) . '</span>'
                   . '</div>'
                   . '<div class="dir-children collapsed">'
                   . renderTree($node['children'], $depth + 1)
                   . '</div>';
        }
    }
    return $html;
}

$sidebarHtml = renderTree($tree);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cuadernos Docs</title>
<?php
$defaultTheme = 'theme-winter.css';
$defaultCss = @file_get_contents(__DIR__ . '/' . $defaultTheme) ?: '';
// Extract :root block for inline injection
preg_match('/:root\s*\{([^}]+)\}/', $defaultCss, $rootMatch);
$inlineRoot = $rootMatch[1] ?? '';
?>
<style>
:root {<?php echo $inlineRoot; ?>}
</style>
<link rel="stylesheet" id="theme-css" href="<?php echo htmlspecialchars($defaultTheme); ?>">
<style>
/* Layout */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: var(--bg-body);
    color: var(--text-primary);
    height: 100vh;
    overflow: hidden;
}

#layout {
    display: flex;
    height: 100vh;
}

/* Sidebar */
#sidebar {
    width: 260px;
    min-width: 140px;
    max-width: 600px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    background: var(--bg-header);
    overflow: hidden;
}

/* Resize handle */
#sidebar-resizer {
    width: 5px;
    flex-shrink: 0;
    cursor: col-resize;
    background: var(--border);
    transition: background 0.15s;
    position: relative;
    z-index: 10;
}
#sidebar-resizer:hover,
#sidebar-resizer.dragging { background: var(--accent); }

#sidebar-header {
    padding: 10px 12px;
    font-weight: 600;
    font-size: 14px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    flex-shrink: 0;
    background: var(--bg-header);
}

#sidebar-header a {
    color: var(--text-primary);
    text-decoration: none;
    font-size: 16px;
}
#sidebar-header a:hover { color: var(--accent); }

#sidebar-tree {
    overflow-y: auto;
    flex: 1;
    padding: 6px 0;
}

/* Tree items */
.doc-file, .doc-dir {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    cursor: pointer;
    font-size: 13px;
    color: var(--text-primary);
    border-radius: 0;
    user-select: none;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.doc-file:hover { background: var(--hover-bg); }
.doc-file.active {
    background: var(--accent);
    color: #fff;
}

.doc-dir {
    font-weight: 600;
    color: var(--text-secondary);
    cursor: pointer;
}
.doc-dir:hover { background: var(--hover-bg); }

.dir-toggle {
    font-size: 10px;
    transition: transform 0.15s;
    flex-shrink: 0;
}
.doc-dir.open .dir-toggle { transform: rotate(90deg); }

.dir-children {
    overflow: hidden;
}
.dir-children.collapsed { display: none; }

.file-icon { flex-shrink: 0; font-size: 11px; }
.file-name { overflow: hidden; text-overflow: ellipsis; }

/* Content area */
#content-wrap {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

#content-header {
    padding: 8px 16px;
    background: var(--bg-header);
    border-bottom: 1px solid var(--border);
    font-size: 13px;
    color: var(--text-secondary);
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

#content-path { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: monospace; }

.theme-row {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}
.theme-row label { font-size: 12px; color: var(--text-secondary); white-space: nowrap; }
#theme-select {
    font-size: 12px;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: var(--bg-column, var(--bg-header));
    color: var(--text-primary);
    padding: 2px 4px;
    cursor: pointer;
}

#content {
    flex: 1;
    overflow-y: auto;
    padding: 2rem 2.5rem;
}

#content-inner {
    max-width: 860px;
    margin: 0 auto;
}
#content-inner.wide {
    max-width: 100%;
}

/* Loading / empty state */
#content-loading, #content-empty {
    color: var(--text-muted);
    font-style: italic;
    padding: 40px 0;
    text-align: center;
}

/* Markdown typography */
#content-inner h1 { font-size: 1.9em; margin: 0 0 .6em; padding-bottom: .3em; border-bottom: 1px solid var(--border); }
#content-inner h2 { font-size: 1.4em; margin: 1.5em 0 .5em; padding-bottom: .25em; border-bottom: 1px solid var(--border); }
#content-inner h3 { font-size: 1.15em; margin: 1.3em 0 .4em; }
#content-inner h4 { font-size: 1em;    margin: 1.1em 0 .35em; }
#content-inner p  { margin: 0 0 .9em; line-height: 1.7; }
#content-inner ul, #content-inner ol { margin: 0 0 .9em; padding-left: 1.6em; line-height: 1.7; }
#content-inner li { margin-bottom: .2em; }
#content-inner li > ul, #content-inner li > ol { margin-bottom: 0; }
#content-inner a  { color: var(--accent); }
#content-inner a:hover { color: var(--accent-hover); }
#content-inner hr { border: none; border-top: 1px solid var(--border); margin: 1.5em 0; }
#content-inner blockquote {
    border-left: 3px solid var(--border);
    margin: 0 0 .9em;
    padding: .4em .8em;
    color: var(--text-secondary);
}
#content-inner blockquote p { margin: 0; }
#content-inner img { max-width: 100%; }

/* Code */
#content-inner code {
    background: var(--bg-body);
    border: 1px solid var(--border);
    border-radius: 3px;
    padding: 1px 5px;
    font-size: .88em;
    font-family: 'Courier New', monospace;
}
#content-inner pre {
    background: var(--bg-body);
    border: 1px solid var(--border);
    border-radius: 5px;
    padding: 1em 1.2em;
    overflow-x: auto;
    margin: 0 0 1em;
}
#content-inner pre code {
    background: none;
    border: none;
    padding: 0;
    font-size: .87em;
}

/* Tables */
#content-inner table {
    border-collapse: collapse;
    width: 100%;
    margin: 0 0 1em;
    font-size: .92em;
}
#content-inner th, #content-inner td {
    border: 1px solid var(--border);
    padding: 6px 12px;
    text-align: left;
}
#content-inner th {
    background: var(--bg-header);
    font-weight: 600;
}
#content-inner tr:nth-child(even) { background: var(--bg-body); }

/* Mermaid */
#content-inner .mermaid {
    display: flex;
    justify-content: center;
    margin: 1em 0;
    overflow-x: auto;
}

/* Status tags: [ ] [_] [x] [>] [?] [!] */
.tag-todo, .tag-done, .tag-progress, .tag-question, .tag-alert {
    display: inline-block;
    font-family: 'Courier New', monospace;
    font-size: .82em;
    font-weight: 700;
    color: #fff;
    border-radius: 3px;
    padding: 1px 5px;
    vertical-align: baseline;
}
.tag-todo     { background: #dc3545; }
.tag-done     { background: #28a745; }
.tag-progress { background: #fd7e14; }
.tag-question { background: #007bff; }
.tag-alert    { background: #dc3545; animation: tag-blink 1s step-end infinite; }

@keyframes tag-blink {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0; }
}
</style>
</head>
<body>

<div id="layout">
  <!-- Sidebar -->
  <div id="sidebar">
    <div id="sidebar-header">
      <span>📚 Docs</span>
      <a href="./index.php" title="Back to Homepage">🏠</a>
    </div>
    <div id="sidebar-tree">
      <?= $sidebarHtml ?>
    </div>
  </div>

  <!-- Resize handle -->
  <div id="sidebar-resizer"></div>

  <!-- Main content -->
  <div id="content-wrap">
    <div id="content-header">
      <span id="content-path">—</span>
      <div class="theme-row">
        <label for="theme-select">Theme:</label>
        <select id="theme-select">
          <?php foreach ($themeFiles as $tf):
              $label = ucfirst(preg_replace('/^theme-(.+)\.css$/', '$1', $tf));
          ?>
          <option value="<?php echo htmlspecialchars($tf); ?>"<?php if ($tf === 'theme-winter.css'): ?> selected<?php endif; ?>><?php echo htmlspecialchars($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div id="content">
      <div id="content-inner">
        <div id="content-empty">Select a file from the sidebar.</div>
      </div>
    </div>
  </div>
</div>

<script src="marked.min.js"></script>
<script src="mermaid.min.js"></script>
<script>
(function () {
    'use strict';

    // --- Theme ---
    const LS_THEME = 'cuadernos-theme';
    const themeLink  = document.getElementById('theme-css');
    const themeSelect = document.getElementById('theme-select');

    function applyTheme(href) {
        themeLink.setAttribute('href', href);
        themeSelect.value = href;
        try { localStorage.setItem(LS_THEME, href); } catch(e) {}
    }

    const validThemes = Array.from(themeSelect.options).map(o => o.value);
    let stored = null;
    try { stored = localStorage.getItem(LS_THEME); } catch(e) {}
    if (stored && validThemes.includes(stored)) {
        applyTheme(stored);
    }

    themeSelect.addEventListener('change', () => applyTheme(themeSelect.value));

    // --- Mermaid + marked ---
    mermaid.initialize({ startOnLoad: false, theme: 'default' });
    marked.use({ async: false, gfm: true, breaks: false, html: true });

    // --- File loading ---
    let currentRel = null;

    async function loadFile(rel) {
        currentRel = rel;

        // Update sidebar active state
        document.querySelectorAll('.doc-file').forEach(el => {
            el.classList.toggle('active', el.dataset.rel === rel);
        });

        // Update header
        document.getElementById('content-path').textContent = rel;

        // Update URL and title
        const filename = rel.split('/').pop();
        document.title = filename + ' — Cuadernos Docs';
        history.pushState({ rel }, '', '?file=' + encodeURIComponent(rel));

        // Show loading
        const inner = document.getElementById('content-inner');
        inner.innerHTML = '<div id="content-loading">Loading…</div>';

        try {
            const resp = await fetch('?file=' + encodeURIComponent(rel), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            const data = await resp.json();
            if (data.error) throw new Error(data.error);

            await renderContent(data.content, data.ext, inner);
        } catch (e) {
            inner.innerHTML = '<div id="content-empty">Error loading file: ' + escHtml(e.message) + '</div>';
        }
    }

    function getFileDir(rel) {
        const parts = rel.split('/');
        parts.pop();
        return parts.join('/');
    }

    function resolveRelPath(href, dir) {
        if (!href || /^(https?:\/\/|\/|data:)/.test(href)) return href;
        return dir ? dir + '/' + href : href;
    }

    function escAttr(str) {
        return (str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function buildRenderer(fileDir) {
        const renderer = new marked.Renderer();
        renderer.image = ({ href, title, text }) => {
            const resolved = resolveRelPath(href, fileDir);
            if (resolved && /\.mermaid$/i.test(resolved)) {
                return `<div class="mermaid-ref" data-src="${escAttr(resolved)}"></div>`;
            }
            const t = title ? ` title="${escAttr(title)}"` : '';
            return `<img src="${escAttr(resolved)}" alt="${escAttr(text)}"${t} data-src-resolved style="max-width:100%;height:auto">`;
        };
        return renderer;
    }

    function fixSrcPaths(container, dir) {
        if (!dir) return;
        container.querySelectorAll('img[src]:not([data-src-resolved]), source[src], iframe[src], embed[src], audio[src], video[src]')
            .forEach(el => {
                const val = el.getAttribute('src');
                if (val && !/^(https?:\/\/|\/|data:)/.test(val))
                    el.setAttribute('src', dir + '/' + val);
            });
    }

    async function renderMermaidRefs(container) {
        const refs = [...container.querySelectorAll('.mermaid-ref')];
        await Promise.all(refs.map(async ref => {
            try {
                const resp = await fetch('?file=' + encodeURIComponent(ref.dataset.src), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await resp.json();
                if (data.content) {
                    const pre = document.createElement('pre');
                    pre.className = 'mermaid';
                    pre.textContent = data.content;
                    ref.replaceWith(pre);
                } else {
                    ref.textContent = '[mermaid error: ' + ref.dataset.src + ']';
                }
            } catch { ref.textContent = '[mermaid load failed]'; }
        }));
    }

    function csvToTable(text) {
        const rows = text.trim().split('\n').map(r => {
            // Handle quoted fields containing commas
            const fields = [];
            let cur = '', inQ = false;
            for (let i = 0; i < r.length; i++) {
                const c = r[i];
                if (c === '"') { inQ = !inQ; }
                else if (c === ',' && !inQ) { fields.push(cur.trim()); cur = ''; }
                else { cur += c; }
            }
            fields.push(cur.trim());
            return fields;
        });
        if (!rows.length) return '';
        let html = '<table><thead><tr>';
        rows[0].forEach(h => { html += '<th>' + escHtml(h) + '</th>'; });
        html += '</tr></thead><tbody>';
        rows.slice(1).forEach(row => {
            html += '<tr>';
            row.forEach(cell => { html += '<td>' + escHtml(cell) + '</td>'; });
            html += '</tr>';
        });
        return html + '</tbody></table>';
    }

    async function renderContent(text, ext, container) {
        const fileDir = getFileDir(currentRel);
        container.classList.toggle('wide', ext === 'mermaid' || ext === 'csv');
        if (ext === 'mermaid') {
            container.innerHTML = '<pre class="mermaid">' + escHtml(text) + '</pre>';
        } else if (ext === 'txt') {
            container.innerHTML = '<pre style="white-space:pre-wrap;word-break:break-word">' + escHtml(text) + '</pre>';
        } else if (ext === 'csv') {
            container.innerHTML = csvToTable(text);
        } else {
            container.innerHTML = marked.parse(text, { renderer: buildRenderer(fileDir) });

            // Convert fenced mermaid code blocks to .mermaid elements
            container.querySelectorAll('pre code.language-mermaid').forEach(code => {
                const pre = code.parentElement;
                const div = document.createElement('pre');
                div.className = 'mermaid';
                div.textContent = code.textContent;
                pre.replaceWith(div);
            });

            fixSrcPaths(container, fileDir);
        }

        // Highlight status tags in text nodes (skip <pre> and <code>)
        highlightTags(container);

        await renderMermaidRefs(container);

        // Run mermaid on all .mermaid nodes
        const mermaidNodes = container.querySelectorAll('.mermaid');
        if (mermaidNodes.length > 0) {
            mermaid.run({ nodes: mermaidNodes });
        }
    }

    // Walk text nodes and wrap status tags with styled spans.
    // Skips content inside <pre> and <code> elements.
    function highlightTags(container) {
        const pattern = /(\[ \]|\[_\]|\[x\]|\[>\]|\[\?\]|\[!\])/g;
        const classMap = {
            '[ ]': 'tag-todo',
            '[_]': 'tag-todo',
            '[x]': 'tag-done',
            '[>]': 'tag-progress',
            '[?]': 'tag-question',
            '[!]': 'tag-alert',
        };

        const walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT, {
            acceptNode(node) {
                let el = node.parentElement;
                while (el && el !== container) {
                    if (el.tagName === 'PRE' || el.tagName === 'CODE') return NodeFilter.FILTER_REJECT;
                    el = el.parentElement;
                }
                return pattern.test(node.textContent) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP;
            }
        });

        const nodes = [];
        let node;
        while ((node = walker.nextNode())) nodes.push(node);

        nodes.forEach(textNode => {
            const frag = document.createDocumentFragment();
            let last = 0;
            let m;
            pattern.lastIndex = 0;
            while ((m = pattern.exec(textNode.textContent)) !== null) {
                if (m.index > last) {
                    frag.appendChild(document.createTextNode(textNode.textContent.slice(last, m.index)));
                }
                const span = document.createElement('span');
                span.className = classMap[m[0]];
                span.textContent = m[0];
                frag.appendChild(span);
                last = m.index + m[0].length;
            }
            if (last < textNode.textContent.length) {
                frag.appendChild(document.createTextNode(textNode.textContent.slice(last)));
            }
            textNode.parentNode.replaceChild(frag, textNode);
        });
    }

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // --- Sidebar: file clicks ---
    document.getElementById('sidebar-tree').addEventListener('click', e => {
        const fileEl = e.target.closest('.doc-file');
        if (fileEl) {
            loadFile(fileEl.dataset.rel);
            return;
        }
        const dirEl = e.target.closest('.doc-dir');
        if (dirEl) {
            const children = dirEl.nextElementSibling;
            if (children && children.classList.contains('dir-children')) {
                const isOpen = !children.classList.contains('collapsed');
                children.classList.toggle('collapsed', isOpen);
                dirEl.classList.toggle('open', !isOpen);
            }
        }
    });

    // --- Browser back/forward ---
    window.addEventListener('popstate', e => {
        if (e.state && e.state.rel) {
            loadFile(e.state.rel);
        }
    });

    // --- Auto-expand folder containing the active file ---
    function expandToFile(rel) {
        const fileEl = document.querySelector(`.doc-file[data-rel="${CSS.escape(rel)}"]`);
        if (!fileEl) return;
        let parent = fileEl.parentElement;
        while (parent) {
            if (parent.classList.contains('dir-children')) {
                parent.classList.remove('collapsed');
                const dirEl = parent.previousElementSibling;
                if (dirEl && dirEl.classList.contains('doc-dir')) {
                    dirEl.classList.add('open');
                }
            }
            parent = parent.parentElement;
        }
    }

    // --- Initial load ---
    const params = new URLSearchParams(location.search);
    const initialFile = params.get('file');

    if (initialFile) {
        expandToFile(initialFile);
        loadFile(initialFile);
    } else {
        // Try to load README.md if it exists in the sidebar
        const readmeEl = document.querySelector('.doc-file[data-rel="README.md"]');
        if (readmeEl) {
            loadFile('README.md');
        }
    }

    // --- Resizable sidebar ---
    (function () {
        const LS_SIDEBAR = 'cuadernos-docs-sidebar-width';
        const sidebar   = document.getElementById('sidebar');
        const resizer   = document.getElementById('sidebar-resizer');

        const saved = parseInt(localStorage.getItem(LS_SIDEBAR), 10);
        if (saved && saved >= 140 && saved <= 600) sidebar.style.width = saved + 'px';

        let startX, startW;

        resizer.addEventListener('mousedown', e => {
            startX = e.clientX;
            startW = sidebar.getBoundingClientRect().width;
            resizer.classList.add('dragging');
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
            e.preventDefault();
        });

        document.addEventListener('mousemove', e => {
            if (!resizer.classList.contains('dragging')) return;
            const w = Math.min(600, Math.max(140, startW + (e.clientX - startX)));
            sidebar.style.width = w + 'px';
        });

        document.addEventListener('mouseup', () => {
            if (!resizer.classList.contains('dragging')) return;
            resizer.classList.remove('dragging');
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            localStorage.setItem(LS_SIDEBAR, parseInt(sidebar.style.width, 10));
        });
    }());

}());
</script>
</body>
</html>

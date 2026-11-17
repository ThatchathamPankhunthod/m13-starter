// Module 13: Portfolios and Items are saved on the server (EC2), not in localStorage
let portfolios = [];          // copy of the server data
let selectedId = null;        // which Portfolio is shown on the right

// ---------- Talk to the server ----------

// Read the server's answer as JSON. If it is not JSON, PHP did not run:
// e.g. the page was opened from GitHub Pages, Live Preview or a file,
// or PHP is not installed on EC2.
async function readJson(response) {
    const text = await response.text();
    try {
        return JSON.parse(text);
    } catch {
        throw new Error('The server did not answer with JSON (HTTP ' + response.status +
            '). Open the app via http://localhost:8000 (php -S) or http://<EC2 public IP>/ — not Live Server or GitHub Pages.');
    }
}

// GET: ask the server for all Portfolios
async function loadPortfolios() {
    const response = await fetch('api/portfolios.php');
    const result = await readJson(response);
    if (!response.ok) {
        throw new Error(result.error || 'Could not load Portfolios');
    }
    portfolios = result;
    showPortfolioList();
    showSelectedPortfolio();
}

// POST: send ONE new thing to the server as form fields (FormData)
async function postForm(url, formData) {
    const response = await fetch(url, {
        method: 'POST',
        body: formData                  // same fields a normal form submit would send
    });
    const result = await readJson(response);
    if (!response.ok) {
        throw new Error(result.error || 'Could not save');
    }
    return result;
}

// ---------- Show data on the page ----------

function showPortfolioList() {
    const list = document.querySelector('#portfolio-list');
    list.innerHTML = '';
    document.querySelector('#list-empty').hidden = portfolios.length > 0;
    for (const portfolio of portfolios) {
        const button = document.createElement('button');
        button.className = 'btn btn-light portfolio-btn text-start';
        if (portfolio.id === selectedId) button.classList.add('active');
        button.textContent = portfolio.displayName + ' (' + portfolio.items.length + ' items)';
        button.addEventListener('click', () => {
            selectedId = portfolio.id;
            updateImportButton();
            showPortfolioList();
            showSelectedPortfolio();
        });
        list.appendChild(button);
    }
}

function showSelectedPortfolio() {
    const portfolio = portfolios.find(p => p.id === selectedId);
    if (!portfolio) return;
    document.querySelector('#portfolio-name').textContent = portfolio.displayName;
    document.querySelector('#portfolio-enjoy').textContent = portfolio.whatIEnjoy;
    document.querySelector('#portfolio-tried').textContent = portfolio.whatIveTried;
    document.querySelector('#portfolio-curious').textContent = portfolio.whatImCuriousAbout;

    const list = document.querySelector('#item-list');
    list.innerHTML = '';
    for (const item of portfolio.items) {
        const card = document.createElement('div');
        card.className = 'item-card rounded-3 p-3';
        const title = document.createElement('h3');
        title.className = 'h6 fw-bold mb-1';
        title.textContent = item.title + ' · ' + item.type + ' · ' + item.date;
        const text = document.createElement('p');
        text.className = 'small mb-1';
        text.textContent = item.description + ' — Skills: ' + item.skills.join(', ');
        card.append(title, text);

        for (const url of (Array.isArray(item.evidence) ? item.evidence : [])) {
            if (!url) continue;
            const link = document.createElement('a');
            link.href = url;
            link.textContent = 'Evidence: ' + url;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.className = 'small d-block mt-2';
            card.appendChild(link);
        }
        list.appendChild(card);
    }
}

// ---------- Forms ----------

document.querySelector('#portfolio-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const error = document.querySelector('#portfolio-error');
    try {
        const formData = new FormData(event.target);   // every input that has a name
        const created = await postForm('api/portfolios.php', formData);
        error.textContent = '';
        event.target.reset();
        selectedId = created.id;
        await loadPortfolios();          // read back from the server
    } catch (e) {
        error.textContent = e.message;
    }
});

document.querySelector('#item-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const error = document.querySelector('#item-error');
    if (!selectedId) {
        error.textContent = 'Choose a Portfolio first.';
        return;
    }
    try {
        const formData = new FormData(event.target);
        formData.append('portfolioId', selectedId);    // which Portfolio this Item belongs to
        await postForm('api/items.php', formData);
        error.textContent = '';
        event.target.reset();
        await loadPortfolios();          // read back from the server
    } catch (e) {
        error.textContent = e.message;
    }
});

// when the page opens: show a problem instead of failing silently
loadPortfolios().catch((e) => {
    const message = document.querySelector('#list-empty');
    message.hidden = false;
    message.className = 'small text-danger mb-0';
    message.textContent = e.message;
});


// ---------- Import / Export ----------
const importFileInput = document.querySelector('#import-file');
const importJsonInput = document.querySelector('#import-json');
const importButton = document.querySelector('#import-item');
const importStatus = document.querySelector('#import-status');

function updateImportButton() {
    const hasText = importJsonInput.value.trim().length > 0;
    const hasFile = Boolean(importFileInput.files[0]);
    importButton.disabled = !selectedId || (!hasText && !hasFile);
}

importFileInput.addEventListener('change', () => {
    const file = importFileInput.files[0];
    if (file) {
        importJsonInput.value = '';
        importStatus.textContent = `Selected file: ${file.name}`;
        importStatus.className = 'small mb-3 text-muted';
    } else {
        importStatus.textContent = '';
    }
    updateImportButton();
});

importJsonInput.addEventListener('input', () => {
    if (importJsonInput.value.trim()) {
        importFileInput.value = '';
    }
    updateImportButton();
});

// Export the currently selected Portfolio as a reusable JSON file.
document.querySelector('#export-portfolio').addEventListener('click', () => {
    const portfolio = portfolios.find(p => p.id === selectedId);
    if (!portfolio) {
        importStatus.textContent = 'Choose a Portfolio before exporting.';
        importStatus.className = 'small mb-3 text-danger';
        return;
    }
    const blob = new Blob([JSON.stringify(portfolio, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'my-portfolio.json';
    document.body.appendChild(link);
    link.click();
    link.remove();
    // Give the browser time to start the download before releasing the Blob URL.
    window.setTimeout(() => URL.revokeObjectURL(url), 1500);
});

// Import JSON pasted in the textarea or selected from a local file.
importButton.addEventListener('click', async () => {
    if (!selectedId) return;
    const file = importFileInput.files[0];
    const content = importJsonInput.value.trim() || (file ? await file.text() : '');
    if (!content) return;

    importButton.disabled = true;
    importStatus.textContent = 'Importing...';
    importStatus.className = 'small mb-3 text-muted';
    try {
        let data;
        try { data = JSON.parse(content); }
        catch { throw new Error('Invalid JSON. Please check the pasted text or uploaded file.'); }

        const response = await fetch('api/import.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                portfolioId: selectedId,
                data,
                fileHash: await sha256(content)
            })
        });
        const result = await readJson(response);
        if (!response.ok) throw new Error(result.error || 'Import failed.');

        if (result.duplicateFile) {
            importStatus.textContent = 'This JSON file was already imported. Duplicate import blocked automatically.';
            importStatus.className = 'small mb-3 text-warning';
            importFileInput.value = '';
            importJsonInput.value = '';
        } else {
            importStatus.textContent = `Import complete: ${result.imported} added, ${result.skipped} duplicate(s) skipped.`;
            importStatus.className = 'small mb-3 text-success';
            importFileInput.value = '';
            importJsonInput.value = '';
            await loadPortfolios();
        }
    } catch (error) {
        importStatus.textContent = error.message;
        importStatus.className = 'small mb-3 text-danger';
    } finally {
        updateImportButton();
    }
});

async function sha256(value) {
    if (window.crypto && crypto.subtle) {
        const bytes = new TextEncoder().encode(value);
        const digest = await crypto.subtle.digest('SHA-256', bytes);
        return Array.from(new Uint8Array(digest)).map(b => b.toString(16).padStart(2, '0')).join('');
    }
    // Fallback fingerprint; server still validates the format and stores it persistently.
    let hash = 2166136261;
    for (let i = 0; i < value.length; i++) hash = Math.imul(hash ^ value.charCodeAt(i), 16777619);
    return 'fnv-' + (hash >>> 0).toString(16);
}


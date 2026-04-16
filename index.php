<?php
session_start();

// --- KONFIGURATION ---
$correctPin = '1234';
$schuljahr = "2025/2026";
$storageFile = 'data.json';
$classFolder = 'klassen';

// Login-Logik & Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') { session_destroy(); header('Location: ' . $_SERVER['PHP_SELF']); exit; }
if (isset($_POST['pin'])) { if ($_POST['pin'] === $correctPin) { $_SESSION['authenticated'] = true; } else { $error = "Falsche PIN!"; } }

// --- LOGIN SEITE ---
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
?>
    <!DOCTYPE html>
    <html lang="de"><head><meta charset="UTF-8"><title>Login - Nachschreibemanager</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .login-card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid #d1d5db; width: 100%; max-width: 400px; text-align: center; }
        input { width: 100%; padding: 12px; margin: 20px 0; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 20px; text-align: center; box-sizing: border-box; }
        button { background: #14508c; color: white; border: none; padding: 12px 30px; border-radius: 8px; font-weight: bold; cursor: pointer; width: 100%; }
        h1 { color: #14508c; margin-top: 0; }
    </style></head>
    <body>
        <div class="login-card">
            <h1>🔑 MMBbS</h1>
            <p>Bitte PIN eingeben</p>
            <form method="POST">
                <input type="password" name="pin" autofocus>
                <?php if (isset($error)): ?><p style="color:red;"><?= $error ?></p><?php endif; ?>
                <button type="submit">Einloggen</button>
            </form>
        </div>
    </body></html>
<?php exit; }

// --- VORSCHLAGSLOGIK ---
$suggestions = [];
if (is_dir($classFolder)) {
    $files = glob($classFolder . "/*.json");
    foreach ($files as $file) {
        $content = json_decode(file_get_contents($file), true);
        if (is_array($content)) {
            $data = (isset($content[0]) && is_array($content[0]) && isset($content[0][0])) ? $content[0] : $content;
            foreach ($data as $student) {
                if (isset($student['vorname'])) {
                    $fullClass = $student['gruppen'] ?? '';
                    $cleanClass = !empty($fullClass) ? explode(' ', trim($fullClass))[0] : '';
                    $suggestions[] = [
                        'name' => $student['vorname'] . ' ' . $student['nachname'],
                        'email' => $student['e-mail-adresse'] ?? '',
                        'class' => $cleanClass
                    ];
                }
            }
        }
    }
}

// Speichern
if (isset($_GET['action']) && $_GET['action'] === 'save') {
    file_put_contents($storageFile, file_get_contents('php://input'));
    exit;
}
$currentData = file_exists($storageFile) ? file_get_contents($storageFile) : '[]';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Nachschreibemanager - MMBbS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary-blue: #14508c; --border-dark: #d1d5db; --border-light: #e5e7eb; --main-bg: #f0f2f5; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--main-bg); margin: 0; display: flex; flex-direction: column; min-height: 100vh; color: #374151; }
        
        header { background: #fff; padding: 10px 40px; display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; border-bottom: 1px solid var(--border-dark); gap: 20px; }
        .header-title h1 { margin: 0; font-size: 1.25rem; color: var(--primary-blue); }
        .nav-btn { text-decoration: none; background: #34495e; color: white; padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; transition: all 0.2s; cursor: pointer; border: none; }
        .nav-btn:hover { background: #1f2937; }
        .nav-btn-blue { background: var(--primary-blue); }

        .container { padding: 30px 40px; flex: 1; max-width: 1600px; margin: 0 auto; width: 100%; box-sizing: border-box; }
        .card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e5e7eb; }
        h3 { margin: 0 0 15px 0; font-size: 0.85rem; color: var(--primary-blue); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #f3f4f6; padding-bottom: 10px; font-weight: 700; }
        
        footer { background-color: #14508c; color: white; padding: 40px 0; margin-top: 60px; }
        .footer-content { max-width: 1320px; margin: 0 auto; padding: 0 40px; display: flex; justify-content: space-between; align-items: center; }
        .footer-link { color: #89b1d8; text-decoration: none; }

        .suggestion-item:hover { background-color: #f1f5f9; cursor: pointer; }
        .tab-active { border-bottom: 3px solid var(--primary-blue); color: var(--primary-blue); }
        .action-btn-toggle { transition: all 0.2s; border: 1px solid #e2e8f0; }
        
        /* Sortier-Header Style */
        .sortable-header { cursor: pointer; transition: background 0.2s; }
        .sortable-header:hover { background-color: #f8fafc; }
    </style>
</head>
<body>

<header>
    <div><img src="logo.png" style="height:55px;" onerror="this.style.visibility='hidden'"></div>
    <div class="header-title"><h1>Nachschreibe-Manager <span style="font-weight:300; color:#64748b;">SJ <?= $schuljahr ?></span></h1></div>
    <div style="display:flex; justify-content:flex-end; gap:10px; align-items:center;">
        <div id="saveStatus" class="text-xs font-medium text-emerald-600 opacity-0 transition-opacity italic mr-4">✓ Gespeichert</div>
        <button onclick="exportToPDF()" class="nav-btn bg-white !text-slate-700 border border-slate-200 shadow-sm">PDF Liste</button>
        <a href="?action=logout" class="nav-btn bg-red-50 !text-red-600 border border-red-100"><i data-lucide="log-out" class="w-4 h-4"></i></a>
    </div>
</header>

<div class="container">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        
        <div class="lg:col-span-1">
            <div class="card sticky top-8">
                <h3 id="formTitle">Neuer Eintrag</h3>
                <form id="addForm" class="space-y-4" autocomplete="off">
                    <div class="relative">
                        <div class="grid grid-cols-3 gap-2">
                            <input type="text" id="inputName" required class="col-span-2 px-3 py-2 rounded-lg border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-blue-500" placeholder="Schüler Name">
                            <input type="text" id="inputTeacher" required class="px-3 py-2 rounded-lg border border-slate-300 text-sm outline-none text-center" placeholder="Kürzel">
                        </div>
                        <div id="suggestionBox" class="hidden absolute z-50 w-full bg-white border border-slate-200 mt-1 rounded-xl shadow-2xl max-h-60 overflow-y-auto"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" id="inputClass" required class="px-3 py-2 rounded-lg border border-slate-300 text-sm outline-none" placeholder="Klasse">
                        <input type="number" id="inputDuration" required class="px-3 py-2 rounded-lg border border-slate-300 text-sm outline-none" placeholder="Minuten">
                    </div>
                    <input type="text" id="inputExam" required class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm outline-none" placeholder="LF / Fachbezeichnung">
                    <input type="datetime-local" id="inputDate" required class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm outline-none">
                    <input type="email" id="inputEmail" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm outline-none" placeholder="E-Mail für Einladung">
                    <textarea id="inputComment" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm outline-none" placeholder="Optionale Bemerkung..." rows="2"></textarea>
                    
                    <div class="flex items-center gap-3 bg-slate-50 p-3 rounded-lg border border-slate-200">
                        <input type="checkbox" id="inputAU" class="w-4 h-4 rounded text-blue-600">
                        <label for="inputAU" class="text-xs font-semibold text-slate-700 cursor-pointer">Attest liegt vor</label>
                    </div>
                    
                    <button type="submit" id="submitBtn" class="nav-btn nav-btn-blue w-full !py-3">Speichern</button>
                    <button type="button" id="cancelEditBtn" onclick="cancelEdit()" class="hidden w-full text-slate-500 text-xs py-2 uppercase font-bold tracking-wider text-center">Abbrechen</button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-3 space-y-4">
            
            <div class="flex flex-col md:flex-row gap-4 justify-between items-center bg-white p-4 rounded-2xl shadow-sm border border-slate-100">
                <div class="flex gap-6 border-b border-slate-100 w-full md:w-auto">
                    <button onclick="setStatusFilter('pending')" id="tabPending" class="pb-2 px-1 text-sm font-bold transition-all tab-active">Ausstehend</button>
                    <button onclick="setStatusFilter('completed')" id="tabCompleted" class="pb-2 px-1 text-sm font-bold text-slate-400 transition-all">Archiv</button>
                    <button onclick="setStatusFilter('all')" id="tabAll" class="pb-2 px-1 text-sm font-bold text-slate-400 transition-all">Alle</button>
                </div>
                
                <div class="flex gap-4 items-center">
                    <div id="badgeOverdue" class="bg-red-50 text-red-700 px-3 py-1 rounded-full text-xs font-bold border border-red-100 hidden"><span id="statsOverdue">0</span> Überfällig</div>
                    <div class="bg-blue-50 text-blue-700 px-3 py-1 rounded-full text-xs font-bold border border-blue-100"><span id="statsPending">0</span> In Planung</div>
                    
                    <div class="flex bg-slate-100 p-1 rounded-lg">
                        <button onclick="setView('grid')" id="btnViewGrid" class="px-3 py-1.5 rounded-md transition-all"><i data-lucide="layout-grid" class="w-4 h-4"></i></button>
                        <button onclick="setView('table')" id="btnViewTable" class="px-3 py-1.5 rounded-md transition-all"><i data-lucide="list" class="w-4 h-4"></i></button>
                    </div>
                </div>
            </div>

            <div class="relative">
                <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400"></i>
                <input type="text" id="searchInput" oninput="render()" placeholder="Suche..." class="w-full pl-12 pr-4 py-3 bg-white rounded-xl shadow-sm border border-slate-200 outline-none">
            </div>

            <div id="contentArea">
                <div id="recordsGrid" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
                <div id="recordsTableContainer" class="hidden overflow-x-auto bg-white rounded-xl shadow-sm border border-slate-100">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50 border-b border-slate-100">
                            <tr>
                                <th onclick="setSort('makeUpDate')" class="sortable-header p-4 text-xs font-bold text-slate-500 uppercase">Termin <i data-lucide="chevrons-up-down" class="inline w-3 h-3 ml-1"></i></th>
                                <th onclick="setSort('name')" class="sortable-header p-4 text-xs font-bold text-slate-500 uppercase">Schüler <i data-lucide="chevrons-up-down" class="inline w-3 h-3 ml-1"></i></th>
                                <th onclick="setSort('schoolClass')" class="sortable-header p-4 text-xs font-bold text-slate-500 uppercase">Klasse <i data-lucide="chevrons-up-down" class="inline w-3 h-3 ml-1"></i></th>
                                <th class="p-4 text-xs font-bold text-slate-500 uppercase text-center">Status</th>
                                <th class="p-4 text-xs font-bold text-slate-500 uppercase text-right">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<footer>
    <div class="footer-content">
        <div>
            <h4 style="margin:0 0 10px 0;">Lizenz</h4>
            © <?= date('Y') ?> MMBbS Hannover | <a href="https://github.com/herr-nm/MMBbS_Nachschreibemanager" target="_blank" class="footer-link">Herr-NM</a> | <a href="https://www.gnu.org/licenses/agpl-3.0.html" target="_blank" class="footer-link">GNU AGPL v3</a>
        </div>
        <div style="text-align: right;">
            <h4 style="margin:0 0 10px 0;">Kontakt</h4>
            <a href="mailto:info@mmbbs.de" class="footer-link">info@mmbbs.de</a><br>
            <a href="https://www.mmbbs.de" target="_blank" class="footer-link">www.mmbbs.de</a>
        </div>
    </div>
</footer>

<script>
    let records = <?= $currentData ?>;
    const studentSuggestions = <?= json_encode($suggestions) ?>;
    let editingId = null;
    let currentView = 'grid'; 
    let currentStatusFilter = 'pending';
    
    // Sortier-Zustand
    let sortField = 'makeUpDate';
    let sortOrder = 1; // 1 = ASC, -1 = DESC

    function setSort(field) {
        if (sortField === field) {
            sortOrder *= -1;
        } else {
            sortField = field;
            sortOrder = 1;
        }
        render();
    }

    // --- RENDER FUNKTION ---
    function render() {
        const grid = document.getElementById('recordsGrid');
        const tableBody = document.getElementById('tableBody');
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const now = new Date();
        
        grid.innerHTML = ''; tableBody.innerHTML = '';
        let overdueCount = 0, pendingCount = 0;

        const filtered = records.filter(r => {
            if (r.status === 'pending') {
                pendingCount++;
                if (new Date(r.makeUpDate) < now) overdueCount++;
            }
            return r.name.toLowerCase().includes(searchTerm) || 
                   r.schoolClass.toLowerCase().includes(searchTerm) || 
                   r.examName.toLowerCase().includes(searchTerm);
        })
        .filter(r => currentStatusFilter === 'all' || r.status === currentStatusFilter)
        .sort((a, b) => {
            let valA = a[sortField] || '';
            let valB = b[sortField] || '';
            
            // Sonderbehandlung für Datum
            if (sortField === 'makeUpDate') {
                valA = new Date(valA);
                valB = new Date(valB);
            } else {
                valA = valA.toString().toLowerCase();
                valB = valB.toString().toLowerCase();
            }

            if (valA < valB) return -1 * sortOrder;
            if (valA > valB) return 1 * sortOrder;
            return 0;
        });

        filtered.forEach(record => {
            const dateObj = new Date(record.makeUpDate);
            const isOverdue = dateObj < now && record.status === 'pending';
            const formattedDate = dateObj.toLocaleString('de-DE', { dateStyle: 'short', timeStyle: 'short' });

            // GRID CARD (Aktionen wie zuvor)
            const card = document.createElement('div');
            card.className = `bg-white p-5 rounded-xl shadow-sm border-2 transition-all ${record.status === 'completed' ? 'opacity-75 grayscale border-slate-100' : isOverdue ? 'border-red-200 bg-red-50/20' : 'border-slate-50'}`;
            card.innerHTML = `
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="!border-none !pb-0 !m-0 !text-base font-bold text-slate-900">${record.name}</h3>
                            <span class="bg-slate-700 text-white text-[10px] px-1.5 py-0.5 rounded font-bold">${record.teacher || '??'}</span>
                        </div>
                        <span class="text-[11px] text-blue-600 font-bold uppercase tracking-wider">${record.schoolClass} • ${record.duration || '?'} Min</span>
                    </div>
                    <div class="flex gap-1">
                        <button onclick="toggleField('${record.id}', 'invited')" title="Eingeladen" class="action-btn-toggle p-1.5 rounded ${record.invited ? 'bg-blue-100 text-blue-600 border-blue-200' : 'bg-slate-50 text-slate-300'}"><i data-lucide="mail" class="w-4 h-4"></i></button>
                        <button onclick="toggleField('${record.id}', 'registered')" title="Bestätigt" class="action-btn-toggle p-1.5 rounded ${record.registered ? 'bg-purple-100 text-purple-600 border-purple-200' : 'bg-slate-50 text-slate-300'}"><i data-lucide="list-checks" class="w-4 h-4"></i></button>
                        <button onclick="toggleField('${record.id}', 'hasAU')" title="Attest Status" class="text-[10px] font-black px-2 rounded border action-btn-toggle ${record.hasAU ? 'bg-emerald-50 border-emerald-200 text-emerald-600' : 'bg-red-50 border-red-200 text-red-400'}">AU</button>
                    </div>
                </div>
                <div class="text-sm space-y-2 mb-4">
                    <div class="font-medium flex items-center gap-2"><i data-lucide="clipboard-list" class="w-4 h-4 text-slate-400"></i> ${record.examName}</div>
                    <div class="flex items-center gap-2 ${isOverdue ? 'text-red-600 font-bold' : 'text-slate-500'}"><i data-lucide="calendar" class="w-4 h-4"></i> ${formattedDate}</div>
                    ${record.comment ? `<div class="text-xs italic text-slate-400 bg-slate-50 p-2 rounded border-l-2 border-slate-200">${record.comment}</div>` : ''}
                </div>
                <div class="flex gap-2 pt-4 border-t border-slate-100">
                    <button onclick="sendInvitationMail('${record.id}')" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 py-2 rounded text-[11px] font-bold uppercase tracking-tighter transition">Mail</button>
                    <button onclick="toggleStatus('${record.id}')" class="flex-1 py-2 rounded text-[11px] font-bold uppercase transition ${record.status === 'completed' ? 'bg-slate-200' : 'bg-emerald-600 hover:bg-emerald-700 text-white'}">${record.status === 'completed' ? 'Reaktivieren' : '✔ Erledigt'}</button>
                    <button onclick="editRecord('${record.id}')" class="p-2 text-amber-600 hover:bg-amber-50 rounded transition"><i data-lucide="pencil" class="w-4 h-4"></i></button>
                    <button onclick="deleteRecord('${record.id}')" class="p-2 text-red-600 hover:bg-red-50 rounded transition"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                </div>
            `;
            grid.appendChild(card);

            // TABLE ROW
            const tr = document.createElement('tr');
            tr.className = `border-b border-slate-100 text-sm hover:bg-slate-50/50 ${record.status === 'completed' ? 'opacity-50' : ''}`;
            tr.innerHTML = `
                <td class="p-4 ${isOverdue ? 'text-red-600 font-bold' : ''}">${formattedDate}</td>
                <td class="p-4 font-bold">${record.name}</td>
                <td class="p-4">${record.schoolClass}</td>
                <td class="p-4 text-center">
                    <div class="flex justify-center gap-2">
                        <i data-lucide="mail" class="w-4 h-4 ${record.invited ? 'text-blue-500' : 'text-slate-200'}"></i>
                        <i data-lucide="list-checks" class="w-4 h-4 ${record.registered ? 'text-purple-500' : 'text-slate-200'}"></i>
                        <span class="text-[10px] font-bold ${record.hasAU ? 'text-emerald-500' : 'text-red-400'}">AU</span>
                    </div>
                </td>
                <td class="p-4 text-right">
                    <div class="flex justify-end gap-1">
                        <button onclick="toggleStatus('${record.id}')" class="p-2 rounded hover:bg-emerald-50 ${record.status === 'completed' ? 'text-emerald-600' : 'text-slate-300'}"><i data-lucide="check-circle" class="w-5 h-5"></i></button>
                        <button onclick="editRecord('${record.id}')" class="p-2 text-slate-400 hover:text-amber-600"><i data-lucide="pencil" class="w-4 h-4"></i></button>
                        <button onclick="deleteRecord('${record.id}')" class="p-2 text-slate-400 hover:text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                    </div>
                </td>
            `;
            tableBody.appendChild(tr);
        });

        document.getElementById('statsPending').innerText = pendingCount;
        document.getElementById('statsOverdue').innerText = overdueCount;
        document.getElementById('badgeOverdue').classList.toggle('hidden', overdueCount === 0);
        lucide.createIcons();
    }

    // --- LOGIK-KERNFUNKTIONEN ---

    async function saveToServer() {
        const statusEl = document.getElementById('saveStatus');
        await fetch('?action=save', { method: 'POST', body: JSON.stringify(records) });
        statusEl.classList.remove('opacity-0');
        setTimeout(() => statusEl.classList.add('opacity-0'), 2000);
    }

    window.toggleStatus = async (id) => {
        records = records.map(r => r.id === id ? {...r, status: r.status === 'completed' ? 'pending' : 'completed'} : r);
        render(); await saveToServer();
    };

    window.toggleField = async (id, field) => {
        records = records.map(r => r.id === id ? {...r, [field]: !r[field]} : r);
        render(); await saveToServer();
    };

    window.sendInvitationMail = async (id) => {
        const r = records.find(r => r.id === id);
        const dateStr = new Date(r.makeUpDate).toLocaleDateString('de-DE');
        const timeStr = new Date(r.makeUpDate).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
        const subject = encodeURIComponent(`Einladung zum Nachschreibetermin - ${r.examName}`);
        const body = encodeURIComponent(`Hallo ${r.name},\n\nIhr Nachschreibetermin findet am ${dateStr} um ${timeStr} statt.\n\nBitte bestätigen Sie mir diese Mail.`);
        window.location.href = `mailto:${r.email}?subject=${subject}&body=${body}`;
        records = records.map(rec => rec.id === id ? {...rec, invited: true} : rec);
        render(); await saveToServer();
    };

    document.getElementById('addForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = {
            name: document.getElementById('inputName').value,
            teacher: document.getElementById('inputTeacher').value.toUpperCase(),
            schoolClass: document.getElementById('inputClass').value,
            duration: document.getElementById('inputDuration').value,
            email: document.getElementById('inputEmail').value,
            examName: document.getElementById('inputExam').value,
            makeUpDate: document.getElementById('inputDate').value,
            comment: document.getElementById('inputComment').value,
            hasAU: document.getElementById('inputAU').checked,
        };
        if (editingId) { 
            records = records.map(r => r.id === editingId ? {...r, ...data} : r); 
            cancelEdit(); 
        } else { 
            records.push({...data, id: crypto.randomUUID(), status: 'pending', invited: false, registered: false}); 
        }
        render(); await saveToServer(); e.target.reset();
    });

    window.deleteRecord = async (id) => { if(confirm('Eintrag wirklich löschen?')) { records = records.filter(r => r.id !== id); render(); await saveToServer(); } };
    
    window.editRecord = (id) => {
        const r = records.find(r => r.id === id); editingId = id;
        document.getElementById('inputName').value = r.name;
        document.getElementById('inputTeacher').value = r.teacher || '';
        document.getElementById('inputClass').value = r.schoolClass;
        document.getElementById('inputDuration').value = r.duration || '';
        document.getElementById('inputEmail').value = r.email || '';
        document.getElementById('inputExam').value = r.examName;
        document.getElementById('inputDate').value = r.makeUpDate;
        document.getElementById('inputAU').checked = r.hasAU;
        document.getElementById('inputComment').value = r.comment || '';
        document.getElementById('formTitle').innerText = "Bearbeiten";
        document.getElementById('submitBtn').innerText = "Änderung speichern";
        document.getElementById('cancelEditBtn').classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    window.cancelEdit = () => { 
        editingId = null; document.getElementById('addForm').reset(); 
        document.getElementById('formTitle').innerText = "Neuer Eintrag"; 
        document.getElementById('submitBtn').innerText = "Speichern";
        document.getElementById('cancelEditBtn').classList.add('hidden'); 
    };

    function setStatusFilter(status) {
        currentStatusFilter = status;
        document.querySelectorAll('[id^="tab"]').forEach(el => { el.classList.remove('tab-active'); el.classList.add('text-slate-400'); });
        const activeTab = document.getElementById('tab' + status.charAt(0).toUpperCase() + status.slice(1));
        activeTab.classList.add('tab-active'); activeTab.classList.remove('text-slate-400');
        render();
    }

    function setView(view) {
        currentView = view;
        document.getElementById('btnViewGrid').className = `px-3 py-1.5 rounded-md transition-all ${view === 'grid' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-400'}`;
        document.getElementById('btnViewTable').className = `px-3 py-1.5 rounded-md transition-all ${view === 'table' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-400'}`;
        document.getElementById('recordsGrid').classList.toggle('hidden', view !== 'grid');
        document.getElementById('recordsTableContainer').classList.toggle('hidden', view !== 'table');
        render();
    }

    window.exportToPDF = function() {
        const { jsPDF } = window.jspdf; const doc = new jsPDF('l', 'mm', 'a4');
        const rows = records.filter(r => currentStatusFilter === 'all' || r.status === currentStatusFilter)
                            .map(r => [new Date(r.makeUpDate).toLocaleString('de-DE'), r.name, r.schoolClass, r.examName, r.status === 'completed' ? 'Erledigt' : 'Offen']);
        doc.text("Nachschreibetermine - MMBbS", 14, 15);
        doc.autoTable({ startY: 20, head: [['Termin', 'Name', 'Klasse', 'Leistung', 'Status']], body: rows });
        doc.save('Nachschreiber.pdf');
    };

    // Autocomplete
    document.getElementById('inputName').addEventListener('input', (e) => {
        const val = e.target.value.toLowerCase();
        const box = document.getElementById('suggestionBox');
        box.innerHTML = '';
        if (val.length < 2) { box.classList.add('hidden'); return; }
        const matches = studentSuggestions.filter(s => s.name.toLowerCase().includes(val)).slice(0, 5);
        if (matches.length > 0) {
            matches.forEach(s => {
                const div = document.createElement('div');
                div.className = 'suggestion-item p-3 border-b border-slate-100 text-sm';
                div.innerHTML = `<div class="font-bold">${s.name}</div><div class="text-xs text-slate-500">${s.class}</div>`;
                div.onclick = () => {
                    document.getElementById('inputName').value = s.name;
                    document.getElementById('inputEmail').value = s.email;
                    document.getElementById('inputClass').value = s.class;
                    box.classList.add('hidden');
                };
                box.appendChild(div);
            });
            box.classList.remove('hidden');
        } else { box.classList.add('hidden'); }
    });

    document.addEventListener('DOMContentLoaded', () => { setView('grid'); render(); });
</script>
</body>
</html>
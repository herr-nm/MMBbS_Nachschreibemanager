<?php
session_start();

// PIN aus der Docker-Umgebung laden - Kommentar dafür entfernen und .env im gleichen Verzeichnis anlegen.
//$correctPin = getenv('APP_PIN');
// PIN in dieser .php-Datei setzen
$correctPin = '1234';

// Login-Logik & Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') { session_destroy(); header('Location: ' . $_SERVER['PHP_SELF']); exit; }
if (isset($_POST['pin'])) { if ($_POST['pin'] === $correctPin) { $_SESSION['authenticated'] = true; } else { $error = "Falsche PIN!"; } }
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
?>
    <!DOCTYPE html>
    <html lang="de"><head><meta charset="UTF-8"><script src="https://cdn.tailwindcss.com"></script></head>
    <body class="bg-slate-100 flex items-center justify-center min-h-screen font-sans">
        <div class="bg-white p-8 rounded-2xl shadow-xl border border-slate-200 w-full max-w-md text-center">
            <h1 class="text-2xl font-bold mb-6 text-slate-800">🔒 Zugriff geschützt</h1>
            <form method="POST" class="space-y-4">
                <input type="password" name="pin" autofocus placeholder="PIN eingeben" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 outline-none text-center text-2xl tracking-widest">
                <?php if (isset($error)): ?><p class="text-red-500 text-sm font-medium"><?php echo $error; ?></p><?php endif; ?>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition-all">Einloggen</button>
            </form>
        </div>
    </body></html>
<?php exit; }

$storageFile = 'data.json';
$classFolder = 'klassen';

// Einlesen der Schüler-Vorschläge aus dem Ordner "klassen"
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nachschreibemanager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <style>
        .suggestion-item:hover { background-color: #f1f5f9; cursor: pointer; }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 font-sans">
    <div class="p-4 md:p-8">
        <div class="max-w-6xl mx-auto space-y-8">
            <header class="flex flex-col md:flex-row justify-between items-start md:items-center bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 flex items-center gap-3"><i data-lucide="book-open" class="text-blue-600 h-8 w-8"></i> Nachschreibe-Manager</h1>
                    <p class="text-slate-500 mt-2">Behalte den Überblick über alle fehlenden Klassenarbeiten.</p>
                </div>
                <div class="flex flex-col items-end gap-3 mt-4 md:mt-0">
                    <div id="saveStatus" class="text-xs font-medium text-emerald-600 opacity-0 transition-opacity italic">✓ Gespeichert</div>
                    <div class="flex flex-wrap justify-end gap-2">
                        <div id="badgeOverdue" class="bg-red-50 text-red-700 px-4 py-2 rounded-lg flex items-center gap-2 font-bold border border-red-100 hidden"><i data-lucide="clock" class="w-4 h-4"></i><span id="statsOverdue">0</span> Überfällig</div>
                        <div class="bg-blue-50 text-blue-700 px-4 py-2 rounded-lg flex items-center gap-2 font-medium border border-blue-100"><i data-lucide="alert-circle" class="w-4 h-4"></i><span id="statsPending">0</span> Ausstehend</div>
                        <button onclick="exportToPDF()" class="bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 px-4 py-2 rounded-lg flex items-center gap-2 font-medium transition-colors shadow-sm text-sm"><i data-lucide="file-text" class="w-4 h-4 text-red-600"></i> PDF Liste</button>
                        <a href="?action=logout" class="bg-red-50 border border-red-100 text-red-600 hover:bg-red-100 px-4 py-2 rounded-lg flex items-center gap-2 font-medium transition-colors"><i data-lucide="log-out" class="w-4 h-4"></i></a>
                    </div>
                </div>
            </header>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 relative">
                        <h2 id="formTitle" class="text-xl font-semibold mb-4 text-slate-800 border-b pb-3">Eintrag erstellen</h2>
                        <form id="addForm" class="space-y-4" autocomplete="off">
                            <div class="relative">
                                <div class="grid grid-cols-3 gap-2">
                                    <input type="text" id="inputName" required class="col-span-2 px-4 py-2 rounded-lg border border-slate-300 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Name Schüler">
                                    <input type="text" id="inputTeacher" required class="px-4 py-2 rounded-lg border border-slate-300 outline-none text-center" placeholder="Kürzel">
                                </div>
                                <div id="suggestionBox" class="hidden absolute z-50 w-full bg-white border border-slate-200 mt-1 rounded-xl shadow-2xl max-h-60 overflow-y-auto"></div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <input type="text" id="inputClass" required class="px-4 py-2 rounded-lg border border-slate-300 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Klasse">
                                <input type="number" id="inputDuration" required class="px-4 py-2 rounded-lg border border-slate-300 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Minuten">
                            </div>
                            <input type="text" id="inputExam" required class="w-full px-4 py-2 rounded-lg border border-slate-300 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Leistung (z.B. LF3)">
                            <input type="datetime-local" id="inputDate" required class="w-full px-4 py-2 rounded-lg border border-slate-300 outline-none focus:ring-2 focus:ring-blue-500">
                            <input type="email" id="inputEmail" class="w-full px-4 py-2 rounded-lg border border-slate-300 outline-none focus:ring-2 focus:ring-blue-500" placeholder="E-Mail (für Einladung)">
                            
                            <textarea id="inputComment" class="w-full px-4 py-2 rounded-lg border border-slate-300 outline-none focus:ring-2 focus:ring-blue-500 text-sm" placeholder="Bemerkung (z.B. Raum, Hilfsmittel...)" rows="2"></textarea>
                            
                            <div class="flex items-center gap-3 bg-slate-50 p-3 rounded-lg border border-slate-200">
                                <input type="checkbox" id="inputAU" class="w-5 h-5 rounded text-blue-600">
                                <label for="inputAU" class="text-sm font-medium text-slate-700 cursor-pointer">AU liegt vor</label>
                            </div>
                            
                            <button type="submit" id="submitBtn" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition-all shadow-md">Speichern</button>
                            <button type="button" id="cancelEditBtn" onclick="cancelEdit()" class="hidden w-full bg-slate-100 py-2 rounded-lg mt-2 font-medium text-slate-600">Abbrechen</button>
                        </form>
                    </div>
                </div>

                <div class="lg:col-span-2 space-y-4">
                    <div class="relative group">
                        <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400"></i>
                        <input type="text" id="searchInput" oninput="render()" placeholder="Suche..." class="w-full pl-12 pr-4 py-3 bg-white rounded-xl shadow-sm border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div id="recordsGrid" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
                </div>
            </div>
        </div>
    </div>

    <footer class="mt-12 pb-8 text-center text-slate-500 text-sm">
        <div class="max-w-6xl mx-auto border-t border-slate-200 pt-6 px-4 text-center">
            <p class="mb-2">Nachschreibe-Manager &copy; 2026 by Herr-NM</p>
            
            <div class="flex flex-col md:flex-row items-center justify-center gap-2 md:gap-4 mb-4">
                <span class="bg-blue-50 border border-blue-200 text-blue-700 px-2 py-1 rounded font-mono text-xs font-semibold">
                    GNU AGPLv3
                </span>
                <p>
                    Lizenziert unter <a href="https://www.gnu.org/licenses/agpl-3.0.de.html" target="_blank" class="underline hover:text-blue-600">AGPL-3.0</a>. 
                    Ursprünglicher Code von Herr-FR (unter CC BY-NC 4.0).
                </p>
            </div>

            <div class="flex items-center justify-center gap-2">
                <a href="https://github.com/herr-nm/MMBbS_Nachschreibemanager" target="_blank" class="flex items-center gap-2 text-slate-600 hover:text-black transition-colors">
                    <i class="fab fa-github text-lg"></i>
                    <span class="font-medium">Quellcode auf GitHub</span>
                </a>
            </div>
        </div>
    </footer>

    <script>
        let records = <?php echo $currentData; ?>;
        const studentSuggestions = <?php echo json_encode($suggestions); ?>;
        let editingId = null;

        const inputName = document.getElementById('inputName');
        const suggestionBox = document.getElementById('suggestionBox');

        inputName.addEventListener('input', (e) => {
            const val = e.target.value.toLowerCase();
            suggestionBox.innerHTML = '';
            if (val.length < 2) { suggestionBox.classList.add('hidden'); return; }
            const matches = studentSuggestions.filter(s => s.name.toLowerCase().includes(val)).slice(0, 5);
            if (matches.length > 0) {
                matches.forEach(s => {
                    const div = document.createElement('div');
                    div.className = 'suggestion-item p-3 border-b border-slate-100 text-sm';
                    div.innerHTML = `<div class="font-bold">${s.name}</div><div class="text-xs text-slate-500">${s.class} | ${s.email}</div>`;
                    div.onclick = () => {
                        inputName.value = s.name;
                        document.getElementById('inputEmail').value = s.email;
                        document.getElementById('inputClass').value = s.class;
                        suggestionBox.classList.add('hidden');
                    };
                    suggestionBox.appendChild(div);
                });
                suggestionBox.classList.remove('hidden');
            } else { suggestionBox.classList.add('hidden'); }
        });

        document.addEventListener('click', (e) => {
            if (!suggestionBox.contains(e.target) && e.target !== inputName) suggestionBox.classList.add('hidden');
        });

        async function saveToServer() {
            const statusEl = document.getElementById('saveStatus');
            await fetch('?action=save', { method: 'POST', body: JSON.stringify(records) });
            statusEl.classList.remove('opacity-0');
            setTimeout(() => statusEl.classList.add('opacity-0'), 2000);
        }

        window.toggleField = async (id, field) => {
            records = records.map(r => r.id === id ? {...r, [field]: !r[field]} : r);
            render(); await saveToServer();
        };

        window.sendInvitationMail = async (id) => {
            const r = records.find(r => r.id === id);
            const dateStr = new Date(r.makeUpDate).toLocaleDateString('de-DE');
            const timeStr = new Date(r.makeUpDate).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
            const subject = encodeURIComponent(`Einladung zum Nachschreibetermin - ${r.examName}`);
            const body = encodeURIComponent(`Hallo ${r.name},\n\nSie haben zu meiner Klassenarbeit im ${r.examName} gefehlt. Wenn Sie mir fristgerecht eine AU eingereicht haben, findet am ${dateStr} um ${timeStr} (Beginn) in Raum 2.03 Ihr Nachschreibetermin statt.\n\nErlaubte Hilfsmittel: Taschenrechner\n\nSie schreiben ${r.duration || 'X'} Minuten; es handelt sich um eine Papier-Arbeit, eigenes Papier darf nicht verwendet werden, der Aufgabensatz enthält ausreichend Platz für Ihre Antworten.\n\nBitte bestätigen Sie mir diese Mail.`);
            window.location.href = `mailto:${r.email}?subject=${subject}&body=${body}`;
            records = records.map(rec => rec.id === id ? {...rec, invited: true} : rec);
            render(); await saveToServer();
        };

        function render() {
            const grid = document.getElementById('recordsGrid');
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const now = new Date();
            grid.innerHTML = '';
            let overdueCount = 0, pendingCount = 0;

            const filtered = records.filter(r => 
                r.name.toLowerCase().includes(searchTerm) || 
                r.schoolClass.toLowerCase().includes(searchTerm) || 
                r.examName.toLowerCase().includes(searchTerm) ||
                (r.teacher && r.teacher.toLowerCase().includes(searchTerm))
            ).sort((a, b) => {
                const dateA = new Date(a.makeUpDate), dateB = new Date(b.makeUpDate);
                return dateA - dateB || a.name.localeCompare(b.name);
            });

            filtered.forEach(record => {
                const dateObj = new Date(record.makeUpDate);
                const isOverdue = dateObj < now && record.status === 'pending';
                if (record.status === 'pending') { pendingCount++; if (isOverdue) overdueCount++; }

                const card = document.createElement('div');
                card.className = `bg-white p-5 rounded-2xl shadow-sm border-2 transition-all ${record.status === 'completed' ? 'border-emerald-100 opacity-75' : isOverdue ? 'border-red-200 bg-red-50/30' : 'border-slate-50'}`;
                card.innerHTML = `
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-slate-900 text-lg">${record.name}</h3>
                                <span class="bg-slate-800 text-white text-[10px] px-1.5 py-0.5 rounded font-black uppercase tracking-tighter">${record.teacher || '??'}</span>
                            </div>
                            <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">${record.schoolClass} (${record.duration || '?'} min)</span>
                        </div>
                        <div class="flex gap-1">
                            <button onclick="toggleField('${record.id}', 'invited')" class="p-1.5 rounded border transition ${record.invited ? 'bg-blue-100 border-blue-200 text-blue-600' : 'bg-slate-50 border-slate-200 text-slate-300'}"><i data-lucide="mail" class="w-4 h-4"></i></button>
                            <button onclick="toggleField('${record.id}', 'registered')" class="p-1.5 rounded border transition ${record.registered ? 'bg-purple-100 border-purple-200 text-purple-600' : 'bg-slate-50 border-slate-200 text-slate-300'}"><i data-lucide="list-checks" class="w-4 h-4"></i></button>
                            <button onclick="toggleField('${record.id}', 'hasAU')" class="text-xs font-bold px-2 py-1 rounded border transition ${record.hasAU ? 'bg-emerald-50 border-emerald-200 text-emerald-600' : 'bg-red-50 border-red-200 text-red-500'}">AU ${record.hasAU ? '✔' : '✘'}</button>
                        </div>
                    </div>
                    <div class="space-y-2 text-sm text-slate-600 mb-6">
                        <div class="flex items-center gap-2 font-medium text-slate-800"><i data-lucide="clipboard-list" class="w-4 h-4 text-slate-400"></i> ${record.examName}</div>
                        <div class="flex items-center gap-2 ${isOverdue ? 'text-red-600 font-bold' : ''}"><i data-lucide="calendar" class="w-4 h-4 ${isOverdue ? 'text-red-500' : 'text-slate-400'}"></i> ${dateObj.toLocaleString('de-DE', { dateStyle: 'medium', timeStyle: 'short' })} Uhr</div>
                        ${record.comment ? `<div class="mt-2 p-2 bg-amber-50 rounded-lg text-xs text-amber-800 border border-amber-100 italic"><i data-lucide="info" class="w-3 h-3 inline mr-1"></i> ${record.comment}</div>` : ''}
                    </div>
                    <div class="flex gap-2 pt-4 border-t border-slate-50">
                        <button onclick="sendInvitationMail('${record.id}')" class="flex-1 bg-blue-50 text-blue-700 py-2 rounded-lg text-xs font-bold hover:bg-blue-100 flex items-center justify-center gap-2 transition"><i data-lucide="send" class="w-3 h-3"></i> Einladung</button>
                        <button onclick="toggleField('${record.id}', 'status')" class="flex-1 py-2 rounded-lg text-xs font-bold transition ${record.status === 'completed' ? 'bg-slate-100 text-slate-500' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'}">${record.status === 'completed' ? 'Reaktivieren' : '✔ Erledigt'}</button>
                        <button onclick="editRecord('${record.id}')" class="p-2 bg-amber-50 text-amber-600 rounded-lg transition hover:bg-amber-100"><i data-lucide="pencil" class="w-4 h-4"></i></button>
                        <button onclick="deleteRecord('${record.id}')" class="p-2 bg-red-50 text-red-600 rounded-lg transition hover:bg-red-100"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                    </div>
                `;
                grid.appendChild(card);
            });
            document.getElementById('statsPending').innerText = pendingCount;
            document.getElementById('statsOverdue').innerText = overdueCount;
            document.getElementById('badgeOverdue').classList.toggle('hidden', overdueCount === 0);
            lucide.createIcons();
        }

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
                comment: document.getElementById('inputComment').value, // BEMERKUNG SPEICHERN
                hasAU: document.getElementById('inputAU').checked,
            };
            if (editingId) { records = records.map(r => r.id === editingId ? {...r, ...data} : r); cancelEdit(); }
            else { records.push({...data, id: crypto.randomUUID(), status: 'pending', invited: false, registered: false}); }
            render(); await saveToServer(); e.target.reset();
        });

        window.deleteRecord = async (id) => { if(confirm('Eintrag löschen?')) { records = records.filter(r => r.id !== id); render(); await saveToServer(); } };
        window.editRecord = (id) => {
            const r = records.find(r => r.id === id); editingId = id;
            document.getElementById('inputName').value = r.name; document.getElementById('inputTeacher').value = r.teacher || '';
            document.getElementById('inputClass').value = r.schoolClass; document.getElementById('inputDuration').value = r.duration || '';
            document.getElementById('inputEmail').value = r.email || ''; document.getElementById('inputExam').value = r.examName;
            document.getElementById('inputDate').value = r.makeUpDate; document.getElementById('inputAU').checked = r.hasAU;
            document.getElementById('inputComment').value = r.comment || ''; // BEMERKUNG LADEN
            document.getElementById('formTitle').innerText = "Bearbeiten"; document.getElementById('submitBtn').innerText = "Speichern";
            document.getElementById('cancelEditBtn').classList.remove('hidden');
        };
        window.cancelEdit = () => { editingId = null; document.getElementById('addForm').reset(); document.getElementById('formTitle').innerText = "Eintrag erstellen"; document.getElementById('submitBtn').innerText = "Speichern"; document.getElementById('cancelEditBtn').classList.add('hidden'); };

        window.exportToPDF = function() {
            const { jsPDF } = window.jspdf; const doc = new jsPDF('l', 'mm', 'a4');
            const sortedRecords = [...records].sort((a, b) => new Date(a.makeUpDate) - new Date(b.makeUpDate) || a.name.localeCompare(b.name));
            const tableRows = sortedRecords.map(r => [new Date(r.makeUpDate).toLocaleString('de-DE', { dateStyle: 'short', timeStyle: 'short' }), r.name, r.schoolClass, r.teacher || '-', r.examName, r.comment || '-', r.hasAU ? 'Ja' : 'Nein', r.status === 'completed' ? 'Erledigt' : 'Offen']);
            doc.text("Nachschreibetermine Übersicht", 14, 15);
            doc.autoTable({ startY: 28, head: [['Termin', 'Schüler', 'Klasse', 'Kürzel', 'Leistung', 'Bemerkung', 'AU', 'Status']], body: tableRows, theme: 'striped', headStyles: { fillColor: [37, 99, 235] }, styles: { fontSize: 8 } });
            doc.save(`nachschreiber_liste_${new Date().toISOString().split('T')[0]}.pdf`);
        };

        document.addEventListener('DOMContentLoaded', render);
    </script>
</body>
</html>
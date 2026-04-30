<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn() || !in_array($_SESSION['role'], ['admin', 'manager', 'accountant'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$success = '';
$error = '';

// Handle Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_doc'])) {
    $title = trim($_POST['title']);
    $category = $_POST['category'];
    $tenant_id = !empty($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : null;
    $apartment_id = !empty($_POST['apartment_id']) ? (int)$_POST['apartment_id'] : null;
    
    // In this simulated environment, we'll mock the file upload
    // But we'll "save" a path derived from the original name
    if (isset($_FILES['document']) && $_FILES['document']['error'] === 0) {
        $filename = time() . '_' . basename($_FILES['document']['name']);
        $upload_dir = __DIR__ . '/uploads/documents/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $target_path = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['document']['tmp_name'], $target_path)) {
            try {
                $stmt = $db->prepare("INSERT INTO documents (title, category, file_path, tenant_id, apartment_id, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $category, 'uploads/documents/' . $filename, $tenant_id, $apartment_id, $_SESSION['user_id']]);
                logActivity("UPLOAD_DOCUMENT", "Title: $title, Category: $category");
                $success = "Document uploaded successfully!";
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        } else {
            $error = "Failed to move uploaded file.";
        }
    } else {
        $error = "Please select a valid file.";
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        // Find file path and title first for logging
        $stmt = $db->prepare("SELECT title, file_path FROM documents WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();
        
        if ($doc && file_exists(__DIR__ . '/' . $doc['file_path'])) {
            unlink(__DIR__ . '/' . $doc['file_path']);
        }
        
        $stmt = $db->prepare("DELETE FROM documents WHERE id = ?");
        $stmt->execute([$id]);
        logActivity("DELETE_DOCUMENT", "Title: {$doc['title']}");
        $success = "Document deleted successfully.";
    } catch (PDOException $e) {
        $error = "Error deleting document.";
    }
}

// Fetch all documents with associations
$documents = $db->query("
    SELECT d.*, t.name as tenant_name, a.unit_number, u.name as uploader_name 
    FROM documents d 
    LEFT JOIN tenants t ON d.tenant_id = t.id 
    LEFT JOIN apartments a ON d.apartment_id = a.id 
    LEFT JOIN users u ON d.uploaded_by = u.id 
    ORDER BY d.uploaded_at DESC
")->fetchAll();

// Fetch residents and apartments for the dropdowns
$tenants = $db->query("SELECT id, name FROM tenants WHERE status = 'Active'")->fetchAll();
$apartments = $db->query("SELECT id, unit_number FROM apartments")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Lease & Documents | Tobby’s Suite</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,900&family=JetBrains+Mono:wght@500;700&display=swap');
    .font-serif-italic { font-family: 'Playfair Display', serif; font-style: italic; }
    .font-mono { font-family: 'JetBrains Mono', monospace; }
  </style>
  <script>
    tailwind.config={theme:{extend:{colors:{"primary":"#1e293b","secondary":"#fbbf24"}}}}
  </script>
</head>
<body class="bg-slate-50 min-h-screen flex">

  <?php include 'includes/sidebar.php'; ?>

  <main class="flex-1 flex flex-col h-screen overflow-y-auto">
    <header class="bg-white border-b border-slate-200 p-6 sticky top-0 z-10 flex justify-between items-center">
      <div>
        <h2 class="text-xl font-serif-italic text-slate-900 tracking-tighter">Lease & Document Central</h2>
        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Digital Archive of Resident Agreements</p>
      </div>
      <div class="flex gap-3">
         <button onclick="document.getElementById('uploadModal').classList.toggle('hidden')" class="bg-slate-900 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest flex items-center gap-2 shadow-lg shadow-slate-200 transition-all hover:bg-slate-800 active:scale-95">
           <span class="material-symbols-outlined text-sm">upload_file</span>
           Add New Document
         </button>
      </div>
    </header>

    <div class="p-8 space-y-8">

      <?php if ($success): ?>
        <div class="p-4 bg-green-50 text-green-700 rounded-2xl border border-green-100 flex items-center gap-3 text-sm font-bold">
          <span class="material-symbols-outlined">verified</span>
          <?= $success ?>
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="p-4 bg-red-50 text-red-700 rounded-2xl border border-red-100 flex items-center gap-3 text-sm font-bold">
          <span class="material-symbols-outlined">error</span>
          <?= $error ?>
        </div>
      <?php endif; ?>

      <!-- Document List -->
      <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-left">
          <thead class="bg-slate-50 border-b border-slate-100">
            <tr>
              <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Document</th>
              <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Category</th>
              <th class="p-4 text-[10px] font-black text-slate-400 uppercase font-mono tracking-widest">Association</th>
              <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Uploaded</th>
              <th class="p-4 text-[10px] font-black text-slate-400 uppercase text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php if (empty($documents)): ?>
            <tr>
              <td colspan="5" class="p-12 text-center">
                <span class="material-symbols-outlined text-slate-200 text-6xl mb-2">folder_off</span>
                <p class="text-sm text-slate-400 font-bold italic uppercase tracking-widest">No documents archived yet.</p>
              </td>
            </tr>
            <?php endif; ?>
            <?php foreach ($documents as $doc): ?>
            <tr class="hover:bg-slate-50 transition-colors group">
              <td class="p-4">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined">description</span>
                  </div>
                  <div>
                    <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars($doc['title']) ?></p>
                    <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">By <?= htmlspecialchars($doc['uploader_name']) ?></p>
                  </div>
                </div>
              </td>
              <td class="p-4">
                <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-slate-600 text-[8px] font-black uppercase tracking-widest"><?= $doc['category'] ?></span>
              </td>
              <td class="p-4">
                <?php if ($doc['tenant_name']): ?>
                  <p class="text-[10px] font-black text-slate-900 uppercase tracking-tighter italic"><?= htmlspecialchars($doc['tenant_name']) ?></p>
                  <p class="text-[9px] text-slate-400 font-mono">Unit <?= $doc['unit_number'] ?></p>
                <?php elseif ($doc['unit_number']): ?>
                  <p class="text-[10px] font-black text-slate-900 uppercase tracking-tighter italic">Unit <?= $doc['unit_number'] ?></p>
                <?php else: ?>
                   <span class="text-[9px] text-slate-300 italic">General</span>
                <?php endif; ?>
              </td>
              <td class="p-4">
                <p class="text-[10px] font-bold text-slate-900"><?= date('d M, Y', strtotime($doc['uploaded_at'])) ?></p>
                <p class="text-[9px] text-slate-400 font-mono"><?= date('H:i', strtotime($doc['uploaded_at'])) ?></p>
              </td>
              <td class="p-4 text-right">
                <div class="flex justify-end gap-2">
                  <a href="<?= $doc['file_path'] ?>" target="_blank" class="p-2 hover:bg-slate-100 rounded-lg text-slate-400 hover:text-slate-900 transition-all bg-white border border-slate-200">
                    <span class="material-symbols-outlined text-sm">visibility</span>
                  </a>
                  <a href="<?= $doc['file_path'] ?>" download class="p-2 hover:bg-slate-100 rounded-lg text-slate-400 hover:text-slate-900 transition-all bg-white border border-slate-200">
                    <span class="material-symbols-outlined text-sm">download</span>
                  </a>
                  <a href="?delete=<?= $doc['id'] ?>" onclick="return confirm('Are you sure you want to remove this document permanently from the archive?')" class="p-2 hover:bg-red-50 rounded-lg text-slate-400 hover:text-red-600 transition-all bg-white border border-slate-200">
                    <span class="material-symbols-outlined text-sm">delete</span>
                  </a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    </div>

    <!-- Upload Modal -->
    <div id="uploadModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
       <div class="bg-white rounded-3xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
          <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
             <h3 class="font-serif-italic text-slate-900 text-lg">Archive Document</h3>
             <button onclick="document.getElementById('uploadModal').classList.add('hidden')" class="material-symbols-outlined text-slate-400 hover:text-slate-900 transition-colors">close</button>
          </div>
          <form method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
             <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Document Title</label>
                <input type="text" name="title" required placeholder="e.g. Unit 4B Lease Agreement" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-slate-900">
             </div>
             <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Category</label>
                <select name="category" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-slate-900">
                   <option value="Lease Agreement">Lease Agreement</option>
                   <option value="ID Proof">ID Proof</option>
                   <option value="Utility Bill">Utility Bill</option>
                   <option value="Other">Other</option>
                </select>
             </div>
             <div class="grid grid-cols-2 gap-4">
                <div>
                   <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Link To Resident</label>
                   <select name="tenant_id" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-slate-900">
                      <option value="">None (General)</option>
                      <?php foreach($tenants as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                      <?php endforeach; ?>
                   </select>
                </div>
                <div>
                   <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Link To Unit</label>
                   <select name="apartment_id" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-slate-900">
                      <option value="">None (General)</option>
                      <?php foreach($apartments as $a): ?>
                        <option value="<?= $a['id'] ?>">Unit <?= $a['unit_number'] ?></option>
                      <?php endforeach; ?>
                   </select>
                </div>
             </div>
             <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Select File</label>
                <input type="file" name="document" required class="w-full text-xs font-bold file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-[10px] file:font-black file:uppercase file:tracking-widest file:bg-slate-900 file:text-white hover:file:bg-slate-800">
             </div>
             <button type="submit" name="upload_doc" class="w-full bg-slate-900 text-white py-4 rounded-2xl font-black text-xs uppercase tracking-widest transition-all hover:bg-slate-800 shadow-xl shadow-slate-200">
                Confirm Archive
             </button>
          </form>
       </div>
    </div>

  </main>
</body>
</html>

<?php
require_once 'database.php';

$id = $_GET['id'] ?? null;
$service = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE service_id = ?");
    $stmt->execute([$id]);
    $service = $stmt->fetch();
    if (!$service) {
        die("Service not found.");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $raw_price = trim($_POST['price_per_unit']);
    $unit = trim($_POST['unit_type']);
    
    // Normalisasi input harga
    $clean_price = str_replace(['.', ','], ['', '.'], $raw_price);
    $price_num = floatval($clean_price);

    // Jika user mengetik angka dalam ribuan (misal 5, 10, 15.5), kalikan 1000
    // Jika user memasukkan harga lengkap (misal 10000), gunakan angka asli
    if ($price_num > 0 && $price_num < 1000) {
        $final_price = $price_num * 1000;
    } else {
        $final_price = $price_num;
    }
    
    if ($id) {
        $stmt = $pdo->prepare("UPDATE services SET name = ?, price_per_unit = ?, unit_type = ? WHERE service_id = ?");
        $stmt->execute([$name, $final_price, $unit, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO services (name, price_per_unit, unit_type) VALUES (?, ?, ?)");
        $stmt->execute([$name, $final_price, $unit]);
    }
    
    header("Location: services.php");
    exit;
}

// Untuk mode Edit: konversi harga DB (misal 10000) menjadi angka ribuan (10) agar pas di input
$display_price = '';
if ($service) {
    $p = floatval($service['price_per_unit']);
    if ($p >= 1000 && fmod($p, 1000) == 0) {
        $display_price = $p / 1000;
    } else if ($p >= 1000) {
        $display_price = $p / 1000;
    } else {
        $display_price = $p;
    }
}

include 'includes/header.php';
?>

<div class="header">
    <div>
        <h1 class="page-title"><?= $id ? 'Edit Service' : 'Add Service' ?></h1>
        <p class="text-muted" style="font-size: 0.9rem; margin-top: 0.2rem;">Kelola nama, harga per unit, dan tipe satuan layanan laundry</p>
    </div>
    <a href="services.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Kembali ke Services</a>
</div>

<div class="card" style="max-width: 600px;">
    <form method="POST">
        <div class="form-group">
            <label class="form-label">Nama Layanan</label>
            <input type="text" name="name" class="form-control" required
                   value="<?= htmlspecialchars($service['name'] ?? '') ?>"
                   placeholder="Contoh: Wash & Fold, Cuci Karpet, Setrika"
                   pattern="[A-Za-zÀ-ÿ0-9\s\&\-\/]+"
                   title="Nama layanan boleh berisi huruf, angka, dan spasi">
        </div>
        
        <div class="form-group">
            <label class="form-label">Harga Layanan (Dalam Ribuan / Thousands)</label>
            <div class="input-group">
                <span class="input-group-addon addon-prefix">Rp</span>
                <input type="number" min="0.01" step="any" name="price_per_unit" id="priceInput" class="form-control" required
                       value="<?= htmlspecialchars($display_price) ?>"
                       placeholder="Ketik angka, misal: 5, 10, 15">
                <span class="input-group-addon addon-suffix">.000</span>
            </div>
            
            <div id="pricePreview" style="margin-top: 0.5rem; font-weight: 700; color: var(--primary-600); font-size: 0.9rem; display: flex; align-items: center; gap: 0.4rem;">
                <i class="fa-solid fa-tag"></i> <span>Total Harga yang Tersimpan: Rp 0</span>
            </div>
            <small class="text-muted" style="display: block; margin-top: 0.35rem; font-size: 0.8rem;">
                <i class="fa-solid fa-circle-info" style="color: var(--accent-500);"></i> Cukup ketik angka depannya saja (Sistem otomatis menambahkan <strong>.000</strong>). Contoh: ketik <strong>10</strong> ➔ tersimpan <strong>Rp 10.000</strong>.
            </small>
        </div>
        
        <div class="form-group">
            <label class="form-label">Tipe Satuan (Unit Type)</label>
            <select name="unit_type" class="form-control" required>
                <option value="kg" <?= ($service['unit_type'] ?? '') == 'kg' ? 'selected' : '' ?>>kg (Kilogram)</option>
                <option value="pcs" <?= ($service['unit_type'] ?? '') == 'pcs' ? 'selected' : '' ?>>pcs (Potong/Pcs)</option>
                <option value="meter" <?= ($service['unit_type'] ?? '') == 'meter' ? 'selected' : '' ?>>meter (Meter Persegi)</option>
            </select>
        </div>
        
        <div style="margin-top: 1.75rem; text-align: right;">
            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;"><i class="fa-solid fa-save"></i> Simpan Layanan</button>
        </div>
    </form>
</div>

<script>
function updatePricePreview() {
    var val = parseFloat(document.getElementById('priceInput').value) || 0;
    var finalPrice = 0;
    if (val > 0 && val < 1000) {
        finalPrice = val * 1000;
    } else {
        finalPrice = val;
    }
    var formatted = new Intl.NumberFormat('id-ID').format(finalPrice);
    document.getElementById('pricePreview').querySelector('span').innerText = 'Total Harga yang Tersimpan: Rp ' + formatted;
}

document.getElementById('priceInput').addEventListener('input', updatePricePreview);
updatePricePreview();
</script>

<?php include 'includes/footer.php'; ?>

<?php
require_once 'database.php';

$id = $_GET['id'] ?? null;
$customer = null;

$old = [
    'name'        => '',
    'phone'       => '',
    'street'      => '',
    'landmark'    => '',
    'postal_code' => '',
];
$errors = [];

function parse_address_parts(string $address): array
{
    $parts = array_map('trim', explode('|', $address));
    $street = $parts[0] ?? '';
    $landmark = '';
    $postal = '';

    foreach ($parts as $part) {
        if (stripos($part, 'Patokan:') === 0 || stripos($part, 'Landmark:') === 0) {
            $landmark = trim(substr($part, strpos($part, ':') + 1));
        } elseif (stripos($part, 'Kode Pos:') === 0 || stripos($part, 'Postal Code:') === 0) {
            $postal = trim(substr($part, strpos($part, ':') + 1));
        }
    }

    return [$street, $landmark, $postal];
}

function build_address(string $street, string $landmark, string $postal): string
{
    $address = $street;
    if ($landmark !== '') {
        $address .= " | Landmark: {$landmark}";
    }
    if ($postal !== '') {
        $address .= " | Postal Code: {$postal}";
    }
    return $address;
}

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->execute([$id]);
    $customer = $stmt->fetch();
    if (!$customer) {
        die("Customer not found.");
    }
    $old['name'] = $customer['name'];
    $old['phone'] = $customer['phone'];
    [$old['street'], $old['landmark'], $old['postal_code']] = parse_address_parts($customer['address']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $street = trim($_POST['street'] ?? '');
    $landmark = trim($_POST['landmark'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');

    $old = [
        'name'        => $name,
        'phone'       => $phone,
        'street'      => $street,
        'landmark'    => $landmark,
        'postal_code' => $postal_code,
    ];

    if ($name === '') {
        $errors['name'] = 'Full name is required.';
    } elseif (!preg_match('/^[\p{L}\s\'\-]+$/u', $name)) {
        $errors['name'] = 'Full name must contain letters only — no numbers or symbols.';
    }

    if ($phone === '') {
        $errors['phone'] = 'Phone number is required.';
    } elseif (!preg_match('/^\d{10,13}$/', $phone)) {
        $errors['phone'] = 'Phone number must be numeric (10-13 digits).';
    }

    if ($street === '') {
        $errors['street'] = 'Street address is required.';
    }
    if ($postal_code === '') {
        $errors['postal_code'] = 'Postal code is required.';
    } elseif (!preg_match('/^\d{5}$/', $postal_code)) {
        $errors['postal_code'] = 'Postal code must be exactly 5 digits.';
    }

    if (empty($errors)) {
        $address = build_address($street, $landmark, $postal_code);

        if ($id) {
            $stmt = $pdo->prepare("UPDATE customers SET name = ?, phone = ?, address = ? WHERE customer_id = ?");
            $stmt->execute([$name, $phone, $address, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO customers (name, phone, address) VALUES (?, ?, ?)");
            $stmt->execute([$name, $phone, $address]);
        }

        header("Location: customers.php");
        exit;
    }
}

include 'includes/header.php';
?>

<div class="header">
    <div>
        <h1 class="page-title"><?= $id ? 'Edit Customer' : 'Add Customer' ?></h1>
        <p class="text-muted" style="font-size: 0.9rem; margin-top: 0.2rem;">Manage customer profile, contact details, and address records</p>
    </div>
    <a href="customers.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Customers</a>
</div>

<div class="card" style="max-width: 600px;">
    <?php if (!empty($errors)): ?>
        <div style="background:#ffe4e6; border:1px solid #fecdd3; color:#9f1239; padding:0.75rem 1rem; border-radius:8px; margin-bottom:1rem; font-size:0.9rem;">
            Please fix the highlighted fields below.
        </div>
    <?php endif; ?>

    <form method="POST" id="customerForm" novalidate>
        <div class="form-group">
            <label class="form-label">Full Name</label>
            <input
                type="text"
                name="name"
                id="nameInput"
                class="form-control"
                required
                value="<?= htmlspecialchars($old['name']) ?>"
                placeholder="e.g. John Doe"
                pattern="[A-Za-zÀ-ÖØ-öø-ÿ\s'\-]+"
                title="Letters only, no numbers or symbols">
            <?php if (isset($errors['name'])): ?>
                <div style="color:#9f1239; font-size:0.8rem; margin-top:0.25rem;"><?= htmlspecialchars($errors['name']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label class="form-label">Phone Number / WhatsApp</label>
            <input
                type="tel"
                name="phone"
                id="phoneInput"
                class="form-control"
                required
                value="<?= htmlspecialchars($old['phone']) ?>"
                placeholder="e.g. 081234567890"
                inputmode="numeric"
                maxlength="13"
                pattern="\d{10,13}"
                title="Numbers only, 10-13 digits">
            <?php if (isset($errors['phone'])): ?>
                <div style="color:#9f1239; font-size:0.8rem; margin-top:0.25rem;"><?= htmlspecialchars($errors['phone']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label class="form-label">Address Details</label>

            <div style="margin-bottom:0.6rem;">
                <label style="font-size:0.8rem; color:var(--gray-500); display:block; margin-bottom:0.25rem;">Street Name / Full Address</label>
                <input
                    type="text"
                    name="street"
                    class="form-control"
                    required
                    value="<?= htmlspecialchars($old['street']) ?>"
                    placeholder="e.g. Main St. No. 12, Block B">
                <?php if (isset($errors['street'])): ?>
                    <div style="color:#9f1239; font-size:0.8rem; margin-top:0.25rem;"><?= htmlspecialchars($errors['street']) ?></div>
                <?php endif; ?>
            </div>

            <div style="margin-bottom:0.6rem;">
                <label style="font-size:0.8rem; color:var(--gray-500); display:block; margin-bottom:0.25rem;">Landmark / Benchmark (optional)</label>
                <input
                    type="text"
                    name="landmark"
                    class="form-control"
                    value="<?= htmlspecialchars($old['landmark']) ?>"
                    placeholder="e.g. Near Indomaret / Next to mosque">
            </div>

            <div>
                <label style="font-size:0.8rem; color:var(--gray-500); display:block; margin-bottom:0.25rem;">Postal Code (5 Digits)</label>
                <input
                    type="text"
                    name="postal_code"
                    id="postalInput"
                    class="form-control"
                    required
                    value="<?= htmlspecialchars($old['postal_code']) ?>"
                    placeholder="e.g. 12345"
                    inputmode="numeric"
                    maxlength="5"
                    pattern="\d{5}"
                    title="5 digit postal code"
                    style="max-width: 160px;">
                <?php if (isset($errors['postal_code'])): ?>
                    <div style="color:#9f1239; font-size:0.8rem; margin-top:0.25rem;"><?= htmlspecialchars($errors['postal_code']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div style="margin-top: 1.5rem; text-align: right;">
            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;"><i class="fa-solid fa-save"></i> Save Customer</button>
        </div>
    </form>
</div>

<script>
document.getElementById('nameInput').addEventListener('input', function (e) {
    const cleaned = this.value.replace(/[^A-Za-zÀ-ÖØ-öø-ÿ\s'\-]/g, '');
    if (cleaned !== this.value) {
        alert('Full Name must contain letters and spaces only.');
        this.value = cleaned;
    }
});

document.getElementById('phoneInput').addEventListener('input', function (e) {
    const cleaned = this.value.replace(/\D/g, '').slice(0, 13);
    if (cleaned !== this.value) {
        alert('Phone Number must contain numbers only (10-13 digits).');
        this.value = cleaned;
    }
});

document.getElementById('postalInput').addEventListener('input', function (e) {
    const cleaned = this.value.replace(/\D/g, '').slice(0, 5);
    if (cleaned !== this.value) {
        alert('Postal Code must be exactly 5 digits.');
        this.value = cleaned;
    }
});
</script>

<?php include 'includes/footer.php'; ?>

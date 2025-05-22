<?php
require_once '../config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">Daftar Akun</div>
                <div class="card-body">
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
                    <?php elseif (isset($_GET['register']) && $_GET['register'] === 'success'): ?>
                        <div class="alert alert-success">Pendaftaran akun baru berhasil!</div>
                    <?php endif; ?>
                    <form action="register_proses.php" method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required onchange="toggleTokenField()">
                                <option value="muzakki">Muzakki (Pengunjung)</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="mb-3" id="tokenField" style="display:none;">
                            <label for="token" class="form-label">Token Khusus (Diperlukan untuk Admin)</label>
                            <input type="text" class="form-control" id="token" name="token">
                            <small class="text-muted">Token khusus diperlukan untuk mendaftarkan akun Admin.</small>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Daftar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function toggleTokenField() {
    // Token field hanya ditampilkan untuk admin
    var role = document.getElementById('role').value;
    document.getElementById('tokenField').style.display = (role === 'admin') ? 'block' : 'none';
    
    // Buat field token required hanya jika role adalah admin
    var tokenInput = document.getElementById('token');
    if (role === 'admin') {
        tokenInput.setAttribute('required', 'required');
    } else {
        tokenInput.removeAttribute('required');
    }
}

// Initialize when page loads
window.onload = function() {
    toggleTokenField();
};
</script>
</body>
</html>
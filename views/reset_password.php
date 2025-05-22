<?php
session_start();
if (!isset($_SESSION['reset_user_id'])) {
    header('Location: lupa_password.php?error=Akses tidak valid!');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">Reset Password</div>
                <div class="card-body">
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
                    <?php endif; ?>
                    <form action="reset_password_proses.php" method="POST">
                        <div class="mb-3">
                            <label for="password_baru" class="form-label">Password Baru</label>
                            <input type="password" class="form-control" id="password_baru" name="password_baru" required>
                        </div>
                        <div class="mb-3">
                            <label for="password_konfirmasi" class="form-label">Konfirmasi Password Baru</label>
                            <input type="password" class="form-control" id="password_konfirmasi" name="password_konfirmasi" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Simpan Password Baru</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html> 
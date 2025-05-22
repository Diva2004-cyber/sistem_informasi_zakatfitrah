<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-warning text-dark">Lupa Password</div>
                <div class="card-body">
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
                    <?php elseif (isset($_GET['success'])): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($_GET['success']); ?>
                            <?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
                                <br>Password berhasil direset! Silakan login dengan password baru Anda.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <form action="lupa_password_proses.php" method="POST">
                        <!-- Hanya muzakki yang diperbolehkan reset password -->
                        <input type="hidden" name="role" value="muzakki">
                        <div class="alert alert-info">Fitur ini hanya tersedia untuk akun Muzakki (Pengunjung).</div>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <!-- Key field for reset has been removed as only muzakki can reset password -->
                        <button type="submit" class="btn btn-warning w-100">Lanjutkan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
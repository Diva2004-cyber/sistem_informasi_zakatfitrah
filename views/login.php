<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Manajemen Zakat Fitrah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Poppins', sans-serif;
        }
        .login-container {
            max-width: 400px;
            margin: 80px auto;
            padding: 30px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo-container img {
            max-width: 200px;
            height: auto;
        }
        .btn-primary {
            background-color: #2D9596;
            border-color: #2D9596;
        }
        .btn-primary:hover {
            background-color: #267b7c;
            border-color: #267b7c;
        }
        a {
            color: #F0B86E;
        }
        a:hover {
            color: #e6a34d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="logo-container">
                <img src="../assets/img/logo.svg" alt="Logo Zakat Fitrah">
            </div>
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_GET['register']) && $_GET['register'] === 'success'): ?>
                <div class="alert alert-success">
                    <?php echo isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Pendaftaran akun Muzakki berhasil! Silakan login dengan username dan password yang telah Anda daftarkan.'; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['login']) && $_GET['login'] === 'success'): ?>
                <div class="alert alert-success">
                    <?php echo isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Login berhasil! Selamat datang kembali.'; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
                <div class="alert alert-success">Password berhasil direset! Silakan login dengan password baru Anda.</div>
            <?php endif; ?>
            <form action="../process_login.php" method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
            <div class="text-center mt-3">
                <a href="register.php">Belum punya akun? Daftar di sini</a>
            </div>
            <div class="text-center mt-2">
                <a href="lupa_password.php">Lupa password?</a>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
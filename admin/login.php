<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (admin_logged_in()) {
    redirect('index.php');
}

$error = '';
$usernameValue = '';

if (isset($_GET['refresh_captcha'])) {
    refresh_login_captcha();
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $captcha = trim((string) ($_POST['captcha'] ?? ''));
    $usernameValue = $username;

    $lockedSeconds = login_locked_seconds();

    if ($lockedSeconds > 0) {
        $error = 'Terlalu banyak percobaan login. Coba lagi dalam ' . $lockedSeconds . ' detik.';
    } elseif (!verify_login_captcha($captcha)) {
        record_failed_login();
        $error = 'Captcha belum benar. Silakan coba lagi.';
    } else {
        try {
            $statement = pdo()->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
            $statement->execute([$username]);
            $admin = $statement->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                reset_login_security();
                set_admin_session($admin);
                set_admin_flash('success', 'Login berhasil. Selamat datang di dashboard admin.');
                redirect('index.php');
            }

            record_failed_login();
            $lockedSeconds = login_locked_seconds();
            $error = $lockedSeconds > 0
                ? 'Terlalu banyak percobaan login. Coba lagi dalam ' . $lockedSeconds . ' detik.'
                : 'Username atau password salah.';
        } catch (Throwable $exception) {
            $error = 'Database belum siap. Import database.sql lewat phpMyAdmin terlebih dahulu.';
        }
    }

    if ($error !== '') {
        refresh_login_captcha();
    }
}

$captchaQuestion = login_captcha_question();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="icon" href="../images/favicon/logo2.png" type="image/png" />
    <title>Login Admin</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/unicons.css">
    <link rel="stylesheet" href="admin.css">
  </head>
  <body class="login-body">
    <div class="login-shell">
      <div class="login-panel">
        <aside class="login-hero-panel">
          <div class="login-brand">
            <span><i class="uil uil-shield-check"></i></span>
            <div>
              <strong>Admin Portfolio</strong>
              <small>Secure Enterprise Access</small>
            </div>
          </div>

          <div class="login-hero-copy">
            <small>Control Center</small>
            <h1>Masuk ke dashboard admin</h1>
            <p>Kelola about, project, berita, resume, contact, dan konten website dari satu ruang kerja.</p>
          </div>

          <div class="login-security-list">
            <div><i class="uil uil-lock-access"></i> Session aktif otomatis diperbarui saat digunakan.</div>
            <div><i class="uil uil-calculator-alt"></i> Captcha session membantu menahan percobaan login otomatis.</div>
            <div><i class="uil uil-key-skeleton"></i> Password tetap diverifikasi dengan hash PHP.</div>
          </div>
        </aside>

        <main class="login-card">
          <div class="login-card-header">
            <span class="login-card-icon"><i class="uil uil-lock-alt"></i></span>
            <div>
              <h1>Login Admin</h1>
              <p>Gunakan akun admin untuk melanjutkan.</p>
            </div>
          </div>

          <?php if ($error): ?>
            <div class="alert alert-danger login-alert" role="alert"><i class="uil uil-exclamation-triangle"></i> <?= e($error) ?></div>
          <?php endif; ?>

          <form method="post" action="login.php" class="login-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-group">
              <label for="username">Username</label>
              <div class="login-input-wrap">
                <i class="uil uil-user"></i>
                <input type="text" class="form-control" id="username" name="username" value="<?= e($usernameValue) ?>" autocomplete="username" required autofocus>
              </div>
            </div>
            <div class="form-group">
              <label for="password">Password</label>
              <div class="login-input-wrap">
                <i class="uil uil-key-skeleton"></i>
                <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
              </div>
            </div>
            <div class="form-group">
              <label for="captcha">Captcha</label>
              <div class="captcha-row">
                <div class="captcha-box">
                  <span><?= e($captchaQuestion) ?></span>
                  <small>= ?</small>
                </div>
                <div class="login-input-wrap captcha-input">
                  <i class="uil uil-calculator-alt"></i>
                  <input type="number" class="form-control" id="captcha" name="captcha" inputmode="numeric" required>
                </div>
                <a class="captcha-refresh" href="login.php?refresh_captcha=1" title="Ganti captcha"><i class="uil uil-refresh"></i></a>
              </div>
            </div>
            <button type="submit" class="btn btn-warning btn-block font-weight-bold login-submit">
              Masuk Dashboard <i class="uil uil-arrow-right"></i>
            </button>
          </form>

          <div class="login-meta">
            <span><strong></strong><strong></strong></span>
            <a href="../index.php"><i class="uil uil-home-alt"></i> Kembali ke website</a>
          </div>
        </main>
      </div>
    </div>
  </body>
</html>

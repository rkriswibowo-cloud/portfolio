<?php
declare(strict_types=1);
require_once __DIR__ . '/config/content.php';
start_app_session();
if (user_logged_in()) redirect('my_courses.php');
$pdo = pdo(true);
$error = '';
$nameValue = '';
$emailValue = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');
    $nameValue = $name; $emailValue = $email;
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
        $error = 'Nama, email valid, dan password minimal 6 karakter wajib diisi.';
    } elseif ($password !== $confirm) {
        $error = 'Konfirmasi password belum sama.';
    } elseif (!$pdo) {
        $error = 'Database belum tersambung.';
    } elseif (find_user_by_email($email, $pdo)) {
        $error = 'Email sudah terdaftar. Silakan login.';
    } else {
        try {
            $st = $pdo->prepare('INSERT INTO users (name, email, password_hash, is_active) VALUES (?, ?, ?, 1)');
            $st->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $user = ['id' => (int) $pdo->lastInsertId(), 'name' => $name, 'email' => $email];
            set_user_session($user);
            set_user_flash('success', 'Registrasi berhasil. Masukkan token enrollment untuk membuka kelas.');
            redirect('my_courses.php');
        } catch (Throwable $e) {
            $error = 'Registrasi gagal. Jalankan migration user enrollment terlebih dahulu.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"><title>Daftar User</title><link rel="stylesheet" href="css/bootstrap.min.css"><link rel="stylesheet" href="css/unicons.css"><link rel="stylesheet" href="css/tooplate-style.css"></head>
<body><section class="py-5"><div class="container"><div class="row justify-content-center"><div class="col-lg-5 col-md-7 col-12"><a href="index.php" class="news-back-link"><i class="uil uil-angle-left"></i> Kembali ke website</a><div class="course-access-card"><h1><i class="uil uil-user-plus"></i> Daftar User</h1><p>Buat akun peserta course. Setelah login, gunakan token enrollment dari admin.</p><?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="form-group"><label>Nama</label><input class="form-control" name="name" value="<?= e($nameValue) ?>" required></div><div class="form-group"><label>Email</label><input type="email" class="form-control" name="email" value="<?= e($emailValue) ?>" required></div><div class="form-group"><label>Password</label><input type="password" class="form-control" name="password" required></div><div class="form-group"><label>Konfirmasi Password</label><input type="password" class="form-control" name="password_confirm" required></div><button class="btn custom-btn custom-btn-bg custom-btn-link btn-block" type="submit">Daftar</button></form><hr><p class="mb-0">Sudah punya akun? <a href="user_login.php">Login user</a></p></div></div></div></div></section></body></html>

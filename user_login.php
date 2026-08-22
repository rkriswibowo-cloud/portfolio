<?php
declare(strict_types=1);
require_once __DIR__ . '/config/content.php';
start_app_session();
if (user_logged_in()) redirect('my_courses.php');
$pdo = pdo(true);
$settings = get_settings($pdo);
$error = '';
$emailValue = '';

function generate_login_captcha(): void
{
    $_SESSION['user_login_captcha_a'] = random_int(1, 9);
    $_SESSION['user_login_captcha_b'] = random_int(1, 9);
    $_SESSION['user_login_captcha_answer'] = $_SESSION['user_login_captcha_a'] + $_SESSION['user_login_captcha_b'];
}

if (!isset($_SESSION['user_login_captcha_answer'])) {
    generate_login_captcha();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $captcha = trim((string) ($_POST['captcha'] ?? ''));
    $expectedCaptcha = (string) ($_SESSION['user_login_captcha_answer'] ?? '');
    $emailValue = $email;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Email dan password wajib diisi dengan benar.';
    } elseif ($captcha === '' || !hash_equals($expectedCaptcha, $captcha)) {
        $error = 'Kode CAPTCHA tidak sesuai. Silakan coba lagi.';
        generate_login_captcha();
    } else {
        $user = find_user_by_email($email, $pdo);
        if ($user && (int) ($user['is_active'] ?? 0) === 1 && password_verify($password, (string) $user['password_hash'])) {
            unset($_SESSION['user_login_captcha_a'], $_SESSION['user_login_captcha_b'], $_SESSION['user_login_captcha_answer']);
            set_user_session($user);
            set_user_flash('success', 'Login berhasil. Silakan lanjutkan belajar.');
            redirect('my_courses.php');
        }
        $error = 'Email atau password salah, atau akun belum aktif.';
        generate_login_captcha();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Login User</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/unicons.css">
    <link rel="stylesheet" href="css/tooplate-style.css">
    <style>
        .captcha-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border: 1px dashed #6c63ff;
            border-radius: 12px;
            background: #f7f7ff;
            margin-bottom: 10px;
        }
        .captcha-question {
            font-weight: 700;
            font-size: 18px;
            letter-spacing: .5px;
            color: #222;
            margin: 0;
        }
        .captcha-note {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7 col-12">
                <a href="index.php" class="news-back-link"><i class="uil uil-angle-left"></i> Kembali ke website</a>
                <div class="course-access-card">
                    <h1><i class="uil uil-user-circle"></i> Login User</h1>
                    <p>Login ini khusus peserta course, terpisah dari login admin.</p>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>
                    <form method="post" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" class="form-control" name="email" value="<?= e($emailValue) ?>" required autofocus autocomplete="email">
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" class="form-control" name="password" required autocomplete="current-password">
                        </div>
                        <div class="form-group">
                            <label>CAPTCHA Keamanan</label>
                            <div class="captcha-box">
                                <p class="captcha-question"><?= e((string) ($_SESSION['user_login_captcha_a'] ?? '')) ?> + <?= e((string) ($_SESSION['user_login_captcha_b'] ?? '')) ?> = ?</p>
                                <i class="uil uil-shield-check"></i>
                            </div>
                            <div class="captcha-note">Masukkan hasil penjumlahan di atas untuk melanjutkan login.</div>
                            <input type="number" class="form-control" name="captcha" placeholder="Jawaban CAPTCHA" required inputmode="numeric" autocomplete="off">
                        </div>
                        <button class="btn custom-btn custom-btn-bg custom-btn-link btn-block" type="submit">Masuk</button>
                    </form>
                    <hr>
                    <p class="mb-0">Belum punya akun? <a href="user_register.php">Daftar user</a></p>
                </div>
            </div>
        </div>
    </div>
</section>
</body>
</html>

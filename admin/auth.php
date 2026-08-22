<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/content.php';

start_app_session();

const ADMIN_SESSION_TIMEOUT = 3600;
const ADMIN_MAX_LOGIN_ATTEMPTS = 5;
const ADMIN_LOCK_SECONDS = 60;

function admin_logged_in(): bool
{
    if (empty($_SESSION['admin_id'])) {
        return false;
    }

    $lastActivity = (int) ($_SESSION['admin_last_activity'] ?? 0);
    if ($lastActivity > 0 && time() - $lastActivity > ADMIN_SESSION_TIMEOUT) {
        clear_admin_session();
        return false;
    }

    $_SESSION['admin_last_activity'] = time();

    return true;
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        redirect('login');
    }
}

function current_admin_name(): string
{
    return (string) ($_SESSION['admin_name'] ?? 'Admin');
}

function current_admin_id(): int
{
    return (int) ($_SESSION['admin_id'] ?? 0);
}

function current_admin_username(): string
{
    return (string) ($_SESSION['admin_username'] ?? '');
}

function clear_admin_session(): void
{
    unset(
        $_SESSION['admin_id'],
        $_SESSION['admin_name'],
        $_SESSION['admin_username'],
        $_SESSION['admin_login_at'],
        $_SESSION['admin_last_activity'],
        $_SESSION['admin_ip'],
        $_SESSION['admin_user_agent']
    );
}

function set_admin_session(array $admin): void
{
    session_regenerate_id(true);

    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_name'] = (string) $admin['name'];
    $_SESSION['admin_username'] = (string) ($admin['username'] ?? '');
    $_SESSION['admin_login_at'] = time();
    $_SESSION['admin_last_activity'] = time();
    $_SESSION['admin_ip'] = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $_SESSION['admin_user_agent'] = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

function set_admin_flash(string $type, string $message): void
{
    $_SESSION['admin_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function get_admin_flash(): ?array
{
    if (empty($_SESSION['admin_flash'])) {
        return null;
    }

    $flash = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);

    return $flash;
}

function refresh_login_captcha(): void
{
    $left = random_int(2, 9);
    $right = random_int(1, 9);

    $_SESSION['login_captcha_question'] = $left . ' + ' . $right;
    $_SESSION['login_captcha_answer'] = (string) ($left + $right);
}

function login_captcha_question(): string
{
    if (empty($_SESSION['login_captcha_question']) || empty($_SESSION['login_captcha_answer'])) {
        refresh_login_captcha();
    }

    return (string) $_SESSION['login_captcha_question'];
}

function verify_login_captcha(string $answer): bool
{
    $expected = (string) ($_SESSION['login_captcha_answer'] ?? '');
    return $expected !== '' && hash_equals($expected, trim($answer));
}

function reset_login_security(): void
{
    unset(
        $_SESSION['login_captcha_question'],
        $_SESSION['login_captcha_answer'],
        $_SESSION['login_attempts'],
        $_SESSION['login_locked_until']
    );
}

function login_locked_seconds(): int
{
    $lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);

    if ($lockedUntil <= time()) {
        unset($_SESSION['login_locked_until']);
        return 0;
    }

    return $lockedUntil - time();
}

function record_failed_login(): void
{
    $_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0) + 1;

    if ($_SESSION['login_attempts'] >= ADMIN_MAX_LOGIN_ATTEMPTS) {
        $_SESSION['login_locked_until'] = time() + ADMIN_LOCK_SECONDS;
        $_SESSION['login_attempts'] = 0;
    }
}

function upload_admin_image(array $file, string $folder, string $prefix): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload gambar gagal.');
    }

    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        throw new RuntimeException('Ukuran gambar maksimal 2 MB.');
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('Format gambar harus JPG, PNG, GIF, atau WEBP.');
    }

    $folder = trim($folder, '/');
    $targetDirectory = __DIR__ . '/../' . $folder;

    if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true)) {
        throw new RuntimeException('Folder upload belum bisa dibuat.');
    }

    $fileName = $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = $targetDirectory . '/' . $fileName;

    if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Gambar belum bisa disimpan.');
    }

    return $folder . '/' . $fileName;
}

function upload_project_image(array $file): ?string
{
    return upload_admin_image($file, 'images/project', 'project');
}

function upload_news_image(array $file): ?string
{
    return upload_admin_image($file, 'images/news', 'news');
}

function upload_course_image(array $file): ?string
{
    return upload_admin_image($file, 'images/course', 'course');
}

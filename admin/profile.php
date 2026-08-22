<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

require_admin();

$pdo = pdo();
$adminId = current_admin_id();

$statement = $pdo->prepare('SELECT id, username, name, password_hash, created_at FROM admins WHERE id = ? LIMIT 1');
$statement->execute([$adminId]);
$admin = $statement->fetch();

if (!$admin) {
    clear_admin_session();
    set_admin_flash('danger', 'Session admin tidak valid. Silakan login ulang.');
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_profile') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $username = trim((string) ($_POST['username'] ?? ''));

            if ($name === '') {
                throw new RuntimeException('Nama admin wajib diisi.');
            }

            if ($username === '') {
                throw new RuntimeException('Username admin wajib diisi.');
            }

            if (strlen($username) < 3 || strlen($username) > 50) {
                throw new RuntimeException('Username harus 3 sampai 50 karakter.');
            }

            if (!preg_match('/^[A-Za-z0-9_.-]+$/', $username)) {
                throw new RuntimeException('Username hanya boleh berisi huruf, angka, titik, underscore, atau strip.');
            }

            $duplicateStatement = $pdo->prepare('SELECT id FROM admins WHERE username = ? AND id <> ? LIMIT 1');
            $duplicateStatement->execute([$username, $adminId]);

            if ($duplicateStatement->fetch()) {
                throw new RuntimeException('Username sudah digunakan admin lain.');
            }

            $updateStatement = $pdo->prepare('UPDATE admins SET name = ?, username = ? WHERE id = ?');
            $updateStatement->execute([$name, $username, $adminId]);

            $_SESSION['admin_name'] = $name;
            $_SESSION['admin_username'] = $username;

            set_admin_flash('success', 'Profil admin berhasil diperbarui.');
            redirect('profile.php');
        }

        if ($action === 'change_password') {
            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                throw new RuntimeException('Semua field password wajib diisi.');
            }

            if (!password_verify($currentPassword, (string) $admin['password_hash'])) {
                throw new RuntimeException('Password lama tidak sesuai.');
            }

            if (strlen($newPassword) < 8) {
                throw new RuntimeException('Password baru minimal 8 karakter.');
            }

            if ($newPassword !== $confirmPassword) {
                throw new RuntimeException('Konfirmasi password baru belum sama.');
            }

            if (password_verify($newPassword, (string) $admin['password_hash'])) {
                throw new RuntimeException('Password baru tidak boleh sama dengan password lama.');
            }

            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStatement = $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
            $updateStatement->execute([$passwordHash, $adminId]);

            set_admin_flash('success', 'Password admin berhasil diganti.');
            redirect('profile.php');
        }

        throw new RuntimeException('Aksi profil tidak dikenali.');
    } catch (Throwable $exception) {
        set_admin_flash('danger', $exception->getMessage());
        redirect('profile.php');
    }
}

admin_header('Edit Profil');
?>
<div class="row">
  <div class="col-lg-5">
    <div class="admin-card profile-summary-card">
      <div class="profile-avatar">
        <i class="uil uil-user-circle"></i>
      </div>
      <h2 class="mb-1"><?= e((string) $admin['name']) ?></h2>
      <p class="text-muted mb-3">@<?= e((string) $admin['username']) ?></p>
      <div class="profile-meta-list">
        <div>
          <span>Role</span>
          <strong>Administrator</strong>
        </div>
        <div>
          <span>Akun dibuat</span>
          <strong><?= !empty($admin['created_at']) ? e(format_course_datetime((string) $admin['created_at'])) : '-' ?></strong>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="settings-accordion" id="profileAccordion">
      <div class="admin-card setting-card">
        <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#profileSettings" aria-expanded="true" aria-controls="profileSettings">
          <span><i class="uil uil-user-square"></i> Informasi Profil <small>Ubah nama tampilan dan username login admin.</small></span>
          <i class="uil uil-angle-down"></i>
        </button>
        <div id="profileSettings" class="collapse show" data-parent="#profileAccordion">
          <form method="post" action="profile.php" class="settings-body">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_profile">
            <div class="form-group">
              <label for="name">Nama admin</label>
              <input type="text" class="form-control" id="name" name="name" value="<?= e((string) $admin['name']) ?>" maxlength="100" required>
            </div>
            <div class="form-group">
              <label for="username">Username login</label>
              <input type="text" class="form-control" id="username" name="username" value="<?= e((string) $admin['username']) ?>" minlength="3" maxlength="50" pattern="[A-Za-z0-9_.-]+" autocomplete="username" required>
              <small class="form-text text-muted">Gunakan huruf, angka, titik, underscore, atau strip tanpa spasi.</small>
            </div>
            <button type="submit" class="btn btn-warning font-weight-bold"><i class="uil uil-check-circle"></i> Simpan Profil</button>
          </form>
        </div>
      </div>

      <div class="admin-card setting-card">
        <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#passwordSettings" aria-expanded="false" aria-controls="passwordSettings">
          <span><i class="uil uil-key-skeleton"></i> Ubah Password <small>Masukkan password lama sebelum menyimpan password baru.</small></span>
          <i class="uil uil-angle-down"></i>
        </button>
        <div id="passwordSettings" class="collapse" data-parent="#profileAccordion">
          <form method="post" action="profile.php" class="settings-body">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="change_password">
            <div class="form-group">
              <label for="current_password">Password lama</label>
              <input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password" required>
            </div>
            <div class="form-group">
              <label for="new_password">Password baru</label>
              <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" autocomplete="new-password" required>
              <small class="form-text text-muted">Minimal 8 karakter agar lebih sulit ditebak.</small>
            </div>
            <div class="form-group">
              <label for="confirm_password">Konfirmasi password baru</label>
              <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required>
            </div>
            <button type="submit" class="btn btn-warning font-weight-bold" onclick="return confirm('Ganti password admin sekarang?')"><i class="uil uil-lock-alt"></i> Ganti Password</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php admin_footer(); ?>

<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

require_admin();

function admin_user_status_filter(string $status): string
{
    return in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all';
}

function admin_users_url(int $page, string $search, string $status, int $perPage): string
{
    $params = [
        'page' => max(1, $page),
        'per_page' => $perPage,
    ];

    if ($search !== '') {
        $params['q'] = $search;
    }

    if ($status !== 'all') {
        $params['status'] = $status;
    }

    return 'users.php?' . http_build_query($params);
}

$pdo = pdo();
$allowedPerPage = [10, 25, 50];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $action = (string) ($_POST['action'] ?? 'save');
    $returnSearch = trim((string) ($_POST['q'] ?? ''));
    $returnStatus = admin_user_status_filter((string) ($_POST['status'] ?? 'all'));
    $returnPage = max(1, (int) ($_POST['page'] ?? 1));
    $returnPerPage = (int) ($_POST['per_page'] ?? 10);
    if (!in_array($returnPerPage, $allowedPerPage, true)) {
        $returnPerPage = 10;
    }

    try {
        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('User tidak valid.');
            }

            $statement = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $statement->execute([$id]);

            if ($statement->rowCount() < 1) {
                throw new RuntimeException('User tidak ditemukan.');
            }

            set_admin_flash('success', 'User berhasil dihapus.');
        } elseif ($action === 'toggle_status') {
            $id = (int) ($_POST['id'] ?? 0);
            $newStatus = (int) ($_POST['new_status'] ?? 0) === 1;

            if ($id <= 0) {
                throw new RuntimeException('User tidak valid.');
            }

            if (!set_user_active_status($id, $newStatus, $pdo)) {
                throw new RuntimeException('Status user gagal diubah.');
            }

            set_admin_flash('success', $newStatus ? 'User berhasil diaktifkan.' : 'User berhasil dinonaktifkan.');
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');
            $confirmPassword = (string) ($_POST['password_confirm'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($name === '') {
                throw new RuntimeException('Nama user wajib diisi.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Email user tidak valid.');
            }

            $duplicateStatement = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $duplicateStatement->execute([$email, $id]);
            if ($duplicateStatement->fetch()) {
                throw new RuntimeException('Email sudah digunakan user lain.');
            }

            $passwordFilled = $password !== '' || $confirmPassword !== '';
            if ($id <= 0 && !$passwordFilled) {
                throw new RuntimeException('Password wajib diisi untuk user baru.');
            }

            if ($passwordFilled) {
                if (strlen($password) < 6) {
                    throw new RuntimeException('Password minimal 6 karakter.');
                }

                if ($password !== $confirmPassword) {
                    throw new RuntimeException('Konfirmasi password belum sama.');
                }
            }

            if ($id > 0) {
                if ($passwordFilled) {
                    $statement = $pdo->prepare('UPDATE users SET name = ?, email = ?, password_hash = ?, is_active = ? WHERE id = ?');
                    $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $isActive, $id]);
                } else {
                    $statement = $pdo->prepare('UPDATE users SET name = ?, email = ?, is_active = ? WHERE id = ?');
                    $statement->execute([$name, $email, $isActive, $id]);
                }

                set_admin_flash('success', 'User berhasil diperbarui.');
            } else {
                $statement = $pdo->prepare('INSERT INTO users (name, email, password_hash, is_active) VALUES (?, ?, ?, ?)');
                $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $isActive]);
                set_admin_flash('success', 'User baru berhasil ditambahkan.');
            }
        }
    } catch (Throwable $exception) {
        set_admin_flash('danger', $exception->getMessage());
    }

    redirect(admin_users_url($returnPage, $returnSearch, $returnStatus, $returnPerPage));
}

$search = trim((string) ($_GET['q'] ?? ''));
$status = admin_user_status_filter((string) ($_GET['status'] ?? 'all'));
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 10;
}

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(u.name LIKE :q_name OR u.email LIKE :q_email)';
    $params[':q_name'] = '%' . $search . '%';
    $params[':q_email'] = '%' . $search . '%';
}

if ($status === 'active') {
    $where[] = 'COALESCE(u.is_active, 1) = :status';
    $params[':status'] = 1;
} elseif ($status === 'inactive') {
    $where[] = 'COALESCE(u.is_active, 1) = :status';
    $params[':status'] = 0;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStatement = $pdo->prepare("SELECT COUNT(*) FROM users u {$whereSql}");
foreach ($params as $key => $value) {
    $countStatement->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$countStatement->execute();
$totalUsers = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalUsers / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
$offset = ($currentPage - 1) * $perPage;

$usersStatement = $pdo->prepare("
    SELECT
        u.id,
        u.name,
        u.email,
        COALESCE(u.is_active, 1) AS is_active,
        u.created_at,
        u.updated_at,
        COUNT(DISTINCT e.id) AS enrollment_count,
        COUNT(DISTINCT CASE WHEN e.id IS NOT NULL AND COALESCE(e.is_active, 1) = 1 THEN e.id END) AS active_enrollment_count,
        COUNT(DISTINCT cp.id) AS progress_count,
        MAX(cal.accessed_at) AS last_accessed_at
    FROM users u
    LEFT JOIN course_enrollments e ON e.user_id = u.id
    LEFT JOIN course_progress cp ON cp.user_id = u.id
    LEFT JOIN course_access_logs cal ON cal.user_id = u.id
    {$whereSql}
    GROUP BY u.id, u.name, u.email, u.is_active, u.created_at, u.updated_at
    ORDER BY u.created_at DESC, u.id DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $key => $value) {
    $usersStatement->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$usersStatement->bindValue(':limit', $perPage, PDO::PARAM_INT);
$usersStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
$usersStatement->execute();
$users = $usersStatement->fetchAll();

$stats = [
    'total' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'active' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE COALESCE(is_active, 1) = 1')->fetchColumn(),
    'inactive' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE COALESCE(is_active, 1) = 0')->fetchColumn(),
    'enrollments' => (int) $pdo->query('SELECT COUNT(*) FROM course_enrollments')->fetchColumn(),
];
$startRow = $totalUsers > 0 ? $offset + 1 : 0;
$endRow = min($offset + $perPage, $totalUsers);

admin_header('Kelola Users');
?>
<div class="row dashboard-stat-grid">
  <div class="col-sm-6 col-xl-3">
    <div class="admin-card stat-card">
      <span><i class="uil uil-users-alt"></i> Total User</span>
      <strong><?= e((string) $stats['total']) ?></strong>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="admin-card stat-card">
      <span><i class="uil uil-user-check"></i> User Aktif</span>
      <strong><?= e((string) $stats['active']) ?></strong>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="admin-card stat-card">
      <span><i class="uil uil-user-times"></i> User Nonaktif</span>
      <strong><?= e((string) $stats['inactive']) ?></strong>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="admin-card stat-card">
      <span><i class="uil uil-book-reader"></i> Total Enrollment</span>
      <strong><?= e((string) $stats['enrollments']) ?></strong>
    </div>
  </div>
</div>

<div class="settings-accordion" id="userCreateAccordion">
  <div class="admin-card setting-card">
    <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#newUserSettings" aria-expanded="true" aria-controls="newUserSettings">
      <span><i class="uil uil-user-plus"></i> Tambah User <small>Buat akun peserta course dari dashboard admin.</small></span>
      <i class="uil uil-angle-down"></i>
    </button>
    <div id="newUserSettings" class="collapse show" data-parent="#userCreateAccordion">
      <form method="post" action="users.php" class="settings-body" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="0">
        <input type="hidden" name="q" value="<?= e($search) ?>">
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <input type="hidden" name="page" value="<?= e((string) $currentPage) ?>">
        <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
        <div class="row">
          <div class="form-group col-md-4">
            <label for="new_user_name">Nama</label>
            <input type="text" class="form-control" id="new_user_name" name="name" maxlength="120" required>
          </div>
          <div class="form-group col-md-4">
            <label for="new_user_email">Email</label>
            <input type="email" class="form-control" id="new_user_email" name="email" maxlength="190" autocomplete="new-password" required>
          </div>
          <div class="form-group col-md-2">
            <label for="new_user_password">Password</label>
            <input type="password" class="form-control" id="new_user_password" name="password" minlength="6" autocomplete="new-password" required>
          </div>
          <div class="form-group col-md-2">
            <label for="new_user_password_confirm">Konfirmasi</label>
            <input type="password" class="form-control" id="new_user_password_confirm" name="password_confirm" minlength="6" autocomplete="new-password" required>
          </div>
        </div>
        <div class="custom-control custom-checkbox mb-3">
          <input type="checkbox" class="custom-control-input" id="new_user_active" name="is_active" checked>
          <label class="custom-control-label" for="new_user_active">Aktifkan user setelah dibuat</label>
        </div>
        <button type="submit" class="btn btn-warning font-weight-bold"><i class="uil uil-plus-circle"></i> Tambah User</button>
      </form>
    </div>
  </div>
</div>

<div class="admin-card user-filter-card">
  <form method="get" action="users.php" class="user-filter-form">
    <div class="row align-items-end">
      <div class="form-group col-lg-5 col-md-6">
        <label for="q">Cari user</label>
        <input type="search" class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Nama atau email">
      </div>
      <div class="form-group col-lg-3 col-md-3">
        <label for="status">Status</label>
        <select class="form-control" id="status" name="status">
          <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Semua status</option>
          <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Aktif</option>
          <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
        </select>
      </div>
      <div class="form-group col-lg-2 col-md-3">
        <label for="per_page">Data / halaman</label>
        <select class="form-control" id="per_page" name="per_page">
          <?php foreach ($allowedPerPage as $option): ?>
            <option value="<?= e((string) $option) ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= e((string) $option) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group col-lg-2 col-md-12 user-filter-actions">
        <button type="submit" class="btn btn-warning font-weight-bold"><i class="uil uil-search"></i> Filter</button>
        <a class="btn btn-outline-secondary" href="users.php"><i class="uil uil-redo"></i> Reset</a>
      </div>
    </div>
  </form>
</div>

<div class="admin-card">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
      <h2 class="mb-1"><i class="uil uil-users-alt"></i> Daftar User</h2>
      <p class="text-muted mb-0">Menampilkan <?= e((string) $startRow) ?>-<?= e((string) $endRow) ?> dari <?= e((string) $totalUsers) ?> user.</p>
    </div>
  </div>

  <?php if (!$users): ?>
    <div class="alert alert-info mb-0">Belum ada user yang cocok dengan filter saat ini.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover user-management-table">
        <thead>
          <tr>
            <th>User</th>
            <th>Status</th>
            <th>Enrollment</th>
            <th>Progress</th>
            <th>Terakhir Akses</th>
            <th>Diperbarui</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $user): ?>
            <?php
              $userId = (int) $user['id'];
              $isActive = (int) $user['is_active'] === 1;
              $enrollmentCount = (int) ($user['enrollment_count'] ?? 0);
              $activeEnrollmentCount = (int) ($user['active_enrollment_count'] ?? 0);
              $progressCount = (int) ($user['progress_count'] ?? 0);
            ?>
            <tr>
              <td>
                <div class="user-identity">
                  <strong><?= e((string) $user['name']) ?></strong>
                  <a href="mailto:<?= e((string) $user['email']) ?>"><?= e((string) $user['email']) ?></a>
                  <small>Bergabung <?= !empty($user['created_at']) ? e(format_course_datetime((string) $user['created_at'])) : '-' ?></small>
                </div>
              </td>
              <td><span class="badge badge-<?= $isActive ? 'success' : 'danger' ?>"><?= $isActive ? 'Aktif' : 'Nonaktif' ?></span></td>
              <td><?= e((string) $activeEnrollmentCount) ?> aktif / <?= e((string) $enrollmentCount) ?> total</td>
              <td><?= e((string) $progressCount) ?> selesai</td>
              <td><?= !empty($user['last_accessed_at']) ? e(format_course_datetime((string) $user['last_accessed_at'])) : '<span class="text-muted">Belum akses</span>' ?></td>
              <td><?= !empty($user['updated_at']) ? e(format_course_datetime((string) $user['updated_at'])) : '-' ?></td>
              <td>
                <div class="user-action-group">
                  <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#editUserModal_<?= e((string) $userId) ?>">
                    <i class="uil uil-edit"></i> Edit
                  </button>
                  <form method="post" action="users.php" onsubmit="return confirm('Ubah status user ini?')">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="id" value="<?= e((string) $userId) ?>">
                    <input type="hidden" name="new_status" value="<?= $isActive ? '0' : '1' ?>">
                    <input type="hidden" name="q" value="<?= e($search) ?>">
                    <input type="hidden" name="status" value="<?= e($status) ?>">
                    <input type="hidden" name="page" value="<?= e((string) $currentPage) ?>">
                    <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
                    <button type="submit" class="btn btn-sm btn-<?= $isActive ? 'outline-warning' : 'outline-success' ?>">
                      <i class="uil <?= $isActive ? 'uil-lock' : 'uil-unlock' ?>"></i> <?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?>
                    </button>
                  </form>
                  <form method="post" action="users.php" onsubmit="return confirm('Hapus user ini? Enrollment, progress, riwayat akses, dan percobaan quiz user juga ikut terhapus.')">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= e((string) $userId) ?>">
                    <input type="hidden" name="q" value="<?= e($search) ?>">
                    <input type="hidden" name="status" value="<?= e($status) ?>">
                    <input type="hidden" name="page" value="<?= e((string) $currentPage) ?>">
                    <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="uil uil-trash-alt"></i> Hapus</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mt-3">
      <p class="text-muted mb-2 mb-md-0">Halaman <?= e((string) $currentPage) ?> dari <?= e((string) $totalPages) ?>.</p>
      <nav aria-label="Pagination user">
        <ul class="pagination pagination-sm mb-0">
          <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e(admin_users_url(max(1, $currentPage - 1), $search, $status, $perPage)) ?>">Sebelumnya</a>
          </li>
          <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
            <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
              <a class="page-link" href="<?= e(admin_users_url($i, $search, $status, $perPage)) ?>"><?= e((string) $i) ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e(admin_users_url(min($totalPages, $currentPage + 1), $search, $status, $perPage)) ?>">Berikutnya</a>
          </li>
        </ul>
      </nav>
    </div>
  <?php endif; ?>
</div>

<?php foreach ($users as $user): ?>
  <?php
    $userId = (int) $user['id'];
    $isActive = (int) $user['is_active'] === 1;
  ?>
  <div class="modal fade user-edit-modal" id="editUserModal_<?= e((string) $userId) ?>" tabindex="-1" role="dialog" aria-labelledby="editUserTitle_<?= e((string) $userId) ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <form method="post" action="users.php" autocomplete="off">
          <div class="modal-header">
            <h5 class="modal-title" id="editUserTitle_<?= e((string) $userId) ?>"><i class="uil uil-edit"></i> Edit User</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= e((string) $userId) ?>">
            <input type="hidden" name="q" value="<?= e($search) ?>">
            <input type="hidden" name="status" value="<?= e($status) ?>">
            <input type="hidden" name="page" value="<?= e((string) $currentPage) ?>">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="row">
              <div class="form-group col-md-6">
                <label for="user_name_<?= e((string) $userId) ?>">Nama</label>
                <input type="text" class="form-control" id="user_name_<?= e((string) $userId) ?>" name="name" value="<?= e((string) $user['name']) ?>" maxlength="120" required>
              </div>
              <div class="form-group col-md-6">
                <label for="user_email_<?= e((string) $userId) ?>">Email</label>
                <input type="email" class="form-control" id="user_email_<?= e((string) $userId) ?>" name="email" value="<?= e((string) $user['email']) ?>" maxlength="190" autocomplete="new-password" required>
              </div>
            </div>
            <div class="row">
              <div class="form-group col-md-6">
                <label for="user_password_<?= e((string) $userId) ?>">Password baru</label>
                <input type="password" class="form-control" id="user_password_<?= e((string) $userId) ?>" name="password" minlength="6" autocomplete="new-password" placeholder="Kosongkan jika tidak diganti">
              </div>
              <div class="form-group col-md-6">
                <label for="user_password_confirm_<?= e((string) $userId) ?>">Konfirmasi password baru</label>
                <input type="password" class="form-control" id="user_password_confirm_<?= e((string) $userId) ?>" name="password_confirm" minlength="6" autocomplete="new-password" placeholder="Kosongkan jika tidak diganti">
              </div>
            </div>
            <div class="custom-control custom-checkbox">
              <input type="checkbox" class="custom-control-input" id="user_active_<?= e((string) $userId) ?>" name="is_active" <?= $isActive ? 'checked' : '' ?>>
              <label class="custom-control-label" for="user_active_<?= e((string) $userId) ?>">User aktif dan bisa login course</label>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-warning font-weight-bold"><i class="uil uil-check-circle"></i> Simpan User</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endforeach; ?>
<?php admin_footer(); ?>

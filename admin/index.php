<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

require_admin();

$pdo = pdo();
$settings = get_settings($pdo);

$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 10);
$allowedPerPage = [10, 25, 50];
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 10;
}
$offset = ($currentPage - 1) * $perPage;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $action = $_POST['action'] ?? '';
    $returnPage = max(1, (int) ($_POST['page'] ?? $currentPage));
    $returnPerPage = (int) ($_POST['per_page'] ?? $perPage);
    if (!in_array($returnPerPage, $allowedPerPage, true)) {
        $returnPerPage = 10;
    }
    $returnUrl = 'index.php?page=' . $returnPage . '&per_page=' . $returnPerPage . '#monitoring';

    if ($action === 'toggle_user_status') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $newStatus = (int) ($_POST['new_status'] ?? 0) === 1;

        if ($userId <= 0) {
            set_admin_flash('danger', 'User tidak valid.');
        } elseif (set_user_active_status($userId, $newStatus, $pdo)) {
            set_admin_flash('success', $newStatus ? 'User berhasil diaktifkan.' : 'User berhasil dinonaktifkan.');
        } else {
            set_admin_flash('danger', 'Status user gagal diubah. Pastikan database sudah dimigrasi.');
        }

        redirect($returnUrl);
    }

    if ($action === 'toggle_enrollment_status') {
        $enrollmentId = (int) ($_POST['enrollment_id'] ?? 0);
        $newStatus = (int) ($_POST['new_status'] ?? 0) === 1;

        if ($enrollmentId <= 0) {
            set_admin_flash('danger', 'Enrollment kelas tidak valid.');
        } elseif (set_enrollment_active_status($enrollmentId, $newStatus, $pdo)) {
            set_admin_flash('success', $newStatus ? 'Akses kelas user berhasil diaktifkan.' : 'Akses kelas user berhasil dinonaktifkan.');
        } else {
            set_admin_flash('danger', 'Status kelas user gagal diubah. Jalankan SQL upgrade terlebih dahulu.');
        }

        redirect($returnUrl);
    }
}

$stats = [
    'projects' => (int) $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn(),
    'news' => (int) $pdo->query('SELECT COUNT(*) FROM news_posts')->fetchColumn(),
    'courses' => (int) $pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn(),
    'resume' => (int) $pdo->query('SELECT COUNT(*) FROM resume_items')->fetchColumn(),
    'messages' => (int) $pdo->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn(),
    'users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'active_users' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE COALESCE(is_active, 1) = 1')->fetchColumn(),
    'inactive_users' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE COALESCE(is_active, 1) = 0')->fetchColumn(),
    'tech_stacks' => count(get_tech_stacks($pdo, false)),
];

$monitoringTotal = count_admin_user_course_monitoring($pdo);
$totalPages = max(1, (int) ceil($monitoringTotal / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $perPage;
}
$monitoringRows = get_admin_user_course_monitoring($pdo, $perPage, $offset);
$userActivePercent = $stats['users'] > 0 ? (int) round(($stats['active_users'] / $stats['users']) * 100) : 0;
$userInactivePercent = max(0, 100 - $userActivePercent);
$startRow = $monitoringTotal > 0 ? $offset + 1 : 0;
$endRow = min($offset + $perPage, $monitoringTotal);

admin_header('Dashboard');
?>
<div class="dashboard-grid row">
  <div class="col-lg-8">
    <div class="row dashboard-stat-grid">
      <div class="col-sm-6 col-xl-4">
        <div class="admin-card stat-card stat-card-modern">
          <span><i class="uil uil-users-alt"></i> Total User</span>
          <strong><?= e((string) $stats['users']) ?></strong>
          <small><?= e((string) $stats['active_users']) ?> aktif / <?= e((string) $stats['inactive_users']) ?> nonaktif</small>
        </div>
      </div>
      <div class="col-sm-6 col-xl-4">
        <div class="admin-card stat-card stat-card-modern">
          <span><i class="uil uil-book-open"></i> Total Course</span>
          <strong><?= e((string) $stats['courses']) ?></strong>
          <small>kelas tersedia</small>
        </div>
      </div>
      <div class="col-sm-6 col-xl-4">
        <div class="admin-card stat-card stat-card-modern">
          <span><i class="uil uil-monitor-heart-rate"></i> Enrollment</span>
          <strong><?= e((string) $monitoringTotal) ?></strong>
          <small>user terdaftar kelas</small>
        </div>
      </div>
      <div class="col-sm-6 col-xl-4">
        <div class="admin-card stat-card stat-card-modern">
          <span><i class="uil uil-images"></i> Total Project</span>
          <strong><?= e((string) $stats['projects']) ?></strong>
          <small>portfolio item</small>
        </div>
      </div>
      <div class="col-sm-6 col-xl-4">
        <div class="admin-card stat-card stat-card-modern">
          <span><i class="uil uil-newspaper"></i> Total Berita</span>
          <strong><?= e((string) $stats['news']) ?></strong>
          <small>post aktif</small>
        </div>
      </div>
      <div class="col-sm-6 col-xl-4">
        <div class="admin-card stat-card stat-card-modern">
          <span><i class="uil uil-envelope"></i> Pesan Contact</span>
          <strong><?= e((string) $stats['messages']) ?></strong>
          <small>pesan masuk</small>
        </div>
      </div>
      <div class="col-sm-6 col-xl-4">
        <div class="admin-card stat-card stat-card-modern">
          <span><i class="uil uil-layer-group"></i> Tech Stack</span>
          <strong><?= e((string) $stats['tech_stacks']) ?></strong>
          <small>teknologi di slider</small>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="admin-card user-chart-card">
      <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
          <h2 class="mb-1"><i class="uil uil-chart-pie"></i> Grafik Total User</h2>
          <p class="text-muted mb-0">Komposisi user aktif dan nonaktif.</p>
        </div>
        <span class="badge badge-primary"><?= e((string) $stats['users']) ?> user</span>
      </div>
      <div class="user-donut" style="--active: <?= e((string) $userActivePercent) ?>%;">
        <div>
          <strong><?= e((string) $userActivePercent) ?>%</strong>
          <small>aktif</small>
        </div>
      </div>
      <div class="chart-legend mt-3">
        <div><span class="legend-dot active"></span> Aktif <strong><?= e((string) $stats['active_users']) ?></strong></div>
        <div><span class="legend-dot inactive"></span> Nonaktif <strong><?= e((string) $stats['inactive_users']) ?></strong></div>
      </div>
      <div class="chart-bar mt-3">
        <span style="width: <?= e((string) $userActivePercent) ?>%;"></span>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-lg-12">
    <div class="admin-card website-card">
      <h2><i class="uil uil-globe"></i> Website Aktif</h2>
      <div class="row">
        <div class="col-md-4"><p class="mb-2"><strong>Brand:</strong><br><?= e($settings['site_brand'] ?? '') ?></p></div>
        <div class="col-md-4"><p class="mb-2"><strong>Judul halaman:</strong><br><?= e($settings['page_title'] ?? '') ?></p></div>
        <div class="col-md-4"><p class="mb-0"><strong>Email contact:</strong><br><?= e($settings['contact_email'] ?? '') ?></p></div>
      </div>
    </div>
  </div>
</div>

<div class="row" id="monitoring">
  <div class="col-lg-12">
    <div class="admin-card">
      <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
          <h2 class="mb-1"><i class="uil uil-monitor-heart-rate"></i> Monitoring Akses User & Course</h2>
          <p class="text-muted mb-0">Pantau enroll, akses, progress, status user, dan status akses kelas per user.</p>
        </div>
        <form method="get" class="form-inline mt-3 mt-md-0">
          <label class="mr-2 small text-muted" for="per_page">Data per halaman</label>
          <select class="form-control form-control-sm" id="per_page" name="per_page" onchange="this.form.submit()">
            <?php foreach ($allowedPerPage as $option): ?>
              <option value="<?= e((string) $option) ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= e((string) $option) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>

      <?php if (!$monitoringRows): ?>
        <div class="alert alert-info mb-0">Belum ada data monitoring. Pastikan user sudah enroll course dan jalankan SQL upgrade monitoring.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle monitoring-table">
            <thead>
              <tr>
                <th>User</th>
                <th>Email</th>
                <th>Course</th>
                <th>Status Course</th>
                <th>Status Akses</th>
                <th>Progress</th>
                <th>Enroll</th>
                <th>Terakhir Akses</th>
                <th>Total Akses</th>
                <th>Status User</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($monitoringRows as $row): ?>
                <?php
                  $totalMeetings = (int) ($row['total_meetings'] ?? 0);
                  $completedMeetings = (int) ($row['completed_meetings'] ?? 0);
                  $percent = $totalMeetings > 0 ? (int) round(($completedMeetings / $totalMeetings) * 100) : 0;
                  $courseActive = (int) ($row['course_is_active'] ?? 0) === 1;
                  $userActive = (int) ($row['user_is_active'] ?? 0) === 1;
                  $enrollmentActive = (int) ($row['enrollment_is_active'] ?? 1) === 1;
                ?>
                <tr class="<?= (!$userActive || !$enrollmentActive) ? 'table-muted-row' : '' ?>">
                  <td><strong><?= e($row['user_name'] ?? '-') ?></strong></td>
                  <td><?= e($row['user_email'] ?? '-') ?></td>
                  <td><?= e($row['course_title'] ?? '-') ?></td>
                  <td><span class="badge badge-<?= $courseActive ? 'success' : 'secondary' ?>"><?= $courseActive ? 'Aktif' : 'Inaktif' ?></span></td>
                  <td><span class="badge badge-<?= $enrollmentActive ? 'info' : 'warning' ?>"><?= $enrollmentActive ? 'Bisa Akses' : 'Akses Ditutup' ?></span></td>
                  <td style="min-width: 150px;">
                    <div class="d-flex justify-content-between small mb-1">
                      <span><?= e((string) $completedMeetings) ?>/<?= e((string) $totalMeetings) ?></span>
                      <span><?= e((string) $percent) ?>%</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                      <div class="progress-bar" role="progressbar" style="width: <?= e((string) $percent) ?>%;" aria-valuenow="<?= e((string) $percent) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                  </td>
                  <td><?= !empty($row['enrolled_at']) ? e(format_course_datetime((string) $row['enrolled_at'])) : '-' ?></td>
                  <td><?= !empty($row['last_accessed_at']) ? e(format_course_datetime((string) $row['last_accessed_at'])) : '<span class="text-muted">Belum akses</span>' ?></td>
                  <td><?= e((string) ((int) ($row['access_count'] ?? 0))) ?></td>
                  <td><span class="badge badge-<?= $userActive ? 'success' : 'danger' ?>"><?= $userActive ? 'Aktif' : 'Nonaktif' ?></span></td>
                  <td>
                    <div class="btn-group-vertical btn-group-sm action-stack" role="group">
                      <form method="post" class="mb-1" onsubmit="return confirm('Ubah status akses kelas user ini?')">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="toggle_enrollment_status">
                        <input type="hidden" name="enrollment_id" value="<?= e((string) $row['enrollment_id']) ?>">
                        <input type="hidden" name="new_status" value="<?= $enrollmentActive ? '0' : '1' ?>">
                        <input type="hidden" name="page" value="<?= e((string) $currentPage) ?>">
                        <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
                        <button type="submit" class="btn btn-sm btn-<?= $enrollmentActive ? 'outline-warning' : 'outline-info' ?>">
                          <?= $enrollmentActive ? 'Nonaktifkan Kelas' : 'Aktifkan Kelas' ?>
                        </button>
                      </form>
                      <form method="post" class="mb-0" onsubmit="return confirm('Ubah status user ini?')">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="toggle_user_status">
                        <input type="hidden" name="user_id" value="<?= e((string) $row['user_id']) ?>">
                        <input type="hidden" name="new_status" value="<?= $userActive ? '0' : '1' ?>">
                        <input type="hidden" name="page" value="<?= e((string) $currentPage) ?>">
                        <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
                        <button type="submit" class="btn btn-sm btn-<?= $userActive ? 'outline-danger' : 'outline-success' ?>">
                          <?= $userActive ? 'Nonaktifkan User' : 'Aktifkan User' ?>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center mt-3">
          <p class="text-muted mb-2 mb-md-0">Menampilkan <?= e((string) $startRow) ?>-<?= e((string) $endRow) ?> dari <?= e((string) $monitoringTotal) ?> data.</p>
          <nav aria-label="Pagination monitoring">
            <ul class="pagination pagination-sm mb-0">
              <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= e((string) max(1, $currentPage - 1)) ?>&per_page=<?= e((string) $perPage) ?>#monitoring">Sebelumnya</a>
              </li>
              <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                  <a class="page-link" href="?page=<?= e((string) $i) ?>&per_page=<?= e((string) $perPage) ?>#monitoring"><?= e((string) $i) ?></a>
                </li>
              <?php endfor; ?>
              <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= e((string) min($totalPages, $currentPage + 1)) ?>&per_page=<?= e((string) $perPage) ?>#monitoring">Berikutnya</a>
              </li>
            </ul>
          </nav>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php admin_footer(); ?>

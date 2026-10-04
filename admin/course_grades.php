<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

require_admin();

$pdo = pdo();
$adminId = current_admin_id();

// Download File Submission Handler
if (isset($_GET['download_submission'])) {
    $subId = (int) $_GET['download_submission'];
    $st = $pdo->prepare('SELECT * FROM course_assignment_submissions WHERE id = ?');
    $st->execute([$subId]);
    $sub = $st->fetch();

    if ($sub && !empty($sub['file_path'])) {
        $storagePath = assignment_submission_storage_path($sub['file_path']);
        if ($storagePath && is_file($storagePath)) {
            $dlName = !empty($sub['file_name']) ? $sub['file_name'] : basename($storagePath);
            $fileSize = filesize($storagePath);
            session_write_close();
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $ext = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION));
            $mimeTypes = [
                'pdf' => 'application/pdf',
                'zip' => 'application/zip',
                'rar' => 'application/x-rar-compressed',
                '7z' => 'application/x-rar-compressed',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'ppt' => 'application/vnd.ms-powerpoint',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
            ];
            $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename="' . addcslashes($dlName, '"\\') . '"');
            header('Content-Transfer-Encoding: binary');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            if ($fileSize !== false) {
                header('Content-Length: ' . (string) $fileSize);
            }
            readfile($storagePath);
            exit;
        }
    }
    set_admin_flash('danger', 'File tugas tidak ditemukan di server.');
    redirect('course_grades.php');
}

// POST Handler: Penilaian Tugas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'grade_submission') {
        $subId = (int) ($_POST['submission_id'] ?? 0);
        $scoreRaw = trim((string) ($_POST['score'] ?? ''));
        $feedback = trim((string) ($_POST['feedback'] ?? ''));
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $status = trim((string) ($_POST['submission_status'] ?? 'graded'));
        if (!in_array($status, ['graded', 'revision'], true)) {
            $status = 'graded';
        }

        $score = $scoreRaw !== '' ? (float) $scoreRaw : null;

        if ($status === 'graded') {
            if ($score === null) {
                set_admin_flash('danger', 'Nilai tugas (0 - 100) wajib diisi untuk status "Selesai Dinilai".');
                redirect('course_grades.php?course_id=' . $courseId . '#sub_' . $subId);
            }
            if ($score < 0 || $score > 100) {
                set_admin_flash('danger', 'Nilai harus berada di antara 0 sampai 100.');
                redirect('course_grades.php?course_id=' . $courseId . '#sub_' . $subId);
            }
        } elseif ($status === 'revision') {
            if ($feedback === '') {
                set_admin_flash('danger', 'Catatan revisi wajib diisi agar mahasiswa mengetahui bagian apa yang harus diperbaiki.');
                redirect('course_grades.php?course_id=' . $courseId . '#sub_' . $subId);
            }
        }

        if (grade_assignment_submission($subId, $score, $feedback, $status, $adminId, $pdo)) {
            if ($status === 'revision') {
                set_admin_flash('warning', 'Status tugas berhasil diubah menjadi "Perlu Revisi". Mahasiswa sekarang dapat mengedit dan mengirim ulang perbaikan tugas.');
            } else {
                set_admin_flash('success', 'Nilai tugas berhasil disimpan. Tugas telah selesai dinilai dan dikunci.');
            }
        } else {
            set_admin_flash('danger', 'Gagal menyimpan penilaian tugas.');
        }
        redirect('course_grades.php?course_id=' . $courseId . '#sub_' . $subId);
    }
}

// Ambil list semua courses
$allCourses = get_courses($pdo, false);
$selectedCourseId = (int) ($_GET['course_id'] ?? ($allCourses[0]['id'] ?? 0));

// Jika ada param assignment_id tapi course_id tidak diset eksplisit
if ($selectedCourseId <= 0 && !empty($allCourses)) {
    $selectedCourseId = (int) $allCourses[0]['id'];
}

// Ambil data laporan nilai untuk course yang dipilih
$report = get_course_all_students_grade_report($selectedCourseId, $pdo);
$currentCourse = $report['course'];
$assignments = $report['assignments'];
$totalAssignments = $report['total_assignments'];
$weightPerAssignment = $report['weight_per_assignment'];
$students = $report['students'];

// EXPORT KE CSV / EXCEL HANDLER
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (!$currentCourse) {
        exit('Course tidak ditemukan.');
    }

    $safeCourseTitle = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string) $currentCourse['title']);
    $filename = 'Rekap_Nilai_' . $safeCourseTitle . '_' . date('Ymd_His') . '.csv';

    session_write_close();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // Tulis UTF-8 BOM agar Excel membaca format teks dengan benar
    fwrite($output, "\xEF\xBB\xBF");

    // Header baris informasi
    fputcsv($output, ['REKAPITULASI NILAI MAHASISWA / SISWA']);
    fputcsv($output, ['Course', $currentCourse['title']]);
    fputcsv($output, ['Tanggal Export', date('d-m-Y H:i:s')]);
    fputcsv($output, ['Total Tugas', $totalAssignments . ' tugas']);
    fputcsv($output, ['Bobot Tiap Tugas', $weightPerAssignment . '% (Rumus: 100% / ' . ($totalAssignments > 0 ? $totalAssignments : 1) . ')']);
    fputcsv($output, []); // Baris kosong

    // Baris Header Kolom Tabel
    $headerRow = [
        'No',
        'Nama Siswa',
        'Email',
        'Status Akses',
    ];

    foreach ($assignments as $idx => $a) {
        $headerRow[] = 'Tugas ' . ($idx + 1) . ': ' . $a['title'] . ' (Nilai)';
        $headerRow[] = 'Kontribusi ' . ($idx + 1) . ' (' . $weightPerAssignment . '%)';
    }

    $headerRow[] = 'Tugas Dikumpulkan';
    $headerRow[] = 'Tugas Dinilai';
    $headerRow[] = 'Total Nilai Akhir (100%)';
    $headerRow[] = 'Status Kelulusan';

    fputcsv($output, $headerRow);

    // Baris Data Siswa
    $no = 1;
    foreach ($students as $stu) {
        $row = [
            $no++,
            $stu['user_name'],
            $stu['user_email'],
            $stu['is_active'] ? 'Aktif' : 'Nonaktif',
        ];

        foreach ($assignments as $a) {
            $aId = (int) $a['id'];
            $t = $stu['tasks'][$aId] ?? null;
            if ($t && ($t['is_graded'] ?? false) && $t['raw_score'] !== null) {
                $row[] = number_format((float) $t['raw_score'], 2);
                $row[] = number_format((float) $t['contribution'], 2) . '%';
            } elseif ($t && ($t['is_revision'] ?? false)) {
                $row[] = 'Perlu Revisi';
                $row[] = '0.00%';
            } elseif ($t && ($t['has_submission'] ?? false)) {
                $row[] = 'Belum Dinilai';
                $row[] = '0.00%';
            } else {
                $row[] = 'Belum Mengumpulkan';
                $row[] = '0.00%';
            }
        }

        $row[] = $stu['submitted_count'] . ' / ' . $totalAssignments;
        $row[] = $stu['graded_count'] . ' / ' . $totalAssignments;
        $row[] = number_format((float) $stu['final_score'], 2) . '%';
        $row[] = $stu['final_score'] >= 60 ? 'LULUS' : 'BELUM LULUS';

        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

// Ambil semua submissions untuk tab Penilaian Tugas
$filterAssignmentId = (int) ($_GET['assignment_id'] ?? 0);
$filterStatus = trim((string) ($_GET['status'] ?? 'all')); // 'all', 'ungraded', 'revision', 'graded'

$allSubmissions = [];
$totalSubmissionsCount = 0;
$ungradedSubmissionsCount = 0;
$revisionSubmissionsCount = 0;
$dbTableError = false;

try {
    $subQuery = 'SELECT cas.*, ca.title AS assignment_title, ca.meeting_id, cm.title AS meeting_title, cm.sort_order AS meeting_order, u.name AS user_name, u.email AS user_email 
                 FROM course_assignment_submissions cas 
                 INNER JOIN course_assignments ca ON ca.id = cas.assignment_id 
                 INNER JOIN course_meetings cm ON cm.id = ca.meeting_id 
                 INNER JOIN users u ON u.id = cas.user_id 
                 WHERE cm.course_id = ?';
    $params = [$selectedCourseId];

    if ($filterAssignmentId > 0) {
        $subQuery .= ' AND cas.assignment_id = ?';
        $params[] = $filterAssignmentId;
    }

    if ($filterStatus === 'ungraded') {
        $subQuery .= " AND cas.status = 'submitted'";
    } elseif ($filterStatus === 'revision') {
        $subQuery .= " AND cas.status = 'revision'";
    } elseif ($filterStatus === 'graded') {
        $subQuery .= " AND cas.status = 'graded'";
    }

    $subQuery .= ' ORDER BY cas.submitted_at DESC, cas.id DESC';
    $st = $pdo->prepare($subQuery);
    $st->execute($params);
    $allSubmissions = $st->fetchAll();

    // Hitung metrik ringkasan
    $totalSubmissionsCount = (int) $pdo->query('SELECT COUNT(*) FROM course_assignment_submissions cas INNER JOIN course_assignments ca ON ca.id = cas.assignment_id INNER JOIN course_meetings cm ON cm.id = ca.meeting_id WHERE cm.course_id = ' . $selectedCourseId)->fetchColumn();
    $ungradedSubmissionsCount = (int) $pdo->query("SELECT COUNT(*) FROM course_assignment_submissions cas INNER JOIN course_assignments ca ON ca.id = cas.assignment_id INNER JOIN course_meetings cm ON cm.id = ca.meeting_id WHERE cm.course_id = $selectedCourseId AND cas.status = 'submitted'")->fetchColumn();
    $revisionSubmissionsCount = (int) $pdo->query("SELECT COUNT(*) FROM course_assignment_submissions cas INNER JOIN course_assignments ca ON ca.id = cas.assignment_id INNER JOIN course_meetings cm ON cm.id = ca.meeting_id WHERE cm.course_id = $selectedCourseId AND cas.status = 'revision'")->fetchColumn();
} catch (Throwable $e) {
    $dbTableError = true;
}

admin_header('Penilaian & Export Nilai');
?>

<?php if ($dbTableError): ?>
  <div class="alert alert-danger shadow-sm mb-4">
    <h5 class="font-weight-bold mb-1"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Tabel Database Tugas Belum Dibuat</h5>
    <p class="mb-0 small">Tabel <code>course_assignments</code> atau <code>course_assignment_submissions</code> belum ditemukan di database Anda. Silakan impor file <code>database_assignments.sql</code> melalui menu <strong>phpMyAdmin &gt; SQL</strong> di cPanel hosting Anda.</p>
  </div>
<?php endif; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
  <div>
    <h1 class="h3 font-weight-bold mb-1"><i class="fa-solid fa-graduation-cap text-warning mr-2"></i> Penilaian & Rekap Nilai Tugas</h1>
    <p class="text-muted mb-0">Kelola penilaian tugas mahasiswa, hitung nilai akhir otomatis (100%), dan export ke format CSV Excel.</p>
  </div>
  <div class="mt-3 mt-md-0 d-flex flex-wrap gap-2">
    <?php if ($currentCourse && $totalAssignments > 0): ?>
      <a href="course_grades.php?course_id=<?= e((string) $selectedCourseId) ?>&export=csv" class="btn btn-success font-weight-bold mr-2 mb-2 shadow-sm">
        <i class="fa-solid fa-file-excel mr-1"></i> Export Nilai (CSV Excel)
      </a>
    <?php endif; ?>
    <a href="courses.php" class="btn btn-outline-secondary font-weight-bold mb-2">
      <i class="uil uil-arrow-left"></i> Kembali ke Courses
    </a>
  </div>
</div>

<!-- Course Selector Bar -->
<div class="admin-card mb-4">
  <form method="get" action="course_grades.php" class="form-inline d-flex flex-wrap align-items-center">
    <label class="mr-3 font-weight-bold" for="course_select"><i class="uil uil-book-reader mr-1"></i> Pilih Course:</label>
    <select class="form-control mr-3 mb-2 mb-md-0 flex-grow-1" id="course_select" name="course_id" onchange="this.form.submit()" style="max-width: 450px;">
      <?php foreach ($allCourses as $c): ?>
        <option value="<?= e((string) $c['id']) ?>" <?= (int) $c['id'] === $selectedCourseId ? 'selected' : '' ?>>
          <?= e($c['title']) ?> (<?= (int) $c['is_active'] === 1 ? 'Aktif' : 'Inaktif' ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary font-weight-bold"><i class="uil uil-filter"></i> Tampilkan</button>
  </form>
</div>

<?php if (!$currentCourse): ?>
  <div class="alert alert-info">Belum ada course yang dipilih atau tersedia.</div>
<?php else: ?>

  <!-- Summary Metric Cards -->
  <div class="row dashboard-stat-grid mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="admin-card stat-card stat-card-modern">
        <span><i class="uil uil-users-alt"></i> Siswa Terdaftar</span>
        <strong><?= count($students) ?></strong>
        <small>mahasiswa dalam kelas</small>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="admin-card stat-card stat-card-modern">
        <span><i class="uil uil-clipboard-notes"></i> Jumlah Tugas (N)</span>
        <strong><?= $totalAssignments ?></strong>
        <small><?= $totalAssignments > 0 ? 'Bobot: ' . $weightPerAssignment . '% / tugas' : 'Belum ada tugas' ?></small>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="admin-card stat-card stat-card-modern">
        <span><i class="uil uil-percentage"></i> Logika Bobot Nilai</span>
        <strong><?= $totalAssignments > 0 ? (100 / $totalAssignments == (int)(100 / $totalAssignments) ? (int)(100 / $totalAssignments) : number_format(100 / $totalAssignments, 1)) . '%' : '0%' ?></strong>
        <small>Rumus: 100% / <?= $totalAssignments > 0 ? $totalAssignments : 1 ?> tugas</small>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="admin-card stat-card stat-card-modern">
        <span><i class="uil uil-clock"></i> Menunggu Penilaian</span>
        <strong class="<?= $ungradedSubmissionsCount > 0 ? 'text-warning' : 'text-success' ?>"><?= $ungradedSubmissionsCount ?></strong>
        <small>dari <?= $totalSubmissionsCount ?> pengumpulan tugas</small>
      </div>
    </div>
  </div>

  <!-- Tab Navigation -->
  <ul class="nav nav-tabs nav-fill mb-4 font-weight-bold" id="gradeTabs" role="tablist">
    <li class="nav-item">
      <a class="nav-link <?= empty($_GET['assignment_id']) && empty($_GET['status']) ? 'active' : '' ?>" id="rekap-tab" data-toggle="tab" href="#rekapPane" role="tab" aria-controls="rekapPane" aria-selected="true">
        <i class="fa-solid fa-table-list mr-2"></i> Rekapitulasi & Export Nilai (<?= count($students) ?> Siswa)
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= !empty($_GET['assignment_id']) || !empty($_GET['status']) ? 'active' : '' ?>" id="submissions-tab" data-toggle="tab" href="#submissionsPane" role="tab" aria-controls="submissionsPane" aria-selected="false">
        <i class="fa-solid fa-list-check mr-2"></i> Penilaian Pengumpulan Tugas 
        <?php if ($ungradedSubmissionsCount > 0): ?>
          <span class="badge badge-warning ml-1"><?= $ungradedSubmissionsCount ?> menunggu</span>
        <?php endif; ?>
        <?php if ($revisionSubmissionsCount > 0): ?>
          <span class="badge badge-danger ml-1"><?= $revisionSubmissionsCount ?> revisi</span>
        <?php endif; ?>
      </a>
    </li>
  </ul>

  <div class="tab-content" id="gradeTabsContent">
    
    <!-- TAB 1: REKAPITULASI & EXPORT NILAI -->
    <div class="tab-pane fade <?= empty($_GET['assignment_id']) && empty($_GET['status']) ? 'show active' : '' ?>" id="rekapPane" role="tabpanel" aria-labelledby="rekap-tab">
      <div class="admin-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
          <div>
            <h2 class="h5 mb-1 font-weight-bold"><i class="fa-solid fa-file-invoice mr-2 text-primary"></i> Tabel Rekap Nilai Mahasiswa</h2>
            <p class="text-muted small mb-0">
              Total nilai dihitung proporsional dari 100% (<?= $totalAssignments ?> tugas &rarr; masing-masing tugas berbobot <?= $weightPerAssignment ?>%).
            </p>
          </div>
          <?php if ($totalAssignments > 0): ?>
            <a href="course_grades.php?course_id=<?= e((string) $selectedCourseId) ?>&export=csv" class="btn btn-sm btn-success font-weight-bold shadow-sm">
              <i class="fa-solid fa-file-excel mr-1"></i> Unduh File CSV (Excel)
            </a>
          <?php endif; ?>
        </div>

        <?php if ($totalAssignments === 0): ?>
          <div class="alert alert-warning">
            <i class="fa-solid fa-circle-exclamation mr-1"></i> Course ini belum memiliki tugas pertemuan yang aktif. Silakan tambahkan tugas melalui menu <strong><a href="courses.php">Daftar Course</a></strong> terlebih dahulu.
          </div>
        <?php elseif (empty($students)): ?>
          <div class="alert alert-info">
            Belum ada mahasiswa yang terdaftar (enrolled) di course ini.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
              <thead class="thead-dark text-center" style="font-size: 13px;">
                <tr>
                  <th style="width: 40px;">No</th>
                  <th>Nama Mahasiswa</th>
                  <th>Email</th>
                  <?php foreach ($assignments as $idx => $a): ?>
                    <th>
                      Tugas <?= $idx + 1 ?><br>
                      <small class="badge badge-warning text-dark font-weight-bold">Bobot <?= $weightPerAssignment ?>%</small>
                    </th>
                  <?php endforeach; ?>
                  <th>Tugas Dikumpulkan</th>
                  <th style="min-width: 140px; background-color: #1e293b; color: #ffc200;">Total Nilai Akhir (100%)</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody style="font-size: 14px;">
                <?php $no = 1; foreach ($students as $stu): ?>
                  <tr>
                    <td class="text-center font-weight-bold"><?= $no++ ?></td>
                    <td>
                      <strong><?= e($stu['user_name']) ?></strong>
                      <?php if (!$stu['is_active']): ?>
                        <span class="badge badge-secondary ml-1">Nonaktif</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-muted small"><?= e($stu['user_email']) ?></td>
                    <?php foreach ($assignments as $a): ?>
                      <?php
                        $aId = (int) $a['id'];
                        $t = $stu['tasks'][$aId] ?? null;
                      ?>
                      <td class="text-center">
                        <?php if ($t && ($t['is_graded'] ?? false) && $t['raw_score'] !== null): ?>
                          <div class="font-weight-bold text-success" style="font-size: 15px;"><?= number_format((float) $t['raw_score'], 1) ?></div>
                          <small class="text-muted font-italic">+<?= number_format((float) $t['contribution'], 1) ?>%</small>
                        <?php elseif ($t && ($t['is_revision'] ?? false)): ?>
                          <span class="badge badge-danger" title="Tugas perlu revisi oleh mahasiswa"><i class="fa-solid fa-rotate-right mr-1"></i> Perlu Revisi</span>
                        <?php elseif ($t && ($t['has_submission'] ?? false)): ?>
                          <span class="badge badge-warning" title="Tugas dikumpulkan, menunggu nilai admin">Menunggu Nilai</span>
                        <?php else: ?>
                          <span class="text-muted small" title="Belum mengumpulkan">-</span>
                        <?php endif; ?>
                      </td>
                    <?php endforeach; ?>
                    <td class="text-center">
                      <span class="badge badge-<?= $stu['submitted_count'] === $totalAssignments ? 'success' : 'light' ?>">
                        <?= $stu['submitted_count'] ?> / <?= $totalAssignments ?>
                      </span>
                    </td>
                    <td class="text-center" style="background-color: #f8fafc;">
                      <div class="font-weight-bold text-primary" style="font-size: 18px;">
                        <?= number_format((float) $stu['final_score'], 2) ?>%
                      </div>
                      <div class="progress" style="height: 6px; margin-top: 4px;">
                        <div class="progress-bar bg-<?= $stu['final_score'] >= 75 ? 'success' : ($stu['final_score'] >= 60 ? 'info' : 'warning') ?>" role="progressbar" style="width: <?= min(100, $stu['final_score']) ?>%;"></div>
                      </div>
                    </td>
                    <td class="text-center">
                      <?php if ($stu['final_score'] >= 60): ?>
                        <span class="badge badge-success font-weight-bold"><i class="fa-solid fa-check-circle mr-1"></i> Lulus</span>
                      <?php else: ?>
                        <span class="badge badge-secondary font-weight-bold">Belum Lulus</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- TAB 2: PENILAIAN PENGUMPULAN TUGAS -->
    <div class="tab-pane fade <?= !empty($_GET['assignment_id']) || !empty($_GET['status']) ? 'show active' : '' ?>" id="submissionsPane" role="tabpanel" aria-labelledby="submissions-tab">
      
      <!-- Filter Bar -->
      <div class="admin-card mb-3">
        <form method="get" action="course_grades.php" class="form-inline d-flex flex-wrap align-items-center">
          <input type="hidden" name="course_id" value="<?= e((string) $selectedCourseId) ?>">
          <div class="form-group mr-3 mb-2">
            <label class="mr-2 small font-weight-bold">Filter Tugas:</label>
            <select class="form-control form-control-sm" name="assignment_id" onchange="this.form.submit()">
              <option value="0">Semua Tugas</option>
              <?php foreach ($assignments as $aOption): ?>
                <option value="<?= e((string) $aOption['id']) ?>" <?= (int) $aOption['id'] === $filterAssignmentId ? 'selected' : '' ?>>
                  Pertemuan <?= e((string) $aOption['meeting_order']) ?>: <?= e($aOption['title']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group mr-3 mb-2">
            <label class="mr-2 small font-weight-bold">Status Nilai:</label>
            <select class="form-control form-control-sm" name="status" onchange="this.form.submit()">
              <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Semua Status</option>
              <option value="ungraded" <?= $filterStatus === 'ungraded' ? 'selected' : '' ?>>Belum Dinilai (Menunggu)</option>
              <option value="revision" <?= $filterStatus === 'revision' ? 'selected' : '' ?>>Perlu Revisi</option>
              <option value="graded" <?= $filterStatus === 'graded' ? 'selected' : '' ?>>Sudah Dinilai (Final)</option>
            </select>
          </div>
          <button type="submit" class="btn btn-sm btn-outline-primary mb-2">Filter</button>
        </form>
      </div>

      <!-- Submission Cards / List -->
      <?php if (empty($allSubmissions)): ?>
        <div class="admin-card text-center py-5">
          <i class="fa-solid fa-folder-open fa-3x text-muted mb-3"></i>
          <h4>Tidak ada data pengumpulan tugas</h4>
          <p class="text-muted mb-0">Belum ada mahasiswa yang mengumpulkan tugas dengan kriteria filter ini.</p>
        </div>
      <?php else: ?>
        <div class="row">
          <?php foreach ($allSubmissions as $sub): ?>
            <?php
              $subStatus = $sub['status'] ?? ($sub['score'] !== null ? 'graded' : 'submitted');
              $isGraded = ($subStatus === 'graded' && $sub['score'] !== null);
              $isRevision = ($subStatus === 'revision');
              $subId = (int) $sub['id'];
              $borderClass = $isGraded ? 'border-success' : ($isRevision ? 'border-danger' : 'border-warning');
            ?>
            <div class="col-12 mb-4" id="sub_<?= $subId ?>">
              <div class="admin-card border <?= $borderClass ?> shadow-sm">
                <div class="d-flex flex-wrap justify-content-between align-items-start border-bottom pb-3 mb-3">
                  <div>
                    <span class="badge badge-dark text-warning font-weight-bold mb-1">
                      Pertemuan <?= e((string) $sub['meeting_order']) ?> &bull; <?= e($sub['meeting_title']) ?>
                    </span>
                    <h3 class="h5 font-weight-bold mb-1"><?= e($sub['assignment_title']) ?></h3>
                    <div class="text-muted small">
                      <i class="fa-solid fa-user mr-1 text-primary"></i> <strong><?= e($sub['user_name']) ?></strong> (<?= e($sub['user_email']) ?>)
                      &bull; <i class="fa-solid fa-clock mr-1 ml-2"></i> Dikumpulkan: <?= date('d M Y, H:i', strtotime($sub['submitted_at'])) ?> WIB
                    </div>
                  </div>
                  <div class="mt-2 mt-md-0 text-right">
                    <?php if ($isGraded): ?>
                      <span class="badge badge-success px-3 py-2 font-weight-bold" style="font-size: 14px;">
                        <i class="fa-solid fa-check-circle mr-1"></i> Nilai: <?= number_format((float) $sub['score'], 1) ?> / 100 (Final)
                      </span>
                    <?php elseif ($isRevision): ?>
                      <span class="badge badge-danger px-3 py-2 font-weight-bold" style="font-size: 14px;">
                        <i class="fa-solid fa-rotate-right mr-1"></i> Perlu Revisi
                      </span>
                    <?php else: ?>
                      <span class="badge badge-warning px-3 py-2 font-weight-bold" style="font-size: 14px;">
                        <i class="fa-solid fa-hourglass-half mr-1"></i> Menunggu Dinilai
                      </span>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="row">
                  <!-- Kolom Berkas / Tautan Tugas -->
                  <div class="col-lg-6 mb-3 mb-lg-0 border-right">
                    <h4 class="h6 font-weight-bold text-muted text-uppercase mb-2"><i class="fa-solid fa-paperclip mr-1"></i> Hasil Pengumpulan Mahasiswa</h4>
                    
                    <?php if ($sub['submission_type'] === 'drive_link'): ?>
                      <div class="p-3 bg-light rounded border mb-2">
                        <div class="font-weight-bold mb-1"><i class="fa-brands fa-google-drive text-warning mr-1"></i> Link Google Drive / URL:</div>
                        <a href="<?= e($sub['drive_url'] ?? '#') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-primary font-weight-bold">
                          <i class="fa-solid fa-arrow-up-right-from-square mr-1"></i> Buka Link Tugas di Tab Baru
                        </a>
                        <div class="mt-2 text-break small text-muted font-italic"><?= e($sub['drive_url'] ?? '') ?></div>
                      </div>
                    <?php else: ?>
                      <div class="p-3 bg-light rounded border mb-2">
                        <div class="font-weight-bold mb-1"><i class="fa-solid fa-file-arrow-down text-info mr-1"></i> File Upload:</div>
                        <a href="course_grades.php?download_submission=<?= $subId ?>" class="btn btn-sm btn-info font-weight-bold">
                          <i class="fa-solid fa-download mr-1"></i> Download File Tugas (<?= e($sub['file_name'] ?? 'File') ?>)
                        </a>
                      </div>
                    <?php endif; ?>

                    <?php if (!empty($sub['student_notes'])): ?>
                      <div class="mt-2">
                        <span class="small font-weight-bold text-muted">Catatan dari Mahasiswa:</span>
                        <div class="p-2 bg-white border rounded small font-italic mt-1"><?= nl2br(e($sub['student_notes'])) ?></div>
                      </div>
                    <?php endif; ?>
                  </div>

                  <!-- Kolom Form Penilaian & Revisi -->
                  <div class="col-lg-6">
                    <h4 class="h6 font-weight-bold text-muted text-uppercase mb-2"><i class="fa-solid fa-marker mr-1"></i> Form Penilaian / Menu Revisi</h4>
                    <form method="post" action="course_grades.php">
                      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="grade_submission">
                      <input type="hidden" name="submission_id" value="<?= $subId ?>">
                      <input type="hidden" name="course_id" value="<?= e((string) $selectedCourseId) ?>">

                      <!-- Pilihan Aksi: Nilai Selesai atau Minta Revisi -->
                      <div class="form-group mb-3 p-2 bg-light rounded border">
                        <label class="font-weight-bold small d-block mb-2">Tindakan Penilaian Admin:</label>
                        <div class="custom-control custom-radio custom-control-inline">
                          <input type="radio" id="status_grade_<?= $subId ?>" name="submission_status" value="graded" class="custom-control-input" <?= $subStatus !== 'revision' ? 'checked' : '' ?> onchange="toggleGradingAction(<?= $subId ?>, 'graded')">
                          <label class="custom-control-label font-weight-bold text-success" for="status_grade_<?= $subId ?>" style="cursor: pointer;">
                            <i class="fa-solid fa-check-circle mr-1"></i> Selesai Dinilai (Final)
                          </label>
                        </div>
                        <div class="custom-control custom-radio custom-control-inline">
                          <input type="radio" id="status_rev_<?= $subId ?>" name="submission_status" value="revision" class="custom-control-input" <?= $subStatus === 'revision' ? 'checked' : '' ?> onchange="toggleGradingAction(<?= $subId ?>, 'revision')">
                          <label class="custom-control-label font-weight-bold text-danger" for="status_rev_<?= $subId ?>" style="cursor: pointer;">
                            <i class="fa-solid fa-rotate-right mr-1"></i> Minta Revisi Tugas
                          </label>
                        </div>
                      </div>

                      <div class="form-row">
                        <div class="form-group col-md-5">
                          <label class="font-weight-bold small" id="score_label_<?= $subId ?>">Nilai Tugas (0 - 100):</label>
                          <input type="number" step="0.5" min="0" max="100" class="form-control font-weight-bold <?= $isGraded ? 'is-valid' : '' ?>" id="score_<?= $subId ?>" name="score" value="<?= $sub['score'] !== null ? e((string) $sub['score']) : '' ?>" placeholder="0 - 100" <?= $subStatus !== 'revision' ? 'required' : '' ?>>
                          <small class="form-text text-muted" id="score_help_<?= $subId ?>"><?= $subStatus === 'revision' ? 'Nilai opsional saat meminta revisi' : 'Bobot tugas: ' . $weightPerAssignment . '%' ?></small>
                        </div>
                        <div class="form-group col-md-7">
                          <label class="font-weight-bold small" id="feedback_label_<?= $subId ?>">
                            <?= $subStatus === 'revision' ? '<span class="text-danger"><i class="fa-solid fa-circle-exclamation mr-1"></i> Catatan Revisi untuk Mahasiswa (Wajib):</span>' : 'Catatan / Feedback untuk Mahasiswa:' ?>
                          </label>
                          <textarea class="form-control" id="feedback_<?= $subId ?>" name="feedback" rows="2" placeholder="<?= $subStatus === 'revision' ? 'Jelaskan apa saja yang perlu diperbaiki oleh mahasiswa...' : 'Komentar hasil tugas atau masukan...' ?>"><?= e($sub['feedback'] ?? '') ?></textarea>
                        </div>
                      </div>

                      <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap">
                        <div>
                          <?php if (!empty($sub['graded_at'])): ?>
                            <small class="text-muted"><i class="fa-solid fa-check mr-1"></i> Diperiksa: <?= date('d/m/Y H:i', strtotime($sub['graded_at'])) ?></small>
                          <?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-sm <?= $subStatus === 'revision' ? 'btn-danger' : 'btn-warning' ?> font-weight-bold px-3" id="btn_submit_<?= $subId ?>">
                          <i class="fa-solid <?= $subStatus === 'revision' ? 'fa-rotate-right' : 'fa-floppy-disk' ?> mr-1"></i> <?= $subStatus === 'revision' ? 'Kirim Permintaan Revisi' : ($isGraded ? 'Perbarui Nilai' : 'Simpan & Kunci Nilai') ?>
                        </button>
                      </div>
                    </form>
                  </div>
                </div>

              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>
  </div>

<script>
function toggleGradingAction(subId, action) {
    var scoreInput = document.getElementById('score_' + subId);
    var scoreHelp = document.getElementById('score_help_' + subId);
    var feedbackLabel = document.getElementById('feedback_label_' + subId);
    var feedbackTextarea = document.getElementById('feedback_' + subId);
    var submitBtn = document.getElementById('btn_submit_' + subId);

    if (action === 'revision') {
        if (scoreInput) {
            scoreInput.removeAttribute('required');
            scoreInput.placeholder = 'Opsional';
        }
        if (scoreHelp) {
            scoreHelp.innerText = 'Nilai opsional saat meminta revisi';
        }
        if (feedbackLabel) {
            feedbackLabel.innerHTML = '<span class="text-danger"><i class="fa-solid fa-circle-exclamation mr-1"></i> Catatan Revisi untuk Mahasiswa (Wajib):</span>';
        }
        if (feedbackTextarea) {
            feedbackTextarea.setAttribute('required', 'required');
            feedbackTextarea.placeholder = 'Jelaskan apa saja yang perlu diperbaiki oleh mahasiswa...';
        }
        if (submitBtn) {
            submitBtn.className = 'btn btn-sm btn-danger font-weight-bold px-3';
            submitBtn.innerHTML = '<i class="fa-solid fa-rotate-right mr-1"></i> Kirim Permintaan Revisi';
        }
    } else {
        if (scoreInput) {
            scoreInput.setAttribute('required', 'required');
            scoreInput.placeholder = '0 - 100';
        }
        if (scoreHelp) {
            scoreHelp.innerText = 'Bobot tugas dihitung ke nilai akhir';
        }
        if (feedbackLabel) {
            feedbackLabel.innerHTML = 'Catatan / Feedback untuk Mahasiswa:';
        }
        if (feedbackTextarea) {
            feedbackTextarea.removeAttribute('required');
            feedbackTextarea.placeholder = 'Komentar hasil tugas atau masukan...';
        }
        if (submitBtn) {
            submitBtn.className = 'btn btn-sm btn-warning font-weight-bold px-3';
            submitBtn.innerHTML = '<i class="fa-solid fa-floppy-disk mr-1"></i> Simpan & Kunci Nilai';
        }
    }
}
</script>

<?php endif; ?>

<?php admin_footer(); ?>

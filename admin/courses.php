<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

require_admin();

$pdo = pdo();

/**
 * Course yang sudah melewati waktu akhir otomatis dianggap inaktif.
 * Ini membuat frontend tidak menampilkan course expired,
 * sementara admin tetap bisa melihat course tersebut dengan status Inaktif.
 */
function mark_expired_courses_inactive(PDO $pdo): void
{
    $now = date('Y-m-d H:i:s');

    $statement = $pdo->prepare(
        'UPDATE courses
         SET is_active = 0
         WHERE is_active = 1
           AND end_at IS NOT NULL
           AND end_at <> ""
           AND end_at < ?'
    );
    $statement->execute([$now]);
}

function course_has_ended(array $course): bool
{
    if (empty($course['end_at'])) {
        return false;
    }

    $endTime = strtotime((string) $course['end_at']);
    if ($endTime === false) {
        return false;
    }

    return $endTime < time();
}

function admin_course_status_label(array $course): string
{
    if (course_has_ended($course)) {
        return 'Inaktif';
    }

    return (int) ($course['is_active'] ?? 0) === 1 ? 'Aktif' : 'Disembunyikan';
}

function admin_course_status_detail(array $course): string
{
    if (course_has_ended($course)) {
        return 'Masa kelas sudah berakhir';
    }

    return course_availability_status($course)['label'];
}

function admin_course_status_class(array $course): string
{
    if (course_has_ended($course)) {
        return 'text-danger';
    }

    return (int) ($course['is_active'] ?? 0) === 1 ? 'text-success' : 'text-muted';
}

mark_expired_courses_inactive($pdo);


/**
 * Token enrollment sekarang disatukan di tabel enrollment_tokens.
 * courses.access_token hanya disimpan sebagai legacy/fallback agar struktur database lama tetap aman,
 * tetapi token yang dipakai user adalah token dari course_tokens.php.
 */
function find_course_enrollment_token(PDO $pdo, int $courseId): ?array
{
    $statement = $pdo->prepare(
        'SELECT et.*
         FROM enrollment_tokens et
         INNER JOIN enrollment_token_courses etc ON etc.token_id = et.id
         WHERE etc.course_id = ?
         ORDER BY et.is_active DESC, et.id DESC
         LIMIT 1'
    );
    $statement->execute([$courseId]);
    $token = $statement->fetch(PDO::FETCH_ASSOC);
    return $token ?: null;
}

function save_single_course_enrollment_token(PDO $pdo, int $courseId, string $courseTitle, ?string $token = null): string
{
    $token = strtoupper(trim((string) $token));
    if ($token === '') {
        $token = generate_course_token(12);
    }

    $existing = find_course_enrollment_token($pdo, $courseId);
    if ($existing) {
        $statement = $pdo->prepare('UPDATE enrollment_tokens SET label = ?, token = ?, is_active = 1 WHERE id = ?');
        $statement->execute(['Token - ' . $courseTitle, $token, (int) $existing['id']]);
        return $token;
    }

    $statement = $pdo->prepare('INSERT INTO enrollment_tokens (label, token, is_active) VALUES (?, ?, 1)');
    $statement->execute(['Token - ' . $courseTitle, $token]);
    $tokenId = (int) $pdo->lastInsertId();

    $statement = $pdo->prepare('INSERT INTO enrollment_token_courses (token_id, course_id) VALUES (?, ?)');
    $statement->execute([$tokenId, $courseId]);

    return $token;
}


function upload_course_material_file(array $file): ?array
{
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload file materi gagal. Silakan coba lagi.');
    }

    $maxSize = 25 * 1024 * 1024;
    if ((int) ($file['size'] ?? 0) > $maxSize) {
        throw new RuntimeException('Ukuran file materi maksimal 25MB.');
    }

    $originalName = basename((string) ($file['name'] ?? 'materi'));
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['pdf', 'ppt', 'pptx'];

    if (!in_array($extension, $allowed, true)) {
        throw new RuntimeException('File materi hanya boleh PDF, PPT, atau PPTX.');
    }

    $uploadDir = dirname(__DIR__) . '/uploads/course_materials';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Folder upload materi tidak bisa dibuat.');
    }

    $safeBaseName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', pathinfo($originalName, PATHINFO_FILENAME));
    $safeBaseName = trim((string) $safeBaseName, '-');
    if ($safeBaseName === '') {
        $safeBaseName = 'materi';
    }

    $fileName = $safeBaseName . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
        throw new RuntimeException('File materi gagal disimpan.');
    }

    return [
        'path' => 'uploads/course_materials/' . $fileName,
        'name' => $originalName,
    ];
}

function delete_course_material_file(?string $path): void
{
    $path = trim((string) $path);
    if ($path === '') {
        return;
    }

    $fullPath = dirname(__DIR__) . '/' . ltrim($path, '/');
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $action = (string) ($_POST['action'] ?? 'save_course');

    try {
        if ($action === 'save_heading') {
            save_settings([
                'course_page_title' => trim((string) ($_POST['course_page_title'] ?? 'Course')),
                'course_page_subtitle' => trim((string) ($_POST['course_page_subtitle'] ?? '')),
                'course_access_label' => trim((string) ($_POST['course_access_label'] ?? 'Masukkan kode akses kelas')),
            ]);
            set_admin_flash('success', 'Pengaturan halaman course berhasil disimpan.');
        } elseif ($action === 'delete_course') {
            $statement = $pdo->prepare('DELETE FROM courses WHERE id = ?');
            $statement->execute([(int) ($_POST['id'] ?? 0)]);
            set_admin_flash('success', 'Course berhasil dihapus.');
        } elseif ($action === 'regenerate_token') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Course tidak valid.');
            }
            $course = get_course_by_id($id, $pdo, false);
            if (!$course) {
                throw new RuntimeException('Course tidak ditemukan.');
            }
            $newToken = save_single_course_enrollment_token($pdo, $id, (string) $course['title']);
            $statement = $pdo->prepare('UPDATE courses SET access_token = ? WHERE id = ?');
            $statement->execute([$newToken, $id]);
            set_admin_flash('success', 'Token enrollment berhasil dibuat/disinkronkan: ' . $newToken . '. Token ini juga tampil di menu Token Course.');
        } elseif ($action === 'save_meeting') {
            $id = (int) ($_POST['id'] ?? 0);
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $uploadedMaterial = upload_course_material_file($_FILES['material_file'] ?? []);
            $materialPath = trim((string) ($_POST['existing_material_file_path'] ?? ''));
            $materialName = trim((string) ($_POST['existing_material_file_name'] ?? ''));

            if (isset($_POST['remove_material_file'])) {
                delete_course_material_file($materialPath);
                $materialPath = '';
                $materialName = '';
            }

            if ($uploadedMaterial !== null) {
                delete_course_material_file($materialPath);
                $materialPath = $uploadedMaterial['path'];
                $materialName = $uploadedMaterial['name'];
            }

            $data = [
                $courseId,
                trim((string) ($_POST['title'] ?? '')),
                trim((string) ($_POST['description'] ?? '')),
                trim((string) ($_POST['youtube_url'] ?? '')),
                $materialPath,
                $materialName,
                (int) ($_POST['sort_order'] ?? 0),
                isset($_POST['is_active']) ? 1 : 0,
            ];

            if ($data[0] <= 0 || $data[1] === '') {
                throw new RuntimeException('Course dan judul pertemuan wajib diisi.');
            }

            if ($id > 0) {
                $statement = $pdo->prepare('UPDATE course_meetings SET course_id = ?, title = ?, description = ?, youtube_url = ?, material_file_path = ?, material_file_name = ?, sort_order = ?, is_active = ? WHERE id = ?');
                $statement->execute([...$data, $id]);
            } else {
                $statement = $pdo->prepare('INSERT INTO course_meetings (course_id, title, description, youtube_url, material_file_path, material_file_name, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $statement->execute($data);
            }
            set_admin_flash('success', 'Pertemuan berhasil disimpan.');
        } elseif ($action === 'save_quiz') {
            $id = (int) ($_POST['id'] ?? 0);
            $meetingId = (int) ($_POST['meeting_id'] ?? 0);
            $data = [
                $meetingId,
                trim((string) ($_POST['question'] ?? '')),
                trim((string) ($_POST['option_a'] ?? '')),
                trim((string) ($_POST['option_b'] ?? '')),
                trim((string) ($_POST['option_c'] ?? '')),
                trim((string) ($_POST['option_d'] ?? '')),
                strtoupper(trim((string) ($_POST['correct_option'] ?? 'A'))),
                (int) ($_POST['sort_order'] ?? 0),
                isset($_POST['is_active']) ? 1 : 0,
            ];
            if ($data[0] <= 0 || $data[1] === '' || $data[2] === '' || $data[3] === '' || $data[4] === '' || $data[5] === '') {
                throw new RuntimeException('Pertemuan, pertanyaan, dan semua opsi quiz wajib diisi.');
            }
            if (!in_array($data[6], ['A', 'B', 'C', 'D'], true)) {
                throw new RuntimeException('Kunci jawaban quiz tidak valid.');
            }
            if ($id > 0) {
                $statement = $pdo->prepare('UPDATE course_quizzes SET meeting_id = ?, question = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ?, sort_order = ?, is_active = ? WHERE id = ?');
                $statement->execute([...$data, $id]);
            } else {
                $statement = $pdo->prepare('INSERT INTO course_quizzes (meeting_id, question, option_a, option_b, option_c, option_d, correct_option, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $statement->execute($data);
            }
            set_admin_flash('success', 'Quiz berhasil disimpan.');
        } elseif ($action === 'delete_quiz') {
            $statement = $pdo->prepare('DELETE FROM course_quizzes WHERE id = ?');
            $statement->execute([(int) ($_POST['id'] ?? 0)]);
            set_admin_flash('success', 'Quiz berhasil dihapus.');
        } elseif ($action === 'delete_meeting') {
            $meetingId = (int) ($_POST['id'] ?? 0);
            if ($meetingId > 0) {
                $statement = $pdo->prepare('SELECT material_file_path FROM course_meetings WHERE id = ?');
                $statement->execute([$meetingId]);
                $meeting = $statement->fetch(PDO::FETCH_ASSOC);
                if ($meeting) {
                    delete_course_material_file($meeting['material_file_path'] ?? null);
                }
            }

            $statement = $pdo->prepare('DELETE FROM course_meetings WHERE id = ?');
            $statement->execute([$meetingId]);
            set_admin_flash('success', 'Pertemuan berhasil dihapus.');
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $thumbnailPath = trim((string) ($_POST['thumbnail_path'] ?? ''));
            $uploadedImage = upload_course_image($_FILES['thumbnail_file'] ?? []);

            if ($uploadedImage !== null) {
                $thumbnailPath = $uploadedImage;
            }

            $legacyToken = trim((string) ($_POST['legacy_access_token'] ?? ''));
            if ($legacyToken === '') {
                $legacyToken = generate_course_token(12);
            }

            $startAt = normalize_course_datetime($_POST['start_at'] ?? null);
            $endAt = normalize_course_datetime($_POST['end_at'] ?? null);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($endAt !== null && strtotime($endAt) !== false && strtotime($endAt) < time()) {
                $isActive = 0;
            }

            $data = [
                trim((string) ($_POST['title'] ?? '')),
                trim((string) ($_POST['description'] ?? '')),
                $thumbnailPath,
                $legacyToken,
                $startAt,
                $endAt,
                (int) ($_POST['sort_order'] ?? 0),
                $isActive,
            ];

            if ($data[0] === '' || $data[2] === '') {
                throw new RuntimeException('Judul dan thumbnail wajib diisi. Token enrollment dikelola dari menu Token Course.');
            }

            if ($data[4] !== null && $data[5] !== null && strtotime($data[4]) > strtotime($data[5])) {
                throw new RuntimeException('Waktu mulai tidak boleh lebih besar dari waktu akhir.');
            }

            if ($id > 0) {
                $statement = $pdo->prepare('UPDATE courses SET title = ?, description = ?, thumbnail_path = ?, access_token = ?, start_at = ?, end_at = ?, sort_order = ?, is_active = ? WHERE id = ?');
                $statement->execute([...$data, $id]);
            } else {
                $statement = $pdo->prepare('INSERT INTO courses (title, description, thumbnail_path, access_token, start_at, end_at, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $statement->execute($data);
            }
            set_admin_flash('success', 'Course berhasil disimpan.');
        }
    } catch (Throwable $exception) {
        set_admin_flash('danger', $exception->getMessage());
    }

    redirect('courses.php');
}

$settings = get_settings($pdo);
$courses = get_courses($pdo, false);
$meetingsByCourse = [];
$tokensByCourse = [];
$quizzesByCourse = [];
foreach ($courses as $course) {
    $courseKey = (int) $course['id'];
    $meetingsByCourse[$courseKey] = get_course_meetings($courseKey, $pdo, false);
    $tokensByCourse[$courseKey] = find_course_enrollment_token($pdo, $courseKey);
    $quizzesByCourse[$courseKey] = get_course_quizzes($courseKey, $pdo, false);
}

admin_header('Edit Courses');
?>
<div class="settings-accordion" id="courseSettingsAccordion">
  <div class="admin-card setting-card">
    <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#coursePageSettings" aria-expanded="true" aria-controls="coursePageSettings">
      <span><i class="uil uil-book-open"></i> Pengaturan Halaman Course <small>Judul, subtitle, dan teks token di frontend.</small></span>
      <i class="uil uil-angle-down"></i>
    </button>
    <div id="coursePageSettings" class="collapse show" data-parent="#courseSettingsAccordion">
      <form method="post" action="courses.php" class="settings-body">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_heading">
        <div class="form-group"><label>Judul halaman</label><input type="text" class="form-control" name="course_page_title" value="<?= e($settings['course_page_title'] ?? 'Course') ?>"></div>
        <div class="form-group"><label>Subtitle</label><textarea class="form-control" name="course_page_subtitle" rows="2"><?= e($settings['course_page_subtitle'] ?? '') ?></textarea></div>
        <div class="form-group"><label>Label token akses</label><input type="text" class="form-control" name="course_access_label" value="<?= e($settings['course_access_label'] ?? 'Masukkan kode akses kelas') ?>"></div>
        <button type="submit" class="btn btn-warning font-weight-bold"><i class="uil uil-check-circle"></i> Simpan Pengaturan</button>
      </form>
    </div>
  </div>

  <div class="admin-card setting-card">
    <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#newCourseSettings" aria-expanded="false" aria-controls="newCourseSettings">
      <span><i class="uil uil-plus-circle"></i> Tambah Course <small>Buat kelas baru. Token enrollment dikelola dari menu Token Course.</small></span>
      <i class="uil uil-angle-down"></i>
    </button>
    <div id="newCourseSettings" class="collapse" data-parent="#courseSettingsAccordion">
      <form method="post" action="courses.php" class="settings-body" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_course">
        <input type="hidden" name="id" value="0">
        <div class="row">
          <div class="form-group col-md-7"><label>Judul Course</label><input type="text" class="form-control" name="title" required><input type="hidden" name="legacy_access_token" value=""></div>
          <div class="form-group col-md-3"><label>Urutan</label><input type="number" class="form-control" name="sort_order" value="0"></div>
          <div class="form-group col-md-2"><label>Status</label><div class="custom-control custom-checkbox mt-2"><input type="checkbox" class="custom-control-input" id="new_course_active" name="is_active" checked><label class="custom-control-label" for="new_course_active">Aktif</label></div></div>
        </div>
        <div class="form-group"><label>Deskripsi</label><textarea class="form-control" name="description" rows="3"></textarea></div>
        <div class="row">
          <div class="form-group col-md-6"><label>Waktu mulai course</label><input type="datetime-local" class="form-control" name="start_at"></div>
          <div class="form-group col-md-6"><label>Waktu akhir course</label><input type="datetime-local" class="form-control" name="end_at"></div>
        </div>
        <div class="row">
          <div class="form-group col-md-6"><label>Path thumbnail</label><input type="text" class="form-control" name="thumbnail_path" placeholder="images/course/course-baru.png"></div>
          <div class="form-group col-md-6"><label>Upload thumbnail</label><input type="file" class="form-control-file" name="thumbnail_file" accept=".jpg,.jpeg,.png,.gif,.webp"></div>
        </div>
        <button type="submit" class="btn btn-warning font-weight-bold"><i class="uil uil-plus-circle"></i> Tambah Course</button>
      </form>
    </div>
  </div>

  <div class="admin-card setting-card">
    <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#newMeetingSettings" aria-expanded="false" aria-controls="newMeetingSettings">
      <span><i class="uil uil-video"></i> Tambah Pertemuan <small>Tambahkan materi dan video YouTube ke course.</small></span>
      <i class="uil uil-angle-down"></i>
    </button>
    <div id="newMeetingSettings" class="collapse" data-parent="#courseSettingsAccordion">
      <form method="post" action="courses.php" class="settings-body" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_meeting">
        <input type="hidden" name="id" value="0">
        <div class="row">
          <div class="form-group col-md-4"><label>Course</label><select class="form-control" name="course_id" required><?php foreach ($courses as $course): ?><option value="<?= e((string) $course['id']) ?>"><?= e($course['title']) ?></option><?php endforeach; ?></select></div>
          <div class="form-group col-md-5"><label>Judul Pertemuan</label><input type="text" class="form-control" name="title" required></div>
          <div class="form-group col-md-3"><label>Urutan</label><input type="number" class="form-control" name="sort_order" value="0"></div>
        </div>
        <div class="form-group"><label>Deskripsi</label><textarea class="form-control" name="description" rows="3"></textarea></div>
        <div class="form-group"><label>URL YouTube</label><input type="text" class="form-control" name="youtube_url" placeholder="https://www.youtube.com/watch?v=..."></div>
        <div class="form-group"><label>Upload Materi PDF/PPT</label><input type="file" class="form-control-file" name="material_file" accept=".pdf,.ppt,.pptx"><small class="form-text text-muted">Format: PDF, PPT, PPTX. Maksimal 25MB.</small></div>
        <div class="custom-control custom-checkbox mb-3"><input type="checkbox" class="custom-control-input" id="new_meeting_active" name="is_active" checked><label class="custom-control-label" for="new_meeting_active">Tampilkan pertemuan</label></div>
        <button type="submit" class="btn btn-warning font-weight-bold"><i class="uil uil-plus-circle"></i> Tambah Pertemuan</button>
      </form>
    </div>
  </div>
</div>

<div class="settings-accordion" id="courseListAccordion">
  <?php foreach ($courses as $course): ?>
    <div class="admin-card setting-card">
      <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#course_<?= e((string) $course['id']) ?>" aria-expanded="false" aria-controls="course_<?= e((string) $course['id']) ?>">
        <span><i class="uil uil-edit"></i> <?= e($course['title']) ?> <small><strong class="<?= e(admin_course_status_class($course)) ?>"><?= e(admin_course_status_label($course)) ?></strong> · <?= e(admin_course_status_detail($course)) ?> · Token enrollment: <?= $tokensByCourse[(int) $course['id']] ? e($tokensByCourse[(int) $course['id']]['token']) : 'Belum ada' ?> · <?= count($meetingsByCourse[(int) $course['id']] ?? []) ?> pertemuan · <?= count($quizzesByCourse[(int) $course['id']] ?? []) ?> quiz</small></span>
        <i class="uil uil-angle-down"></i>
      </button>
      <div id="course_<?= e((string) $course['id']) ?>" class="collapse" data-parent="#courseListAccordion">
        <form method="post" action="courses.php" class="settings-body" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= e((string) $course['id']) ?>">
          <div class="row">
            <div class="col-lg-3"><img class="project-preview mb-3 mb-lg-0" src="../<?= e($course['thumbnail_path']) ?>" alt="<?= e($course['title']) ?>"></div>
            <div class="col-lg-9">
              <div class="row">
                <div class="form-group col-md-5"><label>Judul</label><input type="text" class="form-control" name="title" value="<?= e($course['title']) ?>" required><input type="hidden" name="legacy_access_token" value="<?= e($course['access_token'] ?? '') ?>"></div>
                <div class="form-group col-md-3"><label>Token enrollment</label><input type="text" class="form-control" value="<?= $tokensByCourse[(int) $course['id']] ? e($tokensByCourse[(int) $course['id']]['token']) : 'Belum ada' ?>" readonly><small class="form-text text-muted">Token utama dari menu Token Course.</small></div>
                <div class="form-group col-md-2"><label>Urutan</label><input type="number" class="form-control" name="sort_order" value="<?= e((string) $course['sort_order']) ?>"></div>
                <div class="form-group col-md-2"><label>Status</label><div class="custom-control custom-checkbox mt-2"><input type="checkbox" class="custom-control-input" id="course_active_<?= e((string) $course['id']) ?>" name="is_active" <?= ((int) $course['is_active'] === 1 && !course_has_ended($course)) ? 'checked' : '' ?> <?= course_has_ended($course) ? 'disabled' : '' ?>><label class="custom-control-label" for="course_active_<?= e((string) $course['id']) ?>"><?= course_has_ended($course) ? 'Inaktif' : 'Aktif' ?></label></div><?php if (course_has_ended($course)): ?><small class="form-text text-danger">Masa kelas sudah berakhir. Ubah waktu akhir ke tanggal mendatang untuk mengaktifkan kembali.</small><?php endif; ?></div>
              </div>
              <div class="form-group"><label>Deskripsi</label><textarea class="form-control" name="description" rows="3"><?= e($course['description'] ?? '') ?></textarea></div>
              <div class="row">
                <div class="form-group col-md-6"><label>Waktu mulai course</label><input type="datetime-local" class="form-control" name="start_at" value="<?= e(datetime_local_value($course['start_at'] ?? null)) ?>"></div>
                <div class="form-group col-md-6"><label>Waktu akhir course</label><input type="datetime-local" class="form-control" name="end_at" value="<?= e(datetime_local_value($course['end_at'] ?? null)) ?>"><?php if (course_has_ended($course)): ?><small class="form-text text-danger">Course otomatis hilang dari frontend karena masa kelas sudah berakhir.</small><?php endif; ?></div>
              </div>
              <div class="row">
                <div class="form-group col-md-6"><label>Path thumbnail</label><input type="text" class="form-control" name="thumbnail_path" value="<?= e($course['thumbnail_path']) ?>"></div>
                <div class="form-group col-md-6"><label>Ganti thumbnail</label><input type="file" class="form-control-file" name="thumbnail_file" accept=".jpg,.jpeg,.png,.gif,.webp"></div>
              </div>
              <button type="submit" name="action" value="save_course" class="btn btn-warning font-weight-bold mr-2 mb-2"><i class="uil uil-check-circle"></i> Simpan Course</button>
              <button type="submit" name="action" value="regenerate_token" class="btn btn-outline-primary mr-2 mb-2" onclick="return confirm('Generate/sinkronkan token enrollment untuk course ini? Token akan tampil juga di menu Token Course.')"><i class="uil uil-refresh"></i> Generate/Sinkronkan Token</button>
              <button type="submit" name="action" value="delete_course" class="btn btn-outline-danger mb-2" onclick="return confirm('Hapus course dan semua pertemuannya?')"><i class="uil uil-trash-alt"></i> Hapus Course</button>
            </div>
          </div>
        </form>

        <div class="settings-body pt-0">
          <h3 class="h5 mb-3"><i class="uil uil-list-ul"></i> Pertemuan</h3>
          <?php foreach (($meetingsByCourse[(int) $course['id']] ?? []) as $meeting): ?>
            <form method="post" action="courses.php" class="admin-nested-form mb-3" enctype="multipart/form-data">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="id" value="<?= e((string) $meeting['id']) ?>">
              <input type="hidden" name="course_id" value="<?= e((string) $course['id']) ?>">
              <input type="hidden" name="existing_material_file_path" value="<?= e($meeting['material_file_path'] ?? '') ?>">
              <input type="hidden" name="existing_material_file_name" value="<?= e($meeting['material_file_name'] ?? '') ?>">
              <div class="row">
                <div class="form-group col-md-6"><label>Judul Pertemuan</label><input type="text" class="form-control" name="title" value="<?= e($meeting['title']) ?>" required></div>
                <div class="form-group col-md-2"><label>Urutan</label><input type="number" class="form-control" name="sort_order" value="<?= e((string) $meeting['sort_order']) ?>"></div>
                <div class="form-group col-md-4"><label>URL YouTube</label><input type="text" class="form-control" name="youtube_url" value="<?= e($meeting['youtube_url'] ?? '') ?>"></div>
              </div>
              <div class="form-group"><label>Deskripsi</label><textarea class="form-control" name="description" rows="2"><?= e($meeting['description'] ?? '') ?></textarea></div>
              <div class="form-group">
                <label>File Materi PDF/PPT</label>
                <?php if (!empty($meeting['material_file_path'])): ?>
                  <div class="mb-2">
                    <a class="btn btn-sm btn-outline-secondary" href="../<?= e($meeting['material_file_path']) ?>" target="_blank" rel="noopener">
                      <i class="uil uil-file-download"></i> <?= e($meeting['material_file_name'] ?: basename((string) $meeting['material_file_path'])) ?>
                    </a>
                    <div class="custom-control custom-checkbox d-inline-block ml-2">
                      <input type="checkbox" class="custom-control-input" id="remove_material_<?= e((string) $meeting['id']) ?>" name="remove_material_file" value="1">
                      <label class="custom-control-label" for="remove_material_<?= e((string) $meeting['id']) ?>">Hapus file</label>
                    </div>
                  </div>
                <?php endif; ?>
                <input type="file" class="form-control-file" name="material_file" accept=".pdf,.ppt,.pptx">
                <small class="form-text text-muted">Kosongkan jika tidak ingin mengganti file. Format: PDF, PPT, PPTX. Maksimal 25MB.</small>
              </div>
              <div class="d-flex flex-wrap align-items-center">
                <div class="custom-control custom-checkbox mr-3 mb-2"><input type="checkbox" class="custom-control-input" id="meeting_active_<?= e((string) $meeting['id']) ?>" name="is_active" <?= (int) $meeting['is_active'] === 1 ? 'checked' : '' ?>><label class="custom-control-label" for="meeting_active_<?= e((string) $meeting['id']) ?>">Aktif</label></div>
                <button type="submit" name="action" value="save_meeting" class="btn btn-sm btn-warning font-weight-bold mr-2 mb-2"><i class="uil uil-check-circle"></i> Simpan</button>
                <button type="submit" name="action" value="delete_meeting" class="btn btn-sm btn-outline-danger mb-2" onclick="return confirm('Hapus pertemuan ini?')"><i class="uil uil-trash-alt"></i> Hapus</button>
              </div>
            </form>
          <?php endforeach; ?>
          <hr>
          <h3 class="h5 mb-3"><i class="uil uil-question-circle"></i> Quiz</h3>
          <form method="post" action="courses.php" class="admin-nested-form mb-4">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_quiz">
            <div class="row">
              <div class="form-group col-md-4"><label>Pertemuan</label><select class="form-control" name="meeting_id" required><option value="">Pilih pertemuan</option><?php foreach (($meetingsByCourse[(int) $course['id']] ?? []) as $meetingOption): ?><option value="<?= e((string) $meetingOption['id']) ?>"><?= e($meetingOption['title']) ?></option><?php endforeach; ?></select></div>
              <div class="form-group col-md-6"><label>Pertanyaan</label><input type="text" class="form-control" name="question" required></div>
              <div class="form-group col-md-2"><label>Urutan</label><input type="number" class="form-control" name="sort_order" value="0"></div>
            </div>
            <div class="row">
              <div class="form-group col-md-3"><label>Opsi A</label><input type="text" class="form-control" name="option_a" required></div>
              <div class="form-group col-md-3"><label>Opsi B</label><input type="text" class="form-control" name="option_b" required></div>
              <div class="form-group col-md-3"><label>Opsi C</label><input type="text" class="form-control" name="option_c" required></div>
              <div class="form-group col-md-3"><label>Opsi D</label><input type="text" class="form-control" name="option_d" required></div>
            </div>
            <div class="d-flex flex-wrap align-items-center">
              <div class="form-group mr-3 mb-2"><label>Kunci Jawaban</label><select class="form-control" name="correct_option"><option>A</option><option>B</option><option>C</option><option>D</option></select></div>
              <div class="custom-control custom-checkbox mr-3 mb-2 mt-4"><input type="checkbox" class="custom-control-input" id="new_quiz_active_<?= e((string) $course['id']) ?>" name="is_active" checked><label class="custom-control-label" for="new_quiz_active_<?= e((string) $course['id']) ?>">Aktif</label></div>
              <button type="submit" class="btn btn-sm btn-warning font-weight-bold mt-3"><i class="uil uil-plus-circle"></i> Tambah Quiz</button>
            </div>
          </form>
          <?php foreach (($quizzesByCourse[(int) $course['id']] ?? []) as $quiz): ?>
            <form method="post" action="courses.php" class="admin-nested-form mb-3">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="id" value="<?= e((string) $quiz['id']) ?>">
              <div class="row">
                <div class="form-group col-md-4"><label>Pertemuan</label><select class="form-control" name="meeting_id" required><?php foreach (($meetingsByCourse[(int) $course['id']] ?? []) as $meetingOption): ?><option value="<?= e((string) $meetingOption['id']) ?>" <?= (int) $meetingOption['id'] === (int) $quiz['meeting_id'] ? 'selected' : '' ?>><?= e($meetingOption['title']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group col-md-6"><label>Pertanyaan</label><input type="text" class="form-control" name="question" value="<?= e($quiz['question']) ?>" required></div>
                <div class="form-group col-md-2"><label>Urutan</label><input type="number" class="form-control" name="sort_order" value="<?= e((string) $quiz['sort_order']) ?>"></div>
              </div>
              <div class="row">
                <div class="form-group col-md-3"><label>Opsi A</label><input type="text" class="form-control" name="option_a" value="<?= e($quiz['option_a']) ?>" required></div>
                <div class="form-group col-md-3"><label>Opsi B</label><input type="text" class="form-control" name="option_b" value="<?= e($quiz['option_b']) ?>" required></div>
                <div class="form-group col-md-3"><label>Opsi C</label><input type="text" class="form-control" name="option_c" value="<?= e($quiz['option_c']) ?>" required></div>
                <div class="form-group col-md-3"><label>Opsi D</label><input type="text" class="form-control" name="option_d" value="<?= e($quiz['option_d']) ?>" required></div>
              </div>
              <div class="d-flex flex-wrap align-items-center">
                <div class="form-group mr-3 mb-2"><label>Kunci</label><select class="form-control" name="correct_option"><?php foreach (['A','B','C','D'] as $option): ?><option value="<?= e($option) ?>" <?= $quiz['correct_option'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></div>
                <div class="custom-control custom-checkbox mr-3 mb-2 mt-4"><input type="checkbox" class="custom-control-input" id="quiz_active_<?= e((string) $quiz['id']) ?>" name="is_active" <?= (int) $quiz['is_active'] === 1 ? 'checked' : '' ?>><label class="custom-control-label" for="quiz_active_<?= e((string) $quiz['id']) ?>">Aktif</label></div>
                <button type="submit" name="action" value="save_quiz" class="btn btn-sm btn-warning font-weight-bold mr-2 mb-2 mt-3"><i class="uil uil-check-circle"></i> Simpan Quiz</button>
                <button type="submit" name="action" value="delete_quiz" class="btn btn-sm btn-outline-danger mb-2 mt-3" onclick="return confirm('Hapus quiz ini?')"><i class="uil uil-trash-alt"></i> Hapus</button>
              </div>
            </form>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php admin_footer(); ?>



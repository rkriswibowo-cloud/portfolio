<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

function course_material_storage_path(string $path): ?string
{
    $path = trim(str_replace('\\', '/', $path));

    if ($path === '' || preg_match('~^[a-z][a-z0-9+.-]*://~i', $path)) {
        return null;
    }

    $basePath = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'course_materials');
    $filePath = realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/')));

    if ($basePath === false || $filePath === false || !is_file($filePath)) {
        return null;
    }

    $basePrefix = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strncmp($filePath, $basePrefix, strlen($basePrefix)) !== 0) {
        return null;
    }

    return $filePath;
}

function course_material_download_name(string $path, string $name): string
{
    $fileName = trim($name) !== '' ? trim($name) : basename(str_replace('\\', '/', $path));
    $fileName = preg_replace('/[\r\n"\\\\\/]+/', '_', $fileName) ?: 'materi';

    return $fileName;
}

function course_material_content_type(string $filePath): string
{
    switch (strtolower(pathinfo($filePath, PATHINFO_EXTENSION))) {
        case 'pdf':
            return 'application/pdf';
        case 'ppt':
            return 'application/vnd.ms-powerpoint';
        case 'pptx':
            return 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
        default:
            return 'application/octet-stream';
    }
}

function course_material_content_disposition(string $fileName): string
{
    $fallback = preg_replace('/[^\x20-\x7E]/', '_', $fileName) ?: 'materi';
    $fallback = addcslashes($fallback, '"\\');

    return 'attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($fileName);
}

function course_stream_material_file(string $filePath, string $downloadName): void
{
    $fileSize = filesize($filePath);

    session_write_close();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Description: File Transfer');
    header('Content-Type: ' . course_material_content_type($filePath));
    header('Content-Disposition: ' . course_material_content_disposition($downloadName));
    header('Content-Transfer-Encoding: binary');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    if ($fileSize !== false) {
        header('Content-Length: ' . (string) $fileSize);
    }

    readfile($filePath);
    exit;
}
require_once __DIR__ . '/config/content.php';
require_once __DIR__ . '/config/lang.php';
start_app_session();
$pdo = pdo(true);
$settings = get_settings($pdo);

/**
 * Course yang sudah melewati waktu akhir otomatis dibuat inaktif,
 * sehingga tidak tampil di frontend dan tidak bisa diakses langsung via URL.
 */
$appTimezone = new DateTimeZone('Asia/Jakarta');
$now = new DateTimeImmutable('now', $appTimezone);
$nowForDatabase = $now->format('Y-m-d H:i:s');

$statement = $pdo->prepare(
    'UPDATE courses
     SET is_active = 0
     WHERE is_active = 1
       AND end_at IS NOT NULL
       AND end_at <> ""
       AND end_at < ?'
);
$statement->execute([$nowForDatabase]);

$courses = array_values(array_filter(
    get_courses($pdo, true),
    static function (array $course) use ($appTimezone, $now): bool {
        if (!empty($course['end_at'])) {
            $endTime = DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                (string) $course['end_at'],
                $appTimezone
            );

            if ($endTime instanceof DateTimeImmutable && $endTime < $now) {
                return false;
            }
        }

        return true;
    }
));

$rawCourseSearchQuery = $_GET['q'] ?? '';
$courseSearchQuery = is_string($rawCourseSearchQuery) ? trim($rawCourseSearchQuery) : '';
if (strlen($courseSearchQuery) > 100) {
    $courseSearchQuery = substr($courseSearchQuery, 0, 100);
}
$hasCourseSearch = $courseSearchQuery !== '';

if ($hasCourseSearch) {
    $courses = array_values(array_filter(
        $courses,
        static fn(array $course): bool => stripos((string) ($course['title'] ?? ''), $courseSearchQuery) !== false
    ));
}

$coursesPerPage = 8;
$courseTotalCount = count($courses);
$courseTotalPages = max(1, (int) ceil($courseTotalCount / $coursesPerPage));
$rawCoursePage = $_GET['page'] ?? 1;
$courseCurrentPage = max(1, is_numeric($rawCoursePage) ? (int) $rawCoursePage : 1);
if ($courseCurrentPage > $courseTotalPages) {
    $courseCurrentPage = $courseTotalPages;
}
$courseOffset = ($courseCurrentPage - 1) * $coursesPerPage;
$courses = array_slice($courses, $courseOffset, $coursesPerPage);
$coursePageUrl = static function (int $page) use ($courseSearchQuery): string {
    $params = ['page' => $page];

    if ($courseSearchQuery !== '') {
        $params['q'] = $courseSearchQuery;
    }

    return 'course.php?' . http_build_query($params);
};

$courseId = (int) ($_GET['id'] ?? 0);
$currentCourse = $courseId > 0 ? get_course_by_id($courseId, $pdo, true) : null;

if ($currentCourse && !empty($currentCourse['end_at'])) {
    $endTime = DateTimeImmutable::createFromFormat(
        'Y-m-d H:i:s',
        (string) $currentCourse['end_at'],
        $appTimezone
    );

    if ($endTime instanceof DateTimeImmutable && $endTime < $now) {
        $currentCourse = null;
        $courseId = 0;
    }
}

$meetings = [];
$accessError = '';
$accessSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'mark_meeting_completed') {
    verify_csrf_token();
    if (!user_logged_in()) {
        set_user_flash('danger', 'Silakan login terlebih dahulu.');
        redirect('user_login.php');
    }
    $postedCourseId = (int) ($_POST['course_id'] ?? 0);
    $meetingId = (int) ($_POST['meeting_id'] ?? 0);
    if ($postedCourseId > 0 && user_has_course_enrollment(current_user_id(), $postedCourseId, $pdo)) {
        mark_course_meeting_completed(current_user_id(), $meetingId, $pdo);
        set_user_flash('success', 'Progress pertemuan berhasil ditandai selesai.');
        redirect('course.php?id=' . $postedCourseId);
    }
    set_user_flash('danger', 'Kamu belum memiliki akses ke kelas ini.');
    redirect('course.php?id=' . $postedCourseId);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'submit_quiz') {
    verify_csrf_token();
    if (!user_logged_in()) {
        set_user_flash('danger', 'Silakan login terlebih dahulu.');
        redirect('user_login.php');
    }
    $postedCourseId = (int) ($_POST['course_id'] ?? 0);
    if ($postedCourseId > 0 && user_has_course_enrollment(current_user_id(), $postedCourseId, $pdo)) {
        $result = submit_quiz_attempt(current_user_id(), (int) ($_POST['quiz_id'] ?? 0), (string) ($_POST['selected_option'] ?? ''), $pdo);
        set_user_flash($result['ok'] ? ($result['is_correct'] ? 'success' : 'warning') : 'danger', $result['message']);
        redirect('course.php?id=' . $postedCourseId);
    }
    set_user_flash('danger', 'Kamu belum memiliki akses ke kelas ini.');
    redirect('course.php?id=' . $postedCourseId);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'submit_assignment') {
    verify_csrf_token();
    if (!user_logged_in()) {
        set_user_flash('danger', 'Silakan login terlebih dahulu.');
        redirect('user_login.php');
    }
    $postedCourseId = (int) ($_POST['course_id'] ?? 0);
    $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
    $submissionType = (string) ($_POST['submission_type'] ?? 'drive_link');
    $driveUrl = (string) ($_POST['drive_url'] ?? '');
    $studentNotes = (string) ($_POST['student_notes'] ?? '');
    $uploadedFile = $_FILES['assignment_file'] ?? [];

    if ($postedCourseId > 0 && user_has_course_enrollment(current_user_id(), $postedCourseId, $pdo)) {
        $result = submit_user_assignment(
            current_user_id(),
            $assignmentId,
            $submissionType,
            $driveUrl,
            $uploadedFile,
            $studentNotes,
            $pdo
        );
        set_user_flash($result['ok'] ? 'success' : 'danger', $result['message']);
        redirect('course.php?id=' . $postedCourseId . '#assignment_' . $assignmentId);
    }
    set_user_flash('danger', 'Kamu belum memiliki akses ke kelas ini.');
    redirect('course.php?id=' . $postedCourseId);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'course_enrollment') {
    verify_csrf_token();
    $postedCourseId = (int) ($_POST['course_id'] ?? 0);
    $currentCourse = get_course_by_id($postedCourseId, $pdo, true);
    $courseId = $postedCourseId;

    if ($currentCourse && !empty($currentCourse['end_at'])) {
        $endTime = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            (string) $currentCourse['end_at'],
            $appTimezone
        );

        if ($endTime instanceof DateTimeImmutable && $endTime < $now) {
            set_user_flash('danger', 'Kelas sudah berakhir dan tidak bisa diakses lagi.');
            redirect('course.php');
        }
    }
    if (!user_logged_in()) {
        set_user_flash('danger', 'Silakan login user terlebih dahulu, lalu masukkan token enrollment.');
        redirect('user_login.php');
    }
    $result = enroll_user_with_token(current_user_id(), (string) ($_POST['enrollment_token'] ?? ''), $postedCourseId, $pdo);
    if ($result['ok']) {
        set_user_flash('success', $result['message']);
        redirect('course.php?id=' . $postedCourseId);
    }
    $accessError = $result['message'];
}
$courseStatus = $currentCourse ? course_availability_status($currentCourse) : null;

if ($currentCourse && $courseStatus) {
    $startTime = null;
    $endTime = null;

    if (!empty($currentCourse['start_at'])) {
        $startTime = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            (string) $currentCourse['start_at'],
            $appTimezone
        );
    }

    if (!empty($currentCourse['end_at'])) {
        $endTime = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            (string) $currentCourse['end_at'],
            $appTimezone
        );
    }

    if ($startTime instanceof DateTimeImmutable && $startTime > $now) {
        $courseStatus = [
            'is_open' => false,
            'code' => 'upcoming',
            'label' => 'Belum mulai',
            'message' => 'Kelas ini baru bisa diakses mulai ' . format_course_datetime((string) $currentCourse['start_at']) . '.',
        ];
    } elseif ($endTime instanceof DateTimeImmutable && $endTime < $now) {
        $courseStatus = [
            'is_open' => false,
            'code' => 'ended',
            'label' => 'Inaktif',
            'message' => 'Masa akses kelas ini sudah berakhir.',
        ];
    } elseif ((int) ($currentCourse['is_active'] ?? 0) !== 1) {
        $courseStatus = [
            'is_open' => false,
            'code' => 'inactive',
            'label' => 'Inaktif',
            'message' => 'Kelas ini sedang tidak aktif.',
        ];
    } else {
        $courseStatus = [
            'is_open' => true,
            'code' => 'open',
            'label' => 'Aktif',
            'message' => '',
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'download_material') {
    verify_csrf_token();

    $postedCourseId = (int) ($_POST['course_id'] ?? 0);
    $meetingId = (int) ($_POST['meeting_id'] ?? 0);
    $redirectTarget = $postedCourseId > 0 ? 'course.php?id=' . $postedCourseId : 'course.php';

    if (!user_logged_in()) {
        set_user_flash('danger', 'Silakan login terlebih dahulu.');
        redirect('user_login.php');
    }

    $downloadCourse = $postedCourseId > 0 ? get_course_by_id($postedCourseId, $pdo, true) : null;
    $downloadStatus = $downloadCourse ? course_availability_status($downloadCourse) : null;

    if (!$downloadCourse || !$downloadStatus || !$downloadStatus['is_open']) {
        set_user_flash('danger', 'Kelas ini sedang tidak bisa diakses.');
        redirect($redirectTarget);
    }

    if (!user_has_course_enrollment(current_user_id(), $postedCourseId, $pdo)) {
        set_user_flash('danger', 'Kamu belum memiliki akses ke kelas ini.');
        redirect($redirectTarget);
    }

    $downloadMeeting = null;
    foreach (get_course_meetings($postedCourseId, $pdo, true) as $meeting) {
        if ((int) $meeting['id'] === $meetingId) {
            $downloadMeeting = $meeting;
            break;
        }
    }

    if (!$downloadMeeting) {
        set_user_flash('danger', 'Materi tidak ditemukan.');
        redirect($redirectTarget);
    }

    $materialPath = trim((string) ($downloadMeeting['material_file_path'] ?? ''));
    $filePath = course_material_storage_path($materialPath);

    if ($filePath === null) {
        set_user_flash('danger', 'File materi tidak tersedia.');
        redirect($redirectTarget);
    }

    $downloadName = course_material_download_name($materialPath, (string) ($downloadMeeting['material_file_name'] ?? ''));
    course_stream_material_file($filePath, $downloadName);
}

if (isset($_GET['download_submission'])) {
    if (!user_logged_in()) {
        set_user_flash('danger', 'Silakan login terlebih dahulu.');
        redirect('user_login.php');
    }
    $subId = (int) $_GET['download_submission'];
    $st = $pdo->prepare('SELECT * FROM course_assignment_submissions WHERE id = ?');
    $st->execute([$subId]);
    $sub = $st->fetch();
    if ($sub && ((int) $sub['user_id'] === current_user_id() || admin_logged_in())) {
        $storagePath = assignment_submission_storage_path($sub['file_path']);
        if ($storagePath && is_file($storagePath)) {
            $dlName = !empty($sub['file_name']) ? $sub['file_name'] : basename($storagePath);
            course_stream_material_file($storagePath, $dlName);
        }
    }
    set_user_flash('danger', 'File tugas tidak ditemukan.');
    redirect('course.php' . ($courseId > 0 ? '?id=' . $courseId : ''));
}

$hasEnrollment = $currentCourse && user_logged_in() && user_has_course_enrollment(current_user_id(), (int) $currentCourse['id'], $pdo);
$hasAccess = $currentCourse && $courseStatus && $courseStatus['is_open'] && $hasEnrollment;
if ($currentCourse && $hasAccess) {
    log_course_access(current_user_id(), (int) $currentCourse['id'], $pdo);
    $meetings = get_course_meetings((int) $currentCourse['id'], $pdo, true);
}
$progressSummary = ($currentCourse && $hasAccess) ? get_course_progress_summary(current_user_id(), (int) $currentCourse['id'], $pdo) : ['total' => 0, 'completed' => 0, 'percent' => 0];
$completedMeetingIds = ($currentCourse && $hasAccess) ? get_completed_meeting_ids(current_user_id(), (int) $currentCourse['id'], $pdo) : [];
$quizzesByMeeting = ($currentCourse && $hasAccess && $meetings) ? get_quizzes_by_meeting_ids(array_map(static fn(array $m): int => (int) $m['id'], $meetings), $pdo) : [];
$allQuizIds = [];
foreach ($quizzesByMeeting as $meetingQuizzes) { foreach ($meetingQuizzes as $quiz) { $allQuizIds[] = (int) $quiz['id']; } }
$latestQuizAttempts = ($currentCourse && $hasAccess) ? get_latest_quiz_attempts(current_user_id(), $allQuizIds, $pdo) : [];

$assignmentsByMeeting = ($currentCourse && $hasAccess && $meetings) ? get_assignments_by_meeting_ids(array_map(static fn(array $m): int => (int) $m['id'], $meetings), $pdo, true) : [];
$allAssignmentIds = [];
foreach ($assignmentsByMeeting as $meetingAssignments) {
    foreach ($meetingAssignments as $a) {
        $allAssignmentIds[] = (int) $a['id'];
    }
}
$userAssignmentSubmissions = ($currentCourse && $hasAccess && !empty($allAssignmentIds)) ? get_user_assignment_submissions(current_user_id(), $allAssignmentIds, $pdo) : [];
$courseGradeSummary = ($currentCourse && $hasAccess) ? calculate_course_grade_summary((int) $currentCourse['id'], current_user_id(), $pdo) : ['total_assignments' => 0, 'weight_per_assignment' => 0, 'submitted_count' => 0, 'graded_count' => 0, 'final_score' => 0, 'assignments' => []];

$flash = get_user_flash();
$pageTitle = $currentCourse ? $currentCourse['title'] . ' - Course' : ($settings['course_page_title'] ?? 'Course');
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="icon" href="images/favicon/logo2.png" type="image/png" />
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/unicons.css">
    <link rel="stylesheet" href="css/owl.carousel.min.css">
    <link rel="stylesheet" href="css/owl.theme.default.min.css">
    <link rel="stylesheet" href="css/tooplate-style.css?v=20261004-footer-v2">
</head>

<body>
    <nav class="navbar navbar-expand-sm navbar-light">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="uil uil-user"></i> <?= e($settings['site_brand'] ?? 'Marvel') ?></a>

            <div class="navbar-mobile-actions">
                <div class="mobile-lang-switch">
                    <a href="<?= e(lang_url('id')) ?>" class="mobile-lang-btn <?= current_lang() === 'id' ? 'active' : '' ?>">ID</a>
                    <a href="<?= e(lang_url('en')) ?>" class="mobile-lang-btn <?= current_lang() === 'en' ? 'active' : '' ?>">EN</a>
                </div>
                <button class="mobile-color-mode color-mode-toggle" type="button" aria-label="Ganti dark mode" aria-pressed="false" title="Ganti dark mode">
                    <i class="color-mode-icon"></i>
                </button>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                    <span class="navbar-toggler-icon"></span>
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a href="index.php#about" class="nav-link"><span data-hover="<?= e(__t('nav_about')) ?>"><?= e(__t('nav_about')) ?></span></a></li>
                    <li class="nav-item"><a href="index.php#project" class="nav-link"><span data-hover="<?= e(__t('nav_projects')) ?>"><?= e(__t('nav_projects')) ?></span></a></li>
                    <li class="nav-item"><a href="index.php#resume" class="nav-link"><span data-hover="<?= e(__t('nav_resume')) ?>"><?= e(__t('nav_resume')) ?></span></a></li>
                    <li class="nav-item"><a href="index.php#contact" class="nav-link"><span data-hover="<?= e(__t('nav_contact')) ?>"><?= e(__t('nav_contact')) ?></span></a></li>
                    <li class="nav-item"><a href="berita.php" class="nav-link"><span data-hover="<?= e(__t('nav_news')) ?>"><?= e(__t('nav_news')) ?></span></a></li>
                    <li class="nav-item dropdown active">
                        <a href="#" class="nav-link dropdown-toggle" id="courseDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <span data-hover="<?= e(__t('nav_course')) ?>"><?= e(__t('nav_course')) ?></span>
                        </a>
                        <div class="dropdown-menu navbar-course-dropdown" aria-labelledby="courseDropdown">
                            <a class="dropdown-item" href="course.php"><i class="uil uil-book-open"></i> <?= e(__t('nav_free')) ?></a>
                            <a class="dropdown-item" href="https://lms.rksolusindo.com" target="_blank" rel="noopener noreferrer"><i class="uil uil-star"></i> <?= e(__t('nav_premium')) ?></a>
                            <a class="dropdown-item" href="cv-generator.php"><i class="uil uil-file-alt"></i> <?= e(__t('nav_cv_generator')) ?></a>
                        </div>
                    </li>
                    <?php if (user_logged_in()): ?>
                    <li class="nav-item"><a href="my_courses.php" class="nav-link"><span data-hover="<?= e(__t('nav_my_courses')) ?>"><?= e(__t('nav_my_courses')) ?></span></a></li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav ml-lg-auto align-items-center flex-row">
                    <li class="mr-2 d-none d-lg-block desktop-lang-item">
                      <div class="lang-switch-wrap">
                        <a href="<?= e(lang_url('id')) ?>" class="lang-btn <?= current_lang() === 'id' ? 'active' : '' ?>" title="Bahasa Indonesia">
                          <span>🇮🇩 ID</span>
                        </a>
                        <span class="lang-separator">/</span>
                        <a href="<?= e(lang_url('en')) ?>" class="lang-btn <?= current_lang() === 'en' ? 'active' : '' ?>" title="English">
                          <span>🇬🇧 EN</span>
                        </a>
                      </div>
                    </li>
                    <li>
                        <div class="color-mode color-mode-toggle d-lg-flex justify-content-center align-items-center" role="button" tabindex="0" aria-label="Ganti dark mode" aria-pressed="false">
                            <i class="color-mode-icon"></i> <?= e(__t('nav_color_mode')) ?>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <main class="course-page"><?php if ($flash): ?><div class="container pt-4">
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        </div><?php endif; ?>
        <?php if ($currentCourse): ?><section class="course-detail py-5">
            <div class="container"><a class="news-back-link" href="course.php"><i class="uil uil-angle-left"></i>
                    Kembali ke daftar course</a>
                <div class="row align-items-center mb-5">
                    <div class="col-lg-5 col-12 mb-4 mb-lg-0"><img class="course-detail-image"
                            src="<?= e($currentCourse['thumbnail_path']) ?>" alt="<?= e($currentCourse['title']) ?>">
                    </div>
                    <div class="col-lg-7 col-12"><small class="small-text">Course</small>
                        <h1><?= e($currentCourse['title']) ?></h1>
                        <p><?= nl2br(e($currentCourse['description'] ?? '')) ?></p>
                        <?php if (!empty($currentCourse['start_at']) || !empty($currentCourse['end_at'])): ?><p
                            class="course-schedule"><i class="uil uil-calendar-alt"></i>
                            <?= !empty($currentCourse['start_at']) ? e(format_course_datetime($currentCourse['start_at'])) : 'Sekarang' ?>
                            -
                            <?= !empty($currentCourse['end_at']) ? e(format_course_datetime($currentCourse['end_at'])) : 'Tanpa batas akhir' ?>
                        </p><?php endif; ?><?php if ($courseStatus && !$courseStatus['is_open']): ?><div
                            class="alert alert-warning"><?= e($courseStatus['message']) ?></div>
                        <?php endif; ?><?php if ($hasEnrollment): ?><div class="alert alert-success"><i
                                class="uil uil-check-circle"></i> Kamu sudah terdaftar di kelas ini.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!$hasAccess): ?><div class="row justify-content-center">
                    <div class="col-lg-6 col-12">
                        <div class="course-access-card">
                            <h2><i class="uil uil-lock"></i> Akses Kelas</h2><?php if (!user_logged_in()): ?><p>Untuk
                                membuka kelas, peserta harus login user terlebih dahulu. Login ini terpisah dari admin.
                            </p><a class="btn custom-btn custom-btn-bg custom-btn-link" href="user_login.php">Login
                                User</a> <a class="btn btn-outline-secondary"
                                href="user_register.php">Daftar</a><?php else: ?><p>Masukkan token enrollment dari
                                admin. Token bisa membuka satu atau banyak course sesuai pengaturan admin.</p>
                            <?php if ($accessError): ?><div class="alert alert-danger"><?= e($accessError) ?></div>
                            <?php endif; ?><form method="post"
                                action="course.php?id=<?= e((string) $currentCourse['id']) ?>"><input type="hidden"
                                    name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden"
                                    name="form_type" value="course_enrollment"><input type="hidden" name="course_id"
                                    value="<?= e((string) $currentCourse['id']) ?>">
                                <div class="form-group"><input type="text" class="form-control" name="enrollment_token"
                                        placeholder="Masukkan token enrollment"
                                        <?= ($courseStatus && !$courseStatus['is_open']) ? 'disabled' : 'required' ?>>
                                </div><button type="submit" class="btn custom-btn custom-btn-bg custom-btn-link"
                                    <?= ($courseStatus && !$courseStatus['is_open']) ? 'disabled' : '' ?>>Enroll & Buka
                                    Kelas</button>
                            </form>
                            <p class="mt-3 mb-0"><a href="my_courses.php">Lihat Kelas Saya</a></p><?php endif; ?>
                        </div>
                    </div>
                </div><?php else: ?><section class="course-meetings">
                    <div class="course-meeting-card mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>Progress Belajar</strong>
                            <span><?= e((string) $progressSummary['completed']) ?> / <?= e((string) $progressSummary['total']) ?> pertemuan selesai</span>
                        </div>
                        <div class="progress" style="height: 12px;">
                            <div class="progress-bar" role="progressbar" style="width: <?= e((string) $progressSummary['percent']) ?>%;" aria-valuenow="<?= e((string) $progressSummary['percent']) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <small class="text-muted"><?= e((string) $progressSummary['percent']) ?>% selesai</small>
                    </div>
                    <?php if (($courseGradeSummary['total_assignments'] ?? 0) > 0): ?>
                    <div class="course-meeting-card mb-4 assignment-summary-banner" style="border-left: 4px solid #ffc200;">
                        <div class="row align-items-center">
                            <div class="col-md-7 mb-3 mb-md-0">
                                <h3 class="h5 mb-1 font-weight-bold"><i class="uil uil-award text-warning"></i> Rekapitulasi Nilai Tugas</h3>
                                <p class="text-muted small mb-0">
                                    Total <?= e((string) $courseGradeSummary['total_assignments']) ?> tugas pertemuan (Bobot nilai: <?= e((string) $courseGradeSummary['weight_per_assignment']) ?>% per tugas).
                                    <br><?= e((string) $courseGradeSummary['submitted_count']) ?> dari <?= e((string) $courseGradeSummary['total_assignments']) ?> tugas dikumpulkan &bull; <?= e((string) $courseGradeSummary['graded_count']) ?> sudah dinilai dosen/admin.
                                </p>
                            </div>
                            <div class="col-md-5 text-left text-md-right mt-2 mt-md-0">
                                <span class="small text-muted d-block font-weight-bold">Total Nilai Akhir Tugas:</span>
                                <strong class="assignment-score-display">
                                    <?= number_format((float) $courseGradeSummary['final_score'], 1) ?>% <span class="small" style="font-size: 14px; opacity: 0.85;">/ 100%</span>
                                </strong>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <h2 class="mb-4">Materi Pertemuan</h2><?php if (!$meetings): ?><div class="text-center py-5">
                        <h3>Belum ada pertemuan</h3>
                        <p>Admin belum menambahkan materi untuk kelas ini.</p>
                    </div><?php else: ?><?php foreach ($meetings as $meeting): ?><?php
                        $embedUrl = youtube_embed_url((string) ($meeting['youtube_url'] ?? ''));
                        $materialPath = trim((string) ($meeting['material_file_path'] ?? ''));
                        $materialName = trim((string) ($meeting['material_file_name'] ?? ''));
                        $materialLabel = $materialName !== '' ? $materialName : ($materialPath !== '' ? basename($materialPath) : 'Materi Pertemuan');
                        $meetingId = (int) $meeting['id'];
                        $isCompleted = in_array($meetingId, $completedMeetingIds, true);
                        $meetingQuizzes = $quizzesByMeeting[$meetingId] ?? [];
                    ?><article class="course-meeting-card">
                        <div class="row align-items-center">
                            <div class="col-lg-7 col-12 mb-3 mb-lg-0"><span class="course-meeting-order">Pertemuan
                                    <?= e((string) $meeting['sort_order']) ?></span>
                                <h3><?= e($meeting['title']) ?></h3>
                                <p><?= nl2br(e($meeting['description'] ?? '')) ?></p>
                                <?php if ($materialPath !== ''): ?>
                                <div class="mt-3 d-flex flex-wrap align-items-center">
                                    <form method="post" action="course.php?id=<?= e((string) $currentCourse['id']) ?>" class="mr-2 mb-2">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="form_type" value="download_material">
                                        <input type="hidden" name="course_id" value="<?= e((string) $currentCourse['id']) ?>">
                                        <input type="hidden" name="meeting_id" value="<?= e((string) $meetingId) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                        <i class="uil uil-file-download"></i> Download Materi
                                        </button>
                                    </form>
                                    
                                </div>
                                <small class="text-muted d-block"><?= e($materialLabel) ?></small>
                                <?php endif; ?>
                                <form method="post" action="course.php?id=<?= e((string) $currentCourse['id']) ?>" class="mt-3">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="form_type" value="mark_meeting_completed">
                                    <input type="hidden" name="course_id" value="<?= e((string) $currentCourse['id']) ?>">
                                    <input type="hidden" name="meeting_id" value="<?= e((string) $meetingId) ?>">
                                    <button type="submit" class="btn btn-sm <?= $isCompleted ? 'btn-success' : 'btn-outline-success' ?>" <?= $isCompleted ? 'disabled' : '' ?>>
                                        <i class="uil uil-check-circle"></i> <?= $isCompleted ? 'Sudah selesai' : 'Tandai selesai' ?>
                                    </button>
                                </form>
                            </div>
                            <div class="col-lg-5 col-12">
                                <?php if ($embedUrl): ?>
                                <div class="course-video-wrapper"><iframe src="<?= e($embedUrl) ?>"
                                        title="<?= e($meeting['title']) ?>" allowfullscreen></iframe></div>
                                <?php endif; ?></div>
                        </div>
                        <?php if ($meetingQuizzes): ?>
                        <div class="mt-4">
                            <h4 class="h5"><i class="uil uil-question-circle"></i> Quiz Pertemuan</h4>
                            <?php foreach ($meetingQuizzes as $quiz): ?>
                                <?php $attempt = $latestQuizAttempts[(int) $quiz['id']] ?? null; ?>
                                <div class="card card-body mb-3">
                                    <strong><?= e($quiz['question']) ?></strong>
                                    <?php if ($attempt): ?>
                                        <div class="alert alert-<?= ((int) $attempt['is_correct'] === 1) ? 'success' : 'warning' ?> mt-2 mb-2">Jawaban terakhir: <?= e($attempt['selected_option']) ?> — <?= ((int) $attempt['is_correct'] === 1) ? 'Benar' : 'Belum tepat' ?></div>
                                    <?php endif; ?>
                                    <form method="post" action="course.php?id=<?= e((string) $currentCourse['id']) ?>" class="mt-2">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="form_type" value="submit_quiz">
                                        <input type="hidden" name="course_id" value="<?= e((string) $currentCourse['id']) ?>">
                                        <input type="hidden" name="quiz_id" value="<?= e((string) $quiz['id']) ?>">
                                        <?php foreach (['A','B','C','D'] as $option): $field = 'option_' . strtolower($option); ?>
                                            <div class="custom-control custom-radio">
                                                <input class="custom-control-input" type="radio" name="selected_option" id="quiz_<?= e((string) $quiz['id']) ?>_<?= e($option) ?>" value="<?= e($option) ?>" required>
                                                <label class="custom-control-label" for="quiz_<?= e((string) $quiz['id']) ?>_<?= e($option) ?>"><?= e($option) ?>. <?= e($quiz[$field] ?? '') ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                        <button type="submit" class="btn btn-sm btn-warning font-weight-bold mt-2">Submit Jawaban</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php
                            $meetingAssignments = $assignmentsByMeeting[$meetingId] ?? [];
                        ?>
                        <?php if ($meetingAssignments): ?>
                        <div class="mt-4 pt-3 border-top assignment-meeting-section">
                            <h4 class="h5 font-weight-bold text-warning mb-3">
                                <i class="uil uil-clipboard-notes"></i> Tugas Pertemuan
                            </h4>
                            <?php foreach ($meetingAssignments as $assignment): ?>
                                <?php
                                    $aId = (int) $assignment['id'];
                                    $submission = $userAssignmentSubmissions[$aId] ?? null;
                                    $isSubmitted = !empty($submission);
                                    $subStatus = $submission['status'] ?? ($isSubmitted && $submission['score'] !== null ? 'graded' : ($isSubmitted ? 'submitted' : 'none'));
                                    $isGraded = ($isSubmitted && $subStatus === 'graded' && $submission['score'] !== null);
                                    $isRevision = ($isSubmitted && $subStatus === 'revision');
                                    $canEdit = (!$isSubmitted || $subStatus === 'submitted' || $isRevision);
                                    $weight = $courseGradeSummary['weight_per_assignment'] ?? 0;
                                ?>
                                <div class="course-assignment-card mb-4" id="assignment_<?= $aId ?>">
                                    <div class="course-assignment-header d-flex flex-wrap justify-content-between align-items-start align-items-md-center">
                                        <div class="mb-2 mb-md-0 mr-md-3">
                                            <h5 class="course-assignment-title mb-1 font-weight-bold"><?= e($assignment['title']) ?></h5>
                                            <div class="course-assignment-meta d-flex flex-wrap align-items-center">
                                                <span class="badge badge-warning text-dark font-weight-bold mr-2 mb-1">Bobot Nilai: <?= $weight ?>%</span>
                                                <?php if (!empty($assignment['due_date'])): ?>
                                                    <span class="assignment-due-date mb-1">
                                                        <i class="uil uil-calendar-alt text-danger mr-1"></i> Batas Pengumpulan: <strong><?= date('d M Y, H:i', strtotime($assignment['due_date'])) ?> WIB</strong>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="mt-2 mt-md-0">
                                            <?php if ($isGraded): ?>
                                                <span class="badge badge-success px-3 py-2 font-weight-bold assignment-status-badge">
                                                    <i class="uil uil-award mr-1"></i> Nilai: <?= number_format((float) $submission['score'], 1) ?> / 100 (Final)
                                                </span>
                                            <?php elseif ($isRevision): ?>
                                                <span class="badge badge-danger px-3 py-2 font-weight-bold assignment-status-badge">
                                                    <i class="uil uil-redo mr-1"></i> Perlu Revisi
                                                </span>
                                            <?php elseif ($isSubmitted): ?>
                                                <span class="badge badge-warning px-3 py-2 font-weight-bold assignment-status-badge">
                                                    <i class="uil uil-clock mr-1"></i> Menunggu Penilaian
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary px-3 py-2 font-weight-bold assignment-status-badge">
                                                    <i class="uil uil-clock mr-1"></i> Belum Mengumpulkan
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="course-assignment-body">
                                        <?php if (!empty($assignment['description'])): ?>
                                            <div class="assignment-instructions-box mb-3">
                                                <span class="small font-weight-bold text-uppercase d-block mb-1 assignment-box-label">
                                                    <i class="uil uil-info-circle mr-1"></i> Petunjuk Tugas:
                                                </span>
                                                <div class="assignment-instructions-text"><?= nl2br(e($assignment['description'])) ?></div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($isGraded): ?>
                                            <!-- Box Nilai Final (Selesai Dinilai Admin) -->
                                            <div class="assignment-grade-box graded mb-3">
                                                <div class="d-flex flex-wrap justify-content-between align-items-center">
                                                    <div>
                                                        <h5 class="assignment-grade-heading mb-1 font-weight-bold">
                                                            <i class="uil uil-check-circle text-success mr-1"></i> Nilai Tugas: <?= number_format((float) $submission['score'], 1) ?> / 100
                                                        </h5>
                                                        <p class="mb-0 small assignment-grade-sub">
                                                            Kontribusi ke Nilai Akhir: <strong>+<?= number_format((float) $submission['score'] * ($weight / 100.0), 2) ?>%</strong> (dari total bobot <?= $weight ?>%).
                                                        </p>
                                                    </div>
                                                </div>
                                                <?php if (!empty($submission['feedback'])): ?>
                                                    <hr class="my-2 assignment-divider">
                                                    <div class="small">
                                                        <strong class="assignment-box-label">Catatan / Feedback dari Admin/Dosen:</strong>
                                                        <div class="assignment-feedback-text font-italic mt-1"><?= nl2br(e($submission['feedback'])) ?></div>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="small text-muted mt-2 pt-2 border-top">
                                                    <i class="uil uil-lock mr-1 text-success"></i> <strong>Tugas Selesai & Terkunci:</strong> Tugas ini telah dinilai secara final oleh dosen/admin dan tidak dapat diubah lagi.
                                                </div>
                                            </div>
                                        <?php elseif ($isRevision): ?>
                                            <!-- Box Permintaan Revisi -->
                                            <div class="assignment-grade-box revision mb-3">
                                                <div class="d-flex align-items-center mb-1">
                                                    <h5 class="assignment-revision-heading mb-0 font-weight-bold">
                                                        <i class="uil uil-exclamation-triangle text-danger mr-1"></i> Tugas Memerlukan Revisi
                                                    </h5>
                                                </div>
                                                <p class="mb-0 small">
                                                    Dosen/Admin meminta Anda untuk memperbaiki tugas ini. Silakan periksa catatan revisi di bawah dan kirimkan kembali berkas atau tautan tugas yang telah diperbaiki.
                                                </p>
                                                <?php if (!empty($submission['feedback'])): ?>
                                                    <hr class="my-2 assignment-divider">
                                                    <div class="small">
                                                        <strong class="text-danger"><i class="uil uil-comment-alt-edit mr-1"></i> Catatan Revisi dari Dosen/Admin:</strong>
                                                        <div class="assignment-revision-feedback font-italic mt-1 p-2 rounded"><?= nl2br(e($submission['feedback'])) ?></div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif ($isSubmitted): ?>
                                            <!-- Box Menunggu Penilaian -->
                                            <div class="assignment-grade-box waiting mb-3">
                                                <i class="uil uil-clock mr-1"></i> <strong>Menunggu Penilaian</strong> — Tugas Anda telah berhasil dikumpulkan dan sedang menunggu penilaian dari dosen/admin. Selama belum dinilai, Anda masih dapat mengedit atau mengirim ulang tugas jika diperlukan.
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($isSubmitted): ?>
                                            <!-- Rincian Tugas yang Sudah Dikumpulkan -->
                                            <div class="assignment-detail-box mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
                                                    <span class="small font-weight-bold text-uppercase assignment-box-label">Berkas / Link yang Dikumpulkan:</span>
                                                    <small class="assignment-submitted-time"><i class="uil uil-clock mr-1"></i> <?= date('d M Y, H:i', strtotime($submission['submitted_at'])) ?> WIB</small>
                                                </div>
                                                <?php if ($submission['submission_type'] === 'drive_link'): ?>
                                                    <div class="d-flex align-items-center flex-wrap">
                                                        <a href="<?= e($submission['drive_url'] ?? '#') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary font-weight-bold mr-2 mb-2">
                                                            <i class="uil uil-external-link-alt mr-1"></i> Buka Link Drive / Tugas
                                                        </a>
                                                        <span class="small assignment-url-preview mb-2"><?= e($submission['drive_url'] ?? '') ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="d-flex align-items-center flex-wrap">
                                                        <a href="course.php?id=<?= e((string) $currentCourse['id']) ?>&download_submission=<?= (int) $submission['id'] ?>" class="btn btn-sm btn-outline-info font-weight-bold mr-2 mb-2">
                                                            <i class="uil uil-file-download mr-1"></i> Unduh Berkas Tugas (<?= e($submission['file_name'] ?? 'File') ?>)
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($submission['student_notes'])): ?>
                                                    <div class="mt-2 pt-2 assignment-notes-box small">
                                                        <strong class="assignment-box-label">Catatan Anda:</strong>
                                                        <div class="assignment-notes-text mt-1"><?= nl2br(e($submission['student_notes'])) ?></div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($canEdit): ?>
                                            <!-- Form Pengumpulan / Revisi Tugas -->
                                            <div class="<?= ($isSubmitted && !$isRevision) ? 'collapse mt-3' : 'mt-3' ?>" id="form_sub_<?= $aId ?>">
                                                <div class="assignment-form-box">
                                                    <h6 class="font-weight-bold mb-3 assignment-form-title">
                                                        <i class="uil <?= $isRevision ? 'uil-redo text-danger' : ($isSubmitted ? 'uil-edit text-warning' : 'uil-cloud-upload text-warning') ?> mr-1"></i>
                                                        <?= $isRevision ? 'Form Pengumpulan Revisi Tugas' : ($isSubmitted ? 'Perbarui / Kirim Ulang Tugas' : 'Form Pengumpulan Tugas') ?>
                                                    </h6>
                                                    <form method="post" action="course.php?id=<?= e((string) $currentCourse['id']) ?>" enctype="multipart/form-data">
                                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                        <input type="hidden" name="form_type" value="submit_assignment">
                                                        <input type="hidden" name="course_id" value="<?= e((string) $currentCourse['id']) ?>">
                                                        <input type="hidden" name="assignment_id" value="<?= $aId ?>">

                                                        <!-- Pilihan Opsi Pengumpulan -->
                                                        <div class="form-group mb-3">
                                                            <label class="font-weight-bold small d-block mb-2 assignment-box-label">Pilih Opsi Pengumpulan Tugas:</label>
                                                            <div class="row assignment-option-cards">
                                                                <div class="col-12 col-sm-6 mb-2">
                                                                    <label class="assignment-type-card <?= (!$isSubmitted || ($submission['submission_type'] ?? '') === 'drive_link') ? 'active' : '' ?>" for="type_drive_<?= $aId ?>" id="label_drive_<?= $aId ?>">
                                                                        <input type="radio" id="type_drive_<?= $aId ?>" name="submission_type" value="drive_link" <?= (!$isSubmitted || ($submission['submission_type'] ?? '') === 'drive_link') ? 'checked' : '' ?> onchange="toggleSubmissionType(<?= $aId ?>, 'drive')">
                                                                        <div class="d-flex align-items-center">
                                                                            <div class="assignment-type-icon mr-2">
                                                                                <i class="uil uil-link"></i>
                                                                            </div>
                                                                            <div>
                                                                                <div class="assignment-type-heading">Kirim Link URL / Drive</div>
                                                                                <small class="assignment-type-sub">Tautan Google Drive atau Git</small>
                                                                            </div>
                                                                        </div>
                                                                    </label>
                                                                </div>
                                                                <div class="col-12 col-sm-6 mb-2">
                                                                    <label class="assignment-type-card <?= ($isSubmitted && ($submission['submission_type'] ?? '') === 'file_upload') ? 'active' : '' ?>" for="type_file_<?= $aId ?>" id="label_file_<?= $aId ?>">
                                                                        <input type="radio" id="type_file_<?= $aId ?>" name="submission_type" value="file_upload" <?= ($isSubmitted && ($submission['submission_type'] ?? '') === 'file_upload') ? 'checked' : '' ?> onchange="toggleSubmissionType(<?= $aId ?>, 'file')">
                                                                        <div class="d-flex align-items-center">
                                                                            <div class="assignment-type-icon mr-2">
                                                                                <i class="uil uil-upload-alt"></i>
                                                                            </div>
                                                                            <div>
                                                                                <div class="assignment-type-heading">Upload File Dokumen</div>
                                                                                <small class="assignment-type-sub">PDF, Word, ZIP, Gambar</small>
                                                                            </div>
                                                                        </div>
                                                                    </label>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Input Google Drive Link -->
                                                        <div class="form-group" id="group_drive_<?= $aId ?>" style="<?= ($isSubmitted && ($submission['submission_type'] ?? '') === 'file_upload') ? 'display: none;' : '' ?>">
                                                            <label class="small font-weight-bold assignment-input-label" for="drive_url_<?= $aId ?>">URL Link Google Drive / Repositori:</label>
                                                            <input type="url" class="form-control assignment-input" id="drive_url_<?= $aId ?>" name="drive_url" placeholder="https://drive.google.com/..." value="<?= e($submission['drive_url'] ?? '') ?>">
                                                            <small class="form-text assignment-input-help">Pastikan izin tautan Google Drive sudah diatur agar <strong>siapa saja yang memiliki link dapat melihat</strong>.</small>
                                                        </div>

                                                        <!-- Input Upload File -->
                                                        <div class="form-group" id="group_file_<?= $aId ?>" style="<?= (!$isSubmitted || ($submission['submission_type'] ?? '') === 'drive_link') ? 'display: none;' : '' ?>">
                                                            <label class="small font-weight-bold assignment-input-label" for="file_<?= $aId ?>">Pilih File Tugas:</label>
                                                            <input type="file" class="form-control-file assignment-file-input" id="file_<?= $aId ?>" name="assignment_file" accept=".pdf,.zip,.rar,.7z,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.jpg,.jpeg,.png">
                                                            <small class="form-text assignment-input-help">Format yang didukung: PDF, ZIP, RAR, Word, Excel, PPT, Gambar. Maksimal 25MB. <?= $isSubmitted && !empty($submission['file_name']) ? '(File saat ini: ' . e($submission['file_name']) . ')' : '' ?></small>
                                                        </div>

                                                        <!-- Catatan Tambahan Siswa -->
                                                        <div class="form-group mb-3">
                                                            <label class="small font-weight-bold assignment-input-label" for="notes_<?= $aId ?>">Catatan / Keterangan Tambahan (Opsional):</label>
                                                            <textarea class="form-control assignment-textarea" id="notes_<?= $aId ?>" name="student_notes" rows="2" placeholder="<?= $isRevision ? 'Catatan perbaikan revisi untuk dosen...' : 'Catatan untuk dosen mengenai tugas ini...' ?>"><?= e($submission['student_notes'] ?? '') ?></textarea>
                                                        </div>

                                                        <button type="submit" class="btn <?= $isRevision ? 'btn-danger' : 'btn-warning' ?> font-weight-bold px-4 btn-submit-assignment">
                                                            <i class="uil <?= $isRevision ? 'uil-redo' : 'uil-check-circle' ?> mr-1"></i>
                                                            <?= $isRevision ? 'Kirim Ulang Revisi Tugas' : ($isSubmitted ? 'Simpan Perubahan Tugas' : 'Kumpulkan Tugas') ?>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>

                                            <?php if ($isSubmitted && !$isRevision): ?>
                                                <div class="text-right mt-2">
                                                    <button class="btn btn-sm btn-link font-weight-bold btn-toggle-re-submit" type="button" data-toggle="collapse" data-target="#form_sub_<?= $aId ?>" aria-expanded="false" aria-controls="form_sub_<?= $aId ?>">
                                                        <i class="uil uil-edit mr-1"></i> Edit / Kirim Ulang Tugas
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="alert alert-secondary mt-3 mb-0 small text-center">
                                                <i class="uil uil-lock mr-1"></i> Tugas ini sudah dinilai secara final oleh admin/dosen dan pengumpulan tugas telah <strong>dikunci</strong>.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </article><?php endforeach; ?><?php endif; ?>
                </section><?php endif; ?>
            </div>
        </section><?php else: ?><section class="course-hero py-5">
            <div class="container text-center"><small class="small-text">Learning</small>
                <h1><?= e($settings['course_page_title'] ?? 'Course') ?></h1>
                <p><?= e($settings['course_page_subtitle'] ?? '') ?></p><?php if (user_logged_in()): ?><p><a
                        class="btn custom-btn custom-btn-bg custom-btn-link" href="my_courses.php">Kelas Saya</a></p>
                <?php endif; ?>
            </div>
        </section>
        <section class="course-list pb-5">
            <div class="container">
                <div class="course-search-bar mb-4">
                    <form class="course-search-form" method="get" action="course.php" role="search">
                        <label class="sr-only" for="course_search">Cari nama kelas</label>
                        <div class="input-group">
                            <input type="search" class="form-control" id="course_search" name="q"
                                value="<?= e($courseSearchQuery) ?>" placeholder="Cari nama kelas...">
                            <div class="input-group-append">
                                <button class="btn custom-btn custom-btn-bg custom-btn-link" type="submit"><i
                                        class="uil uil-search"></i> Cari</button>
                                <?php if ($hasCourseSearch): ?><a class="btn btn-outline-secondary"
                                    href="course.php">Reset</a><?php endif; ?>
                            </div>
                        </div>
                    </form>
                    <?php if ($hasCourseSearch): ?><p class="course-search-summary mb-0">Menampilkan
                        <?= e((string) $courseTotalCount) ?> hasil untuk "<?= e($courseSearchQuery) ?>".</p><?php endif; ?>
                </div>
                <?php if (!$courses): ?><div class="text-center py-5">
                    <?php if ($hasCourseSearch): ?><h2>Kelas tidak ditemukan</h2>
                    <p>Tidak ada kelas aktif dengan nama "<?= e($courseSearchQuery) ?>".</p><?php else: ?><h2>Belum ada course</h2>
                    <p>Course aktif yang dibuat admin akan tampil di sini.</p><?php endif; ?>
                </div><?php else: ?><div class="row"><?php foreach ($courses as $course): ?><div
                        class="col-lg-3 col-md-6 col-12 mb-4">
                        <article class="course-card"><a class="course-card-image"
                                href="course.php?id=<?= e((string) $course['id']) ?>"><img
                                    src="<?= e($course['thumbnail_path']) ?>" alt="<?= e($course['title']) ?>"></a>
                            <div class="course-card-body">
                                <h2><a
                                        href="course.php?id=<?= e((string) $course['id']) ?>"><?= e($course['title']) ?></a>
                                </h2>
                                <p><?= e($course['description'] ?? '') ?></p>
                                <?php $cardStatus = course_availability_status($course); ?><small
                                    class="course-status <?= e($cardStatus['code']) ?>"><?= e($cardStatus['label']) ?></small><?php if (user_logged_in() && user_has_course_enrollment(current_user_id(), (int) $course['id'], $pdo)): ?><small
                                    class="course-status open">Sudah enroll</small><?php endif; ?><a
                                    class="news-read-more" href="course.php?id=<?= e((string) $course['id']) ?>">Masuk
                                    kelas <i class="uil uil-arrow-right"></i></a>
                            </div>
                        </article>
                    </div><?php endforeach; ?></div><?php if ($courseTotalPages > 1): ?><nav
                        class="mt-4 d-flex justify-content-center" aria-label="Pagination course">
                        <ul class="pagination course-pagination mb-0">
                            <li class="page-item <?= $courseCurrentPage <= 1 ? 'disabled' : '' ?>"><a
                                    class="page-link"
                                    href="<?= e($coursePageUrl(max(1, $courseCurrentPage - 1))) ?>">Sebelumnya</a>
                            </li>
                            <?php for ($i = max(1, $courseCurrentPage - 2); $i <= min($courseTotalPages, $courseCurrentPage + 2); $i++): ?><li
                                class="page-item <?= $i === $courseCurrentPage ? 'active' : '' ?>"><a
                                    class="page-link" href="<?= e($coursePageUrl($i)) ?>"
                                    <?= $i === $courseCurrentPage ? 'aria-current="page"' : '' ?>><?= e((string) $i) ?></a>
                            </li><?php endfor; ?>
                            <li class="page-item <?= $courseCurrentPage >= $courseTotalPages ? 'disabled' : '' ?>"><a
                                    class="page-link"
                                    href="<?= e($coursePageUrl(min($courseTotalPages, $courseCurrentPage + 1))) ?>">Berikutnya</a>
                            </li>
                        </ul>
                    </nav><?php endif; ?><?php endif; ?></div>
        </section><?php endif; ?>
    </main>

    <?php include __DIR__ . '/footer.php'; ?>

    <script src="js/jquery-3.3.1.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/Headroom.js"></script>
    <script src="js/jQuery.headroom.js"></script>
    <script src="js/owl.carousel.min.js"></script>
    <script src="js/smoothscroll.js"></script>
    <script src="js/custom.js?v=20260506-dark-mobile"></script>
    <script>
    function toggleSubmissionType(assignmentId, type) {
        var driveGroup = document.getElementById('group_drive_' + assignmentId);
        var fileGroup = document.getElementById('group_file_' + assignmentId);
        var labelDrive = document.getElementById('label_drive_' + assignmentId);
        var labelFile = document.getElementById('label_file_' + assignmentId);
        if (driveGroup && fileGroup) {
            if (type === 'drive') {
                driveGroup.style.display = 'block';
                fileGroup.style.display = 'none';
                if (labelDrive) labelDrive.classList.add('active');
                if (labelFile) labelFile.classList.remove('active');
            } else {
                driveGroup.style.display = 'none';
                fileGroup.style.display = 'block';
                if (labelDrive) labelDrive.classList.remove('active');
                if (labelFile) labelFile.classList.add('active');
            }
        }
    }
    </script>
</body>

</html>

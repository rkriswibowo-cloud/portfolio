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
    <link rel="stylesheet" href="css/tooplate-style.css?v=20260514-course-dropdown">
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
                    <li class="mr-2">
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
    <script src="js/jquery-3.3.1.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/Headroom.js"></script>
    <script src="js/jQuery.headroom.js"></script>
    <script src="js/owl.carousel.min.js"></script>
    <script src="js/smoothscroll.js"></script>
    <script src="js/custom.js?v=20260506-dark-mobile"></script>
</body>

</html>

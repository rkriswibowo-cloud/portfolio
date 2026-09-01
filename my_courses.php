<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/config/content.php';
require_once __DIR__ . '/config/lang.php';

start_app_session();
require_user_login('user_login.php');

$pdo = pdo(true);
$settings = get_settings($pdo);

$appTimezone = new DateTimeZone('Asia/Jakarta');
$now = new DateTimeImmutable('now', $appTimezone);

/**
 * Course yang sudah melewati waktu akhir otomatis dibuat inaktif.
 */
if ($pdo) {
    try {
        $statement = $pdo->prepare(
            'UPDATE courses
             SET is_active = 0
             WHERE is_active = 1
               AND end_at IS NOT NULL
               AND end_at <> ""
               AND end_at < ?'
        );
        $statement->execute([$now->format('Y-m-d H:i:s')]);
    } catch (Throwable $e) {
        // Fallback
    }
}

/**
 * Cek apakah course sudah melewati waktu akhir.
 */
function user_course_has_ended(array $course, DateTimeImmutable $now, DateTimeZone $timezone): bool
{
    if (empty($course['end_at'])) {
        return false;
    }

    $endTime = DateTimeImmutable::createFromFormat(
        'Y-m-d H:i:s',
        (string) $course['end_at'],
        $timezone
    );

    if (!$endTime instanceof DateTimeImmutable) {
        return false;
    }

    return $endTime < $now;
}

/**
 * Cek apakah course belum waktunya dibuka.
 */
function user_course_not_started(array $course, DateTimeImmutable $now, DateTimeZone $timezone): bool
{
    if (empty($course['start_at'])) {
        return false;
    }

    $startTime = DateTimeImmutable::createFromFormat(
        'Y-m-d H:i:s',
        (string) $course['start_at'],
        $timezone
    );

    if (!$startTime instanceof DateTimeImmutable) {
        return false;
    }

    return $startTime > $now;
}

/**
 * Course yang boleh muncul di daftar kelas aktif:
 */
function user_course_visible_in_my_courses(array $course, DateTimeImmutable $now, DateTimeZone $timezone): bool
{
    if ((int) ($course['is_active'] ?? 0) !== 1) {
        return false;
    }

    if (user_course_has_ended($course, $now, $timezone)) {
        return false;
    }

    return true;
}

/**
 * Status frontend dengan timezone yang konsisten.
 */
function user_course_frontend_status(array $course, DateTimeImmutable $now, DateTimeZone $timezone): array
{
    if ((int) ($course['is_active'] ?? 0) !== 1) {
        return [
            'is_open' => false,
            'code' => 'inactive',
            'label' => 'Inaktif',
            'message' => 'Kelas ini sedang tidak aktif.',
        ];
    }

    if (user_course_has_ended($course, $now, $timezone)) {
        return [
            'is_open' => false,
            'code' => 'ended',
            'label' => 'Berakhir',
            'message' => 'Masa akses kelas ini sudah berakhir.',
        ];
    }

    if (user_course_not_started($course, $now, $timezone)) {
        return [
            'is_open' => false,
            'code' => 'upcoming',
            'label' => 'Belum mulai',
            'message' => 'Kelas ini belum mulai.',
        ];
    }

    return [
        'is_open' => true,
        'code' => 'open',
        'label' => 'Aktif',
        'message' => '',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'enroll_token') {
    verify_csrf_token();

    $result = enroll_user_with_token(
        current_user_id(),
        (string) ($_POST['enrollment_token'] ?? ''),
        null,
        $pdo
    );

    set_user_flash($result['ok'] ? 'success' : 'danger', $result['message']);
    redirect('my_courses.php');
}

$flash = get_user_flash();

$allCourses = get_user_enrolled_courses(current_user_id(), $pdo);

// Pisahkan kelas yang sedang aktif/berjalan dan riwayat kelas yang sudah berakhir
$activeCourses = array_values(array_filter(
    $allCourses,
    static fn (array $course): bool => user_course_visible_in_my_courses($course, $now, $appTimezone)
));

$historyCourses = array_values(array_filter(
    $allCourses,
    static fn (array $course): bool => !user_course_visible_in_my_courses($course, $now, $appTimezone)
));

// Cek Riwayat CV Tersimpan di Cloud
$savedCv = null;
if ($pdo) {
    try {
        $stCv = $pdo->prepare('SELECT id, title, updated_at FROM user_cvs WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1');
        $stCv->execute([current_user_id()]);
        $savedCv = $stCv->fetch();
    } catch (Throwable $e) {
        $savedCv = null;
    }
}
?>
<!doctype html>
<html lang="<?= e(current_lang()) ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Kelas Saya & Riwayat Aktivitas | <?= e($settings['site_brand'] ?? 'Marvel') ?></title>
    <link rel="icon" href="images/favicon/logo2.png" type="image/png" />

    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/unicons.css">
    <link rel="stylesheet" href="css/owl.carousel.min.css">
    <link rel="stylesheet" href="css/owl.theme.default.min.css">
    <link rel="stylesheet" href="css/tooplate-style.css?v=20260901-bilingual">
</head>

<body>
    <!-- Main Navigation (Clean & Uncluttered) -->
    <nav class="navbar navbar-expand-sm navbar-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="uil uil-user"></i>
                <?= e($settings['site_brand'] ?? 'Marvel') ?>
            </a>

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
                    <li class="nav-item">
                        <a href="index.php" class="nav-link">
                            <span data-hover="Home">Home</span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="berita.php" class="nav-link">
                            <span data-hover="<?= e(__t('nav_news')) ?>"><?= e(__t('nav_news')) ?></span>
                        </a>
                    </li>

                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" id="courseDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span data-hover="<?= e(__t('nav_course')) ?>"><?= e(__t('nav_course')) ?></span></a>
                        <div class="dropdown-menu navbar-course-dropdown" aria-labelledby="courseDropdown">
                            <a class="dropdown-item" href="course.php"><i class="uil uil-book-open"></i> <?= e(__t('nav_free')) ?></a>
                            <a class="dropdown-item" href="https://lms.rksolusindo.com" target="_blank" rel="noopener noreferrer"><i class="uil uil-star"></i> <?= e(__t('nav_premium')) ?></a>
                            <a class="dropdown-item" href="cv-generator.php"><i class="uil uil-file-alt"></i> <?= e(__t('nav_cv_generator')) ?></a>
                        </div>
                    </li>

                    <li class="nav-item active">
                        <a href="my_courses.php" class="nav-link">
                            <span data-hover="<?= e(__t('nav_my_courses')) ?>"><?= e(__t('nav_my_courses')) ?></span>
                        </a>
                    </li>
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
                    <li class="nav-item mr-2">
                        <a href="user_logout.php" class="nav-link text-danger font-weight-bold">
                            <span data-hover="<?= e(__t('nav_logout')) ?>"><?= e(__t('nav_logout')) ?></span>
                        </a>
                    </li>
                    <li>
                        <div class="color-mode color-mode-toggle d-lg-flex justify-content-center align-items-center" role="button" tabindex="0" aria-label="Ganti dark mode" aria-pressed="false">
                            <i class="color-mode-icon"></i>
                            <?= e(__t('nav_color_mode')) ?>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <small class="small-text">Member Dashboard</small>
                <h1>Kelas Saya</h1>
                <p>
                    Halo, <strong><?= e(current_user_name()) ?></strong>.
                    Kelola akses pembelajaran aktif Anda atau masukkan token baru dari admin.
                </p>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show mb-4">
                    <?= e($flash['message']) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Token Enrollment Box -->
            <div class="row justify-content-center mb-5">
                <div class="col-lg-6 col-12">
                    <form method="post" class="course-access-card">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="form_type" value="enroll_token">

                        <h2>
                            <i class="uil uil-key-skeleton text-warning"></i>
                            Token Enrollment
                        </h2>

                        <p>
                            Satu token bisa membuka banyak course sesuai pengaturan admin.
                        </p>

                        <div class="form-group">
                            <input
                                class="form-control"
                                name="enrollment_token"
                                placeholder="Masukkan token enrollment"
                                required
                            >
                        </div>

                        <button class="btn custom-btn custom-btn-bg custom-btn-link btn-block" type="submit">
                            Enroll Course
                        </button>
                    </form>
                </div>
            </div>

            <!-- SECTION 1: KELAS AKTIF SAYA -->
            <div class="mb-5">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h2 class="mb-1"><i class="uil uil-book-open text-primary mr-2"></i> Kelas Aktif</h2>
                        <p class="mb-0 text-muted small">Kelas yang saat ini dapat Anda akses materinya.</p>
                    </div>
                    <a href="course.php" class="btn btn-sm btn-outline-secondary">
                        <i class="uil uil-plus mr-1"></i> Jelajahi Course Lain
                    </a>
                </div>

                <?php if (!$activeCourses): ?>
                    <div class="course-empty-box text-center py-5 rounded">
                        <i class="uil uil-folder-open text-muted" style="font-size: 48px;"></i>
                        <h3 class="mt-3">Belum ada kelas aktif</h3>
                        <p class="text-muted mb-0">
                            Gunakan token enrollment di atas untuk menambahkan kelas ke akun Anda.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($activeCourses as $course): ?>
                            <?php $status = user_course_frontend_status($course, $now, $appTimezone); ?>
                            <?php $progress = get_course_progress_summary(current_user_id(), (int) $course['id'], $pdo); ?>

                            <div class="col-lg-3 col-md-6 col-12 mb-4">
                                <article class="course-card h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <a
                                            class="course-card-image"
                                            href="course.php?id=<?= e((string) $course['id']) ?>"
                                        >
                                            <img
                                                src="<?= e($course['thumbnail_path']) ?>"
                                                alt="<?= e($course['title']) ?>"
                                            >
                                        </a>

                                        <div class="course-card-body">
                                            <h2>
                                                <a href="course.php?id=<?= e((string) $course['id']) ?>">
                                                    <?= e($course['title']) ?>
                                                </a>
                                            </h2>

                                            <p><?= e($course['description'] ?? '') ?></p>

                                            <small class="course-status <?= e($status['code']) ?>">
                                                <?= e($status['label']) ?>
                                            </small>

                                            <div class="mt-3 mb-2">
                                                <div class="d-flex justify-content-between"><small>Progress</small><small><?= e((string) $progress['percent']) ?>%</small></div>
                                                <div class="progress" style="height: 8px;"><div class="progress-bar" role="progressbar" style="width: <?= e((string) $progress['percent']) ?>%;"></div></div>
                                                <small class="text-muted"><?= e((string) $progress['completed']) ?> / <?= e((string) $progress['total']) ?> pertemuan</small>
                                            </div>

                                            <small class="course-card-date">
                                                Enroll:
                                                <?= e(format_course_datetime($course['enrolled_at'] ?? '', 'd M Y H:i')) ?>
                                            </small>
                                        </div>
                                    </div>

                                    <div class="px-3 pb-3">
                                        <?php if ($status['is_open']): ?>
                                            <a
                                                class="news-read-more"
                                                href="course.php?id=<?= e((string) $course['id']) ?>"
                                            >
                                                Buka kelas <i class="uil uil-arrow-right"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted font-weight-bold small">
                                                <?= e($status['message']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <hr class="my-5" style="border-top: 2px dashed #e2e8f0;">

            <!-- SECTION 2: RIWAYAT & AKTIVITAS SAYA (DI BAGIAN BAWAH) -->
            <div id="riwayat-section" class="pt-2">
                <div class="text-center mb-5">
                    <small class="small-text">Riwayat & Arsip</small>
                    <h2><i class="uil uil-history text-warning mr-1"></i> Riwayat & Aktivitas Saya</h2>
                    <p class="text-muted">
                        Daftar riwayat kelas yang telah selesai, dokumen CV yang tersimpan di cloud, dan riwayat profil.
                    </p>
                </div>

                <div class="row">
                    <!-- Kartu Riwayat CV Generator -->
                    <div class="col-12 mb-4">
                        <div class="history-activity-card p-4">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mr-3" style="width: 44px; height: 44px; font-size: 20px;">
                                        <i class="uil uil-file-alt"></i>
                                    </div>
                                    <div>
                                        <h4 class="mb-0 font-weight-bold">Riwayat & Dokumen CV Generator</h4>
                                        <small class="text-muted">Kelola data CV Anda yang tersimpan aman di Cloud Database</small>
                                    </div>
                                </div>
                                <div>
                                    <a href="cv-generator.php" class="btn btn-sm btn-primary">
                                        <i class="uil uil-external-link-alt mr-1"></i> Buka CV Generator
                                    </a>
                                </div>
                            </div>

                            <?php if ($savedCv): ?>
                                <div class="history-cv-box p-3 rounded d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <strong class="history-item-title font-weight-bold"><i class="uil uil-check-circle text-success mr-1"></i> <?= e($savedCv['title'] ?? 'CV Utama') ?></strong>
                                        <span class="badge badge-success px-2 py-1 ml-2">Tersimpan di Cloud</span>
                                        <div class="small history-meta-text mt-1">
                                            Terakhir diperbarui: <?= e(format_course_datetime($savedCv['updated_at'] ?? '', 'd M Y, H:i')) ?> WIB
                                        </div>
                                    </div>
                                    <div>
                                        <a href="cv-generator.php" class="btn btn-sm btn-outline-primary">
                                            <i class="uil uil-edit mr-1"></i> Lanjutkan Edit CV
                                        </a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="history-cv-box p-3 rounded d-flex justify-content-between align-items-center flex-wrap gap-2 text-muted small">
                                    <div>
                                        <i class="uil uil-info-circle mr-1"></i> Belum ada CV yang tersimpan ke cloud. Anda dapat membuat dan menyimpan CV profesional secara gratis.
                                    </div>
                                    <div>
                                        <a href="cv-generator.php" class="btn btn-sm btn-outline-primary">
                                            <i class="uil uil-plus-circle mr-1"></i> Buat CV Baru
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tabel Riwayat Kelas yang Sudah Berakhir / Selesai -->
                <div class="history-activity-card p-4 mt-2">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h4 class="mb-0 font-weight-bold">
                            <i class="uil uil-calendar-slash text-danger mr-1"></i> Riwayat Kelas Berakhir / Selesai
                        </h4>
                        <span class="badge badge-secondary"><?= count($historyCourses) ?> Kelas</span>
                    </div>

                    <?php if (!$historyCourses): ?>
                        <div class="history-empty-box py-4 text-center small rounded">
                            <i class="uil uil-check-circle text-success" style="font-size: 24px;"></i>
                            <div class="mt-1">Belum ada kelas yang masa aktifnya berakhir. Seluruh kelas Anda saat ini masih aktif.</div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover history-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Nama Kelas</th>
                                        <th>Waktu Enroll</th>
                                        <th>Progress Belajar</th>
                                        <th>Status Akses</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($historyCourses as $hCourse): ?>
                                        <?php $hProgress = get_course_progress_summary(current_user_id(), (int) $hCourse['id'], $pdo); ?>
                                        <tr>
                                            <td class="history-course-title font-weight-bold">
                                                <?= e($hCourse['title']) ?>
                                            </td>
                                            <td class="small history-meta-text">
                                                <?= e(format_course_datetime($hCourse['enrolled_at'] ?? '', 'd M Y, H:i')) ?>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2" style="max-width: 160px;">
                                                    <div class="progress flex-grow-1" style="height: 6px;">
                                                        <div class="progress-bar bg-success" style="width: <?= e((string) $hProgress['percent']) ?>%;"></div>
                                                    </div>
                                                    <small class="font-weight-bold history-percent"><?= e((string) $hProgress['percent']) ?>%</small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-danger px-2 py-1">Akses Berakhir</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </main>

    <footer class="footer py-4 bg-light mt-5 border-top">
        <div class="container text-center">
            <p class="mb-0 text-muted small">&copy; <?= date('Y') ?> <?= e($settings['site_brand'] ?? 'Marvel') ?>. Member Learning Portal.</p>
        </div>
    </footer>

    <script src="js/jquery-3.3.1.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/Headroom.js"></script>
    <script src="js/jQuery.headroom.js"></script>
    <script src="js/custom.js?v=20260823-history"></script>
</body>

</html>

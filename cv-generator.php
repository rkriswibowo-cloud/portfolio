<?php
declare(strict_types=1);

require_once __DIR__ . '/config/content.php';
require_once __DIR__ . '/config/lang.php';

start_app_session();

$pdo = pdo(true);
$settings = get_settings($pdo);

// Otomatis buat tabel user_cvs jika database tersedia
if ($pdo) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_cvs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(150) NOT NULL DEFAULT 'CV Utama',
                cv_data LONGTEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Throwable $e) {
        // Abaikan jika database belum terhubung
    }
}

// Endpoint AJAX untuk Sinkronisasi Cloud CV bagi User yang Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = (string) $_POST['ajax_action'];

    if (!user_logged_in()) {
        echo json_encode(['success' => false, 'message' => 'Anda harus login untuk menggunakan fitur sinkronisasi akun.']);
        exit;
    }

    $userId = current_user_id();

    if ($action === 'save_cloud_cv') {
        $cvJson = trim((string) ($_POST['cv_data'] ?? ''));
        if (!$pdo || $cvJson === '') {
            echo json_encode(['success' => false, 'message' => 'Data CV kosong atau database belum siap.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT id FROM user_cvs WHERE user_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$userId]);
            $existing = $stmt->fetch();

            if ($existing) {
                $update = $pdo->prepare("UPDATE user_cvs SET cv_data = ?, updated_at = NOW() WHERE id = ?");
                $update->execute([$cvJson, $existing['id']]);
            } else {
                $insert = $pdo->prepare("INSERT INTO user_cvs (user_id, title, cv_data) VALUES (?, 'CV Utama', ?)");
                $insert->execute([$userId, $cvJson]);
            }

            echo json_encode(['success' => true, 'message' => 'CV berhasil disimpan ke akun cloud Anda!']);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan ke database: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'load_cloud_cv') {
        if (!$pdo) {
            echo json_encode(['success' => false, 'message' => 'Database belum siap.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT cv_data, updated_at FROM user_cvs WHERE user_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();

            if ($row && !empty($row['cv_data'])) {
                $parsed = json_decode($row['cv_data'], true);
                echo json_encode([
                    'success' => true,
                    'cv_data' => $parsed,
                    'updated_at' => $row['updated_at'],
                    'message' => 'CV berhasil dimuat dari akun cloud Anda!'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Belum ada data CV yang tersimpan di akun cloud Anda.']);
            }
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal membaca data dari database.']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Aksi tidak valid.']);
    exit;
}

$isLoggedIn = user_logged_in();
$userName = current_user_name();
$userEmail = current_user_email();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Buat CV profesional, modern, dan ATS-friendly secara instan dan gratis dengan Live Preview dan Download PDF.">
    <meta name="author" content="<?= e($settings['site_brand'] ?? 'Marvel') ?>">
    <link rel="icon" href="images/favicon/logo2.png" type="image/png" />

    <title>CV Generator Profesional & ATS-Friendly | <?= e($settings['site_brand'] ?? 'Marvel') ?></title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:wght@400;600;700&family=Inter:wght@400;500;600;700;800&family=Lato:wght@400;700&family=Merriweather:wght@400;700&family=Montserrat:wght@400;500;600;700;800&family=Open+Sans:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/unicons.css">
    <link rel="stylesheet" href="css/owl.carousel.min.css">
    <link rel="stylesheet" href="css/owl.theme.default.min.css">
    <link rel="stylesheet" href="css/tooplate-style.css?v=20260514-cv-gen">
    <link rel="stylesheet" href="css/cv-generator.css?v=20260823-2">

    <!-- html2pdf for high quality client-side A4 PDF download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
</head>
<body>

    <!-- Main Navigation -->
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
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false"
                    aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                    <span class="navbar-toggler-icon"></span>
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a href="index.php#about" class="nav-link"><span data-hover="<?= e(__t('nav_about')) ?>"><?= e(__t('nav_about')) ?></span></a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php#project" class="nav-link"><span data-hover="<?= e(__t('nav_projects')) ?>"><?= e(__t('nav_projects')) ?></span></a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php#resume" class="nav-link"><span data-hover="<?= e(__t('nav_resume')) ?>"><?= e(__t('nav_resume')) ?></span></a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php#contact" class="nav-link"><span data-hover="<?= e(__t('nav_contact')) ?>"><?= e(__t('nav_contact')) ?></span></a>
                    </li>
                    <li class="nav-item">
                        <a href="berita.php" class="nav-link"><span data-hover="<?= e(__t('nav_news')) ?>"><?= e(__t('nav_news')) ?></span></a>
                    </li>
                    <li class="nav-item dropdown active">
                        <a href="#" class="nav-link dropdown-toggle" id="courseDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span data-hover="<?= e(__t('nav_course')) ?>"><?= e(__t('nav_course')) ?></span></a>
                        <div class="dropdown-menu navbar-course-dropdown" aria-labelledby="courseDropdown">
                            <a class="dropdown-item" href="course.php"><i class="uil uil-book-open"></i> <?= e(__t('nav_free')) ?></a>
                            <a class="dropdown-item" href="https://lms.rksolusindo.com" target="_blank" rel="noopener noreferrer"><i class="uil uil-star"></i> <?= e(__t('nav_premium')) ?></a>
                            <a class="dropdown-item" href="cv-generator.php"><i class="uil uil-file-alt"></i> <?= e(__t('nav_cv_generator')) ?></a>
                        </div>
                    </li>
                    <?php if ($isLoggedIn): ?>
                    <li class="nav-item">
                        <a href="my_courses.php" class="nav-link"><span data-hover="<?= e(__t('nav_my_courses')) ?>"><?= e(__t('nav_my_courses')) ?></span></a>
                    </li>
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
                    <?php if ($isLoggedIn): ?>
                    <li class="nav-item mr-2">
                        <a href="user_logout.php" class="nav-link text-danger font-weight-bold"><span data-hover="<?= e(__t('nav_logout')) ?>"><?= e(__t('nav_logout')) ?></span></a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item mr-2">
                        <a href="user_login.php" class="nav-link"><span data-hover="<?= e(__t('nav_login')) ?>"><?= e(__t('nav_login')) ?></span></a>
                    </li>
                    <?php endif; ?>
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

    <!-- Top Sticky Action Bar -->
    <div class="cv-action-bar">
        <div class="container-fluid px-lg-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <a href="course.php" class="btn btn-sm btn-outline-secondary d-none d-md-inline-flex align-items-center">
                        <i class="uil uil-arrow-left mr-1"></i> Course
                    </a>
                    <h5 class="mb-0 font-weight-bold text-dark d-none d-sm-block">
                        <i class="uil uil-file-alt text-warning mr-1"></i> CV Generator
                    </h5>
                    
                    <span id="saveStatusBadge" class="cv-badge-status">
                        <i class="uil uil-check-circle"></i> Tersimpan otomatis
                    </span>
                </div>

                <div class="btn-group-responsive">
                    <?php if ($isLoggedIn): ?>
                        <button type="button" class="cv-btn cv-btn-success" id="btnSaveCloud" title="Simpan data CV ke akun cloud">
                            <i class="uil uil-cloud-upload"></i> <span class="d-none d-sm-inline">Simpan</span>
                        </button>
                    <?php else: ?>
                        <a href="user_login.php" class="cv-btn cv-btn-secondary" title="Login untuk simpan ke cloud">
                            <i class="uil uil-signin"></i> <span class="d-none d-sm-inline">Login Member</span>
                        </a>
                    <?php endif; ?>

                    <!-- Dropdown Opsi & Data -->
                    <div class="dropdown d-inline-block">
                        <button class="cv-btn cv-btn-secondary dropdown-toggle" type="button" id="dropdownDataOptions" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="uil uil-sliders-v-alt"></i> <span>Opsi & Data</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right shadow-sm p-2" aria-labelledby="dropdownDataOptions" style="min-width: 230px; border-radius: 10px;">
                            <?php if ($isLoggedIn): ?>
                                <a class="dropdown-item py-2" href="javascript:void(0)" id="btnLoadCloud">
                                    <i class="uil uil-cloud-download text-primary mr-2"></i> Muat dari Akun Cloud
                                </a>
                            <?php endif; ?>
                            <a class="dropdown-item py-2" href="javascript:void(0)" id="btnLoadDemo">
                                <i class="uil uil-bolt text-warning mr-2"></i> Isi Contoh Data Demo
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item py-2" href="javascript:void(0)" id="btnExportJson">
                                <i class="uil uil-export text-info mr-2"></i> Ekspor Cadangan (.json)
                            </a>
                            <label class="dropdown-item py-2 mb-0" style="cursor:pointer;">
                                <i class="uil uil-import text-success mr-2"></i> Impor Data (.json)
                                <input type="file" id="importJsonInput" accept=".json" style="display:none;">
                            </label>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item py-2 text-danger" href="javascript:void(0)" id="btnResetForm">
                                <i class="uil uil-trash-alt mr-2"></i> Kosongkan Formulir (Reset)
                            </a>
                        </div>
                    </div>

                    <button type="button" class="cv-btn cv-btn-secondary" id="btnPrintCv" title="Cetak langsung ke printer">
                        <i class="uil uil-print"></i> <span class="d-none d-md-inline">Print</span>
                    </button>
                    <button type="button" class="cv-btn cv-btn-primary" id="btnDownloadPdf" title="Unduh format PDF A4">
                        <i class="uil uil-download-alt"></i> <span>Download PDF</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Builder Section -->
    <main class="cv-generator-page">
        <div class="container-fluid px-lg-4 cv-builder-container">

            <!-- User Notification / Info Banner -->
            <?php if ($isLoggedIn): ?>
                <div class="alert alert-info alert-dismissible fade show mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2" role="alert">
                    <div>
                        <i class="uil uil-user-check mr-1 font-weight-bold"></i>
                        Halo <strong><?= e($userName) ?></strong>! Sebagai member terdaftar, Anda dapat menyimpan CV langsung ke <strong>Akun Cloud</strong> dan menyinkronkan antar laptop/HP.
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-primary" id="btnBannerAutofill">
                            <i class="uil uil-sync mr-1"></i> Auto-fill dari Akun
                        </button>
                        <button type="button" class="close position-static p-0 ml-2" data-dismiss="alert" aria-label="Close" style="line-height:1;">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
                    <i class="uil uil-info-circle mr-1 font-weight-bold"></i>
                    <strong>Mode Tamu:</strong> Data CV Anda otomatis tersimpan di browser ini (*LocalStorage*). Ingin menyimpan CV secara permanen di akun dan bisa diakses dari perangkat mana saja?
                    <a href="user_login.php" class="font-weight-bold text-dark text-underline ml-1">Login User</a> atau
                    <a href="user_register.php" class="font-weight-bold text-dark text-underline">Daftar Akun Baru</a>.
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Mobile View Switcher (Visible only on mobile/tablet) -->
            <div class="cv-mobile-switcher">
                <button type="button" class="cv-mobile-tab active" id="tabMobileEditor" onclick="switchMobileView('editor')">
                    <i class="uil uil-edit"></i> Edit Form
                </button>
                <button type="button" class="cv-mobile-tab" id="tabMobilePreview" onclick="switchMobileView('preview')">
                    <i class="uil uil-eye"></i> Live Preview A4
                </button>
            </div>

            <div class="row">
                <!-- LEFT COLUMN: FORM EDITOR -->
                <div class="col-lg-6 col-12 cv-editor-col" id="editorColumn">

                    <!-- SECTION 1: TEMPLATE & STYLING -->
                    <div class="cv-editor-card">
                        <div class="cv-editor-header" data-toggle="collapse" data-target="#collapseStyle">
                            <h3>
                                <span class="cv-header-icon"><i class="uil uil-palette"></i></span>
                                1. Pilihan Template & Desain
                            </h3>
                            <i class="uil uil-angle-down text-muted"></i>
                        </div>
                        <div id="collapseStyle" class="collapse show">
                            <div class="cv-editor-body">
                                <label class="cv-form-label">Pilih Template CV:</label>
                                <div class="cv-template-grid">
                                    <div class="cv-template-item active" data-template="modern">
                                        <div class="cv-template-preview-thumb">
                                            <i class="uil uil-table text-primary" style="font-size: 28px;"></i>
                                        </div>
                                        <h4 class="cv-template-name">Modern Pro</h4>
                                        <small class="text-muted">2 Kolom Sidebar</small>
                                    </div>
                                    <div class="cv-template-item" data-template="executive">
                                        <div class="cv-template-preview-thumb">
                                            <i class="uil uil-document-layout-left text-success" style="font-size: 28px;"></i>
                                        </div>
                                        <h4 class="cv-template-name">Executive ATS</h4>
                                        <small class="text-muted">1 Kolom Standar</small>
                                    </div>
                                    <div class="cv-template-item" data-template="creative">
                                        <div class="cv-template-preview-thumb">
                                            <i class="uil uil-brush-alt text-warning" style="font-size: 28px;"></i>
                                        </div>
                                        <h4 class="cv-template-name">Creative Studio</h4>
                                        <small class="text-muted">Banner Aksen</small>
                                    </div>
                                    <div class="cv-template-item" data-template="minimalist">
                                        <div class="cv-template-preview-thumb">
                                            <i class="uil uil-align-left text-dark" style="font-size: 28px;"></i>
                                        </div>
                                        <h4 class="cv-template-name">Minimalist</h4>
                                        <small class="text-muted">Swiss Grid Clean</small>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 col-12 cv-form-group">
                                        <label class="cv-form-label">Warna Utama (Aksen):</label>
                                        <div class="cv-color-palette">
                                            <span class="cv-color-dot active" style="background:#4361ee;" data-color="#4361ee" title="Royal Blue"></span>
                                            <span class="cv-color-dot" style="background:#10b981;" data-color="#10b981" title="Emerald Green"></span>
                                            <span class="cv-color-dot" style="background:#0ea5e9;" data-color="#0ea5e9" title="Sky Blue"></span>
                                            <span class="cv-color-dot" style="background:#8b5cf6;" data-color="#8b5cf6" title="Purple Violet"></span>
                                            <span class="cv-color-dot" style="background:#e11d48;" data-color="#e11d48" title="Crimson Rose"></span>
                                            <span class="cv-color-dot" style="background:#334155;" data-color="#334155" title="Slate Dark"></span>
                                            <span class="cv-color-dot" style="background:#d97706;" data-color="#d97706" title="Warm Amber"></span>
                                            <div class="cv-custom-color-wrap">
                                                <input type="color" id="customColorPicker" class="cv-color-picker-input" value="#4361ee" title="Pilih warna custom">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-12 cv-form-group">
                                        <label class="cv-form-label">Jenis Font:</label>
                                        <select class="cv-form-select" id="fontSelector">
                                            <optgroup label="Standar & ATS Klasik">
                                                <option value="'Times New Roman', Times, serif">Times New Roman (Classic ATS)</option>
                                                <option value="Arial, Helvetica, sans-serif">Arial (Universal Clean)</option>
                                                <option value="Calibri, 'Segoe UI', Arial, sans-serif">Calibri / Segoe (Corporate)</option>
                                                <option value="Georgia, 'Times New Roman', serif">Georgia (Formal Serif)</option>
                                            </optgroup>
                                            <optgroup label="Modern Sans-Serif">
                                                <option value="'Plus Jakarta Sans', sans-serif" selected>Plus Jakarta Sans (Modern Pro)</option>
                                                <option value="'Inter', sans-serif">Inter (Clean Tech)</option>
                                                <option value="'Poppins', sans-serif">Poppins (Friendly)</option>
                                                <option value="'Roboto', sans-serif">Roboto (Clean Standard)</option>
                                                <option value="'Montserrat', sans-serif">Montserrat (Geometric)</option>
                                                <option value="'Lato', sans-serif">Lato (Balanced)</option>
                                                <option value="'Open Sans', sans-serif">Open Sans (Legible)</option>
                                            </optgroup>
                                            <optgroup label="Executive & Editorial Serif">
                                                <option value="'Merriweather', serif">Merriweather (Executive)</option>
                                                <option value="'EB Garamond', Garamond, serif">Garamond (Academic & Formal)</option>
                                            </optgroup>
                                        </select>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 col-12 cv-form-group">
                                        <label class="cv-form-label">Kerapatan Teks (Spacing):</label>
                                        <select class="cv-form-select" id="densitySelector">
                                            <option value="compact">Kompak (Cocok 1 Halaman Padat)</option>
                                            <option value="normal" selected>Normal (Proporsional)</option>
                                            <option value="spacious">Spacious (Renggang)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 col-12 cv-form-group">
                                        <label class="cv-form-label">Bentuk Foto Profil:</label>
                                        <select class="cv-form-select" id="photoShapeSelector">
                                            <option value="circle" selected>Lingkaran (Bulat)</option>
                                            <option value="rounded">Kotak Melengkung</option>
                                            <option value="square">Persegi</option>
                                            <option value="hide">Sembunyikan Foto</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: PERSONAL INFO -->
                    <div class="cv-editor-card">
                        <div class="cv-editor-header" data-toggle="collapse" data-target="#collapsePersonal">
                            <h3>
                                <span class="cv-header-icon"><i class="uil uil-user"></i></span>
                                2. Informasi Pribadi
                            </h3>
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($isLoggedIn): ?>
                                    <button type="button" class="btn btn-xs btn-outline-primary mr-2" id="btnSectionAutofill" title="Isi Nama dan Email dari akun profil">
                                        <i class="uil uil-sync"></i> Isi dari Akun
                                    </button>
                                <?php endif; ?>
                                <i class="uil uil-angle-down text-muted"></i>
                            </div>
                        </div>
                        <div id="collapsePersonal" class="collapse show">
                            <div class="cv-editor-body">
                                <div class="cv-form-group">
                                    <label class="cv-form-label">Foto Profil:</label>
                                    <div class="cv-photo-upload-box">
                                        <img id="photoPreviewImg" src="images/undraw/undraw_software_engineer_lvl5.svg" class="cv-photo-thumb" alt="Profile Photo">
                                        <div>
                                            <label class="btn btn-sm btn-outline-primary mb-1" style="cursor:pointer;">
                                                <i class="uil uil-upload mr-1"></i> Unggah Foto
                                                <input type="file" id="photoFileInput" accept="image/*" style="display:none;">
                                            </label>
                                            <button type="button" class="btn btn-sm btn-outline-danger mb-1" id="btnRemovePhoto">
                                                <i class="uil uil-trash-alt mr-1"></i> Hapus
                                            </button>
                                            <div class="small text-muted">Format PNG, JPG, WebP (Maks. 2MB).</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 col-12 cv-form-group">
                                        <label class="cv-form-label">Nama Lengkap *</label>
                                        <input type="text" class="cv-form-input" id="inputFullName" placeholder="cth: Marvel Sann" value="Marvel Sann">
                                    </div>
                                    <div class="col-md-6 col-12 cv-form-group">
                                        <label class="cv-form-label">Profesi / Title *</label>
                                        <input type="text" class="cv-form-input" id="inputJobTitle" placeholder="cth: Senior Full-Stack Developer" value="Senior Full-Stack Developer">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 col-12 cv-form-group">
                                        <label class="cv-form-label"><i class="uil uil-envelope text-primary"></i> Email *</label>
                                        <input type="email" class="cv-form-input" id="inputEmail" placeholder="cth: marvel@example.com" value="marvel@example.com">
                                    </div>
                                    <div class="col-md-6 col-12 cv-form-group">
                                        <label class="cv-form-label"><i class="uil uil-phone text-primary"></i> Nomor Telepon / WA</label>
                                        <input type="text" class="cv-form-input" id="inputPhone" placeholder="cth: +62 812-3456-7890" value="+62 812-3456-7890">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 col-12 cv-form-group">
                                        <label class="cv-form-label"><i class="uil uil-map-marker text-primary"></i> Kota, Negara</label>
                                        <input type="text" class="cv-form-input" id="inputLocation" placeholder="cth: Jakarta, Indonesia" value="Jakarta, Indonesia">
                                    </div>
                                    <div class="col-md-6 col-12 cv-form-group">
                                        <label class="cv-form-label"><i class="uil uil-globe text-primary"></i> Website / Portofolio</label>
                                        <input type="text" class="cv-form-input" id="inputWebsite" placeholder="cth: marvelsann.dev" value="https://marvelsann.dev">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 col-12 cv-form-group">
                                        <label class="cv-form-label"><i class="uil uil-linkedin text-primary"></i> LinkedIn URL</label>
                                        <input type="text" class="cv-form-input" id="inputLinkedin" placeholder="cth: linkedin.com/in/marvelsann" value="linkedin.com/in/marvelsann">
                                    </div>
                                    <div class="col-md-6 col-12 cv-form-group">
                                        <label class="cv-form-label"><i class="uil uil-github text-primary"></i> GitHub URL</label>
                                        <input type="text" class="cv-form-input" id="inputGithub" placeholder="cth: github.com/marvelsann" value="github.com/marvelsann">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3: SUMMARY -->
                    <div class="cv-editor-card">
                        <div class="cv-editor-header" data-toggle="collapse" data-target="#collapseSummary">
                            <h3>
                                <span class="cv-header-icon"><i class="uil uil-comment-alt-lines"></i></span>
                                3. Ringkasan Profesional (About Me)
                            </h3>
                            <i class="uil uil-angle-down text-muted"></i>
                        </div>
                        <div id="collapseSummary" class="collapse show">
                            <div class="cv-editor-body">
                                <div class="cv-form-group">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="cv-form-label mb-0">Deskripsi Singkat Profil:</label>
                                        <div class="dropdown">
                                            <button class="btn btn-xs btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown" style="font-size:12px; padding:2px 8px;">
                                                <i class="uil uil-lightbulb-alt text-warning"></i> Ide Kalimat
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="javascript:void(0)" onclick="insertSummaryPrompt('dev')">Developer / Programmer</a>
                                                <a class="dropdown-item" href="javascript:void(0)" onclick="insertSummaryPrompt('designer')">UI/UX Designer</a>
                                                <a class="dropdown-item" href="javascript:void(0)" onclick="insertSummaryPrompt('graduate')">Fresh Graduate</a>
                                            </div>
                                        </div>
                                    </div>
                                    <textarea class="cv-form-textarea" id="inputSummary" rows="3" placeholder="Tuliskan ringkasan karir, keahlian utama, dan pencapaian Anda..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 4: WORK EXPERIENCE -->
                    <div class="cv-editor-card">
                        <div class="cv-editor-header" data-toggle="collapse" data-target="#collapseExp">
                            <h3>
                                <span class="cv-header-icon"><i class="uil uil-briefcase-alt"></i></span>
                                4. Pengalaman Kerja
                            </h3>
                            <i class="uil uil-angle-down text-muted"></i>
                        </div>
                        <div id="collapseExp" class="collapse show">
                            <div class="cv-editor-body">
                                <div class="cv-repeater-list" id="experienceList">
                                    <!-- Dynamic Items Injected via JS -->
                                </div>
                                <button type="button" class="cv-btn-add-item" id="btnAddExp">
                                    <i class="uil uil-plus-circle"></i> Tambah Pengalaman Kerja
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 5: EDUCATION -->
                    <div class="cv-editor-card">
                        <div class="cv-editor-header" data-toggle="collapse" data-target="#collapseEdu">
                            <h3>
                                <span class="cv-header-icon"><i class="uil uil-graduation-cap"></i></span>
                                5. Riwayat Pendidikan
                            </h3>
                            <i class="uil uil-angle-down text-muted"></i>
                        </div>
                        <div id="collapseEdu" class="collapse show">
                            <div class="cv-editor-body">
                                <div class="cv-repeater-list" id="educationList">
                                    <!-- Dynamic Items Injected via JS -->
                                </div>
                                <button type="button" class="cv-btn-add-item" id="btnAddEdu">
                                    <i class="uil uil-plus-circle"></i> Tambah Pendidikan
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 6: SKILLS -->
                    <div class="cv-editor-card">
                        <div class="cv-editor-header" data-toggle="collapse" data-target="#collapseSkills">
                            <h3>
                                <span class="cv-header-icon"><i class="uil uil-wrench"></i></span>
                                6. Keahlian & Keterampilan (Skills)
                            </h3>
                            <i class="uil uil-angle-down text-muted"></i>
                        </div>
                        <div id="collapseSkills" class="collapse show">
                            <div class="cv-editor-body">
                                <div class="cv-form-group">
                                    <label class="cv-form-label">Keahlian Teknis / Hard Skills (Pisahkan dengan koma):</label>
                                    <input type="text" class="cv-form-input" id="inputTechSkills" placeholder="cth: PHP, JavaScript, React, MySQL, Laravel, TailwindCSS">
                                    <div class="cv-skills-pills-wrap" id="techSkillsPills"></div>
                                </div>

                                <div class="cv-form-group">
                                    <label class="cv-form-label">Soft Skills / Interpersonal (Pisahkan dengan koma):</label>
                                    <input type="text" class="cv-form-input" id="inputSoftSkills" placeholder="cth: Problem Solving, Team Leadership, Critical Thinking, Agile/Scrum">
                                    <div class="cv-skills-pills-wrap" id="softSkillsPills"></div>
                                </div>

                                <div class="cv-form-group">
                                    <label class="cv-form-label">Tools & Software (Pisahkan dengan koma):</label>
                                    <input type="text" class="cv-form-input" id="inputToolSkills" placeholder="cth: Git, GitHub, VS Code, Figma, Docker, Postman">
                                    <div class="cv-skills-pills-wrap" id="toolSkillsPills"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 7: PROJECTS -->
                    <div class="cv-editor-card">
                        <div class="cv-editor-header" data-toggle="collapse" data-target="#collapseProjects">
                            <h3>
                                <span class="cv-header-icon"><i class="uil uil-folder-open"></i></span>
                                7. Proyek & Portofolio Unggulan
                            </h3>
                            <i class="uil uil-angle-down text-muted"></i>
                        </div>
                        <div id="collapseProjects" class="collapse show">
                            <div class="cv-editor-body">
                                <div class="cv-repeater-list" id="projectList">
                                    <!-- Dynamic Items Injected via JS -->
                                </div>
                                <button type="button" class="cv-btn-add-item" id="btnAddProject">
                                    <i class="uil uil-plus-circle"></i> Tambah Proyek
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 8: CERTIFICATIONS -->
                    <div class="cv-editor-card">
                        <div class="cv-editor-header" data-toggle="collapse" data-target="#collapseCerts">
                            <h3>
                                <span class="cv-header-icon"><i class="uil uil-award"></i></span>
                                8. Sertifikasi & Lisensi
                            </h3>
                            <i class="uil uil-angle-down text-muted"></i>
                        </div>
                        <div id="collapseCerts" class="collapse show">
                            <div class="cv-editor-body">
                                <div class="cv-repeater-list" id="certList">
                                    <!-- Dynamic Items Injected via JS -->
                                </div>
                                <button type="button" class="cv-btn-add-item" id="btnAddCert">
                                    <i class="uil uil-plus-circle"></i> Tambah Sertifikat
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 9: LANGUAGES & AWARDS -->
                    <div class="cv-editor-card">
                        <div class="cv-editor-header" data-toggle="collapse" data-target="#collapseOther">
                            <h3>
                                <span class="cv-header-icon"><i class="uil uil-globe"></i></span>
                                9. Bahasa & Penghargaan
                            </h3>
                            <i class="uil uil-angle-down text-muted"></i>
                        </div>
                        <div id="collapseOther" class="collapse show">
                            <div class="cv-editor-body">
                                <div class="cv-form-group">
                                    <label class="cv-form-label">Bahasa yang Dikuasai (cth: Indonesia (Native), English (Professional)):</label>
                                    <input type="text" class="cv-form-input" id="inputLanguages" placeholder="cth: Bahasa Indonesia (Native), English (Professional Working)">
                                </div>
                                <div class="cv-form-group">
                                    <label class="cv-form-label">Penghargaan / Organisasi / Info Tambahan (Opsional):</label>
                                    <textarea class="cv-form-textarea" id="inputAwards" rows="2" placeholder="cth: Juara 1 Hackathon Nasional 2024, Best Graduate Award Informatika 2020..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- RIGHT COLUMN: LIVE A4 PREVIEW CANVAS -->
                <div class="col-lg-6 col-12 cv-preview-col" id="previewColumn">
                    <div class="cv-preview-sticky-wrap">
                        
                        <!-- Preview Controls Bar -->
                        <div class="cv-preview-controls-bar">
                            <div class="d-flex align-items-center gap-2">
                                <span class="small font-weight-bold text-muted">
                                    <i class="uil uil-file-alt text-warning"></i> Pratinjau Dokumen A4
                                </span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary" onclick="setZoom('zoom-75')" title="75%">75%</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setZoom('zoom-90')" title="90%">90%</button>
                                    <button type="button" class="btn btn-outline-secondary active" onclick="setZoom('zoom-100')" title="100%">100%</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setZoom('zoom-110')" title="110%">110%</button>
                                </div>
                            </div>
                        </div>

                        <!-- Scrollable Preview Viewport -->
                        <div class="cv-preview-scroll-pane">
                            <div id="cvPaper" class="cv-paper zoom-100 density-normal">
                                <!-- Live Rendered Content Injected here -->
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="footer py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 col-12">
                    <p class="copyright-text text-center">Copyright &copy; <?= date('Y') ?> <?= e($settings['site_brand'] ?? 'Marvel') ?>. Built with passion & precision.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Pass User Session Data to JS -->
    <script>
        window.cvUserData = {
            isLoggedIn: <?= json_encode($isLoggedIn) ?>,
            name: <?= json_encode($userName) ?>,
            email: <?= json_encode($userEmail) ?>
        };
    </script>

    <!-- Scripts -->
    <script src="js/jquery-3.3.1.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/Headroom.js"></script>
    <script src="js/jQuery.headroom.js"></script>
    <script src="js/smoothscroll.js"></script>
    <script src="js/custom.js"></script>
    <script src="js/cv-generator.js?v=20260823-3"></script>
</body>
</html>

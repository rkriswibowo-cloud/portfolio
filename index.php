<?php
declare(strict_types=1);

require_once __DIR__ . '/config/content.php';

start_app_session();

$pdo = pdo(true);
$settings = get_settings($pdo);
$projects = get_projects($pdo, true);
$experiences = get_resume_items($pdo, 'experience', true);
$educations = get_resume_items($pdo, 'education', true);
$techStacks = get_tech_stacks($pdo, true);
$academicRecords = get_academic_records($pdo, null, true);

$publicationsBySubcategory = [
    'Jurnal Internasional' => [],
    'Prosiding Internasional' => [],
    'Jurnal Nasional' => [],
];
$researchList = [];
$serviceList = [];
$patentList = [];
$bookList = [];

foreach ($academicRecords as $record) {
    $cat = $record['category'] ?? '';
    if ($cat === 'publikasi') {
        $sub = $record['subcategory'] ?? 'Jurnal Internasional';
        if (!isset($publicationsBySubcategory[$sub])) {
            $publicationsBySubcategory[$sub] = [];
        }
        $publicationsBySubcategory[$sub][] = $record;
    } elseif ($cat === 'riset') {
        $researchList[] = $record;
    } elseif ($cat === 'pengabdian') {
        $serviceList[] = $record;
    } elseif ($cat === 'hki') {
        $patentList[] = $record;
    } elseif ($cat === 'buku') {
        $bookList[] = $record;
    }
}

$contactStatus = null;
$contactMessage = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['form_type'] ?? '') === 'contact') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    if ($name === '' || $email === '' || $message === '') {
        $contactStatus = 'danger';
        $contactMessage = 'Nama, email, dan pesan wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contactStatus = 'danger';
        $contactMessage = 'Format email belum benar.';
    } elseif (!$pdo) {
        $contactStatus = 'danger';
        $contactMessage = 'Database belum tersambung. Silakan impor database.sql terlebih dahulu.';
    } else {
        try {
            $statement = $pdo->prepare('INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)');
            $statement->execute([$name, $email, $message]);
            $contactStatus = 'success';
            $contactMessage = 'Pesan berhasil dikirim. Terima kasih!';
        } catch (Throwable $exception) {
            $contactStatus = 'danger';
            $contactMessage = 'Pesan belum bisa disimpan. Periksa koneksi database.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="images/favicon/logo2.png" type="image/png" />

    <title><?= e($settings['page_title'] ?? 'Marvel Portfolio') ?></title>

    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/unicons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="css/owl.carousel.min.css">
    <link rel="stylesheet" href="css/owl.theme.default.min.css">

    <link rel="stylesheet" href="css/tooplate-style.css?v=20260901-fontawesome-icons">
  </head>
  <body>

    <nav class="navbar navbar-expand-sm navbar-light">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="uil uil-user"></i> <?= e($settings['site_brand'] ?? 'Marvel') ?></a>

            <div class="navbar-mobile-actions">
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
                        <a href="#about" class="nav-link"><span data-hover="About">About</span></a>
                    </li>
                    <li class="nav-item">
                        <a href="#project" class="nav-link"><span data-hover="Projects">Projects</span></a>
                    </li>
                    <li class="nav-item">
                        <a href="#resume" class="nav-link"><span data-hover="Resume">Resume</span></a>
                    </li>
                    <li class="nav-item">
                        <a href="#academic" class="nav-link"><span data-hover="Publikasi">Publikasi</span></a>
                    </li>
                    <li class="nav-item">
                        <a href="#contact" class="nav-link"><span data-hover="Contact">Contact</span></a>
                    </li>
                    <li class="nav-item">
                        <a href="berita.php" class="nav-link"><span data-hover="News">News</span></a>
                    </li>
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" id="courseDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span data-hover="Course">Course</span></a>
                        <div class="dropdown-menu navbar-course-dropdown" aria-labelledby="courseDropdown">
                            <a class="dropdown-item" href="course.php"><i class="uil uil-book-open"></i> Free</a>
                            <a class="dropdown-item" href="https://lms.rksolusindo.com" target="_blank" rel="noopener noreferrer"><i class="uil uil-star"></i> Premium</a>
                            <a class="dropdown-item" href="cv-generator.php"><i class="uil uil-file-alt"></i> CV Generator</a>
                        </div>
                    </li>
                    <?php if (user_logged_in()): ?>
                    <li class="nav-item">
                        <a href="my_courses.php" class="nav-link"><span data-hover="My Courses">My Courses</span></a>
                    </li>
                    <?php endif; ?>
                </ul>

                <ul class="navbar-nav ml-lg-auto">
                    <li class="ml-lg-4">
                      <div class="color-mode color-mode-toggle d-lg-flex justify-content-center align-items-center" role="button" tabindex="0" aria-label="Ganti dark mode" aria-pressed="false">
                        <i class="color-mode-icon"></i>
                        Color mode
                      </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="about full-screen d-lg-flex justify-content-center align-items-center" id="about">
        <div class="container">
            <div class="row">
                <div class="col-lg-7 col-md-12 col-12 d-flex align-items-center">
                    <div class="about-text">
                        <small class="small-text"><?= e($settings['about_welcome'] ?? '') ?></small>
                        <h1 class="animated animated-text">
                            <span class="mr-2"><?= e($settings['about_prefix'] ?? '') ?></span>
                            <span class="animated-info">
                                <span class="animated-item"><?= e($settings['about_name'] ?? '') ?></span>
                                <span class="animated-item"><?= e($settings['about_role_1'] ?? '') ?></span>
                                <span class="animated-item"><?= e($settings['about_role_2'] ?? '') ?></span>
                                <span class="animated-item"><?= e($settings['about_role_3'] ?? '') ?></span>
                            </span>
                        </h1>

                        <p><?= e($settings['about_description'] ?? '') ?></p>

                        <div class="custom-btn-group mt-4">
                          <a href="<?= e($settings['resume_file_url'] ?? '#') ?>" class="btn mr-lg-2 custom-btn"><i class="uil uil-file-alt"></i> Download Resume</a>
                          <a href="#contact" class="btn custom-btn custom-btn-bg custom-btn-link"><?= e($settings['quote_button_text'] ?? 'Contact Me') ?></a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5 col-md-12 col-12">
                    <div class="about-image svg">
                        <img src="<?= e($settings['about_image'] ?? 'images/undraw/undraw_software_engineer_lvl5.svg') ?>" class="img-fluid" alt="About image">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="project py-5" id="project">
        <div class="container">
            <div class="row">
              <div class="col-lg-11 text-center mx-auto col-12">
                  <div class="col-lg-8 mx-auto">
                    <h2><?= e($settings['project_heading'] ?? '') ?></h2>
                  </div>

                  <div class="owl-carousel owl-theme">
                    <?php foreach ($projects as $project): ?>
                      <div class="item">
                        <div class="project-info">
                          <?php if (!empty($project['link_url']) && $project['link_url'] !== '#'): ?>
                            <a href="<?= e($project['link_url']) ?>" target="_blank" rel="noopener">
                              <img src="<?= e($project['image_path']) ?>" class="img-fluid" alt="<?= e($project['title']) ?>">
                            </a>
                          <?php else: ?>
                            <img src="<?= e($project['image_path']) ?>" class="img-fluid" alt="<?= e($project['title']) ?>">
                          <?php endif; ?>

                          <?php if (!empty($project['title']) || !empty($project['description'])): ?>
                            <div class="project-caption">
                              <?php if (!empty($project['title'])): ?>
                                <h3><?= e($project['title']) ?></h3>
                              <?php endif; ?>
                              <?php if (!empty($project['description'])): ?>
                                <p><?= e($project['description']) ?></p>
                              <?php endif; ?>
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
              </div>
            </div>
        </div>
    </section>

    <section class="resume py-5 d-lg-flex justify-content-center align-items-center" id="resume">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 col-12">
                  <h2 class="mb-4">Experiences</h2>

                    <div class="timeline">
                        <?php foreach ($experiences as $item): ?>
                          <div class="timeline-wrapper">
                               <div class="timeline-yr">
                                    <span><?= e($item['year_label']) ?></span>
                               </div>
                               <div class="timeline-info">
                                    <h3>
                                      <span><?= e($item['title']) ?></span>
                                      <?php if (!empty($item['subtitle'])): ?><small><?= e($item['subtitle']) ?></small><?php endif; ?>
                                    </h3>
                                    <p><?= e($item['description']) ?></p>
                               </div>
                          </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="col-lg-6 col-12">
                  <h2 class="mb-4 mobile-mt-2">Educations</h2>

                    <div class="timeline">
                        <?php foreach ($educations as $item): ?>
                          <div class="timeline-wrapper">
                               <div class="timeline-yr">
                                    <span><?= e($item['year_label']) ?></span>
                               </div>
                               <div class="timeline-info">
                                    <h3>
                                      <span><?= e($item['title']) ?></span>
                                      <?php if (!empty($item['subtitle'])): ?><small><?= e($item['subtitle']) ?></small><?php endif; ?>
                                    </h3>
                                    <p><?= e($item['description']) ?></p>
                               </div>
                          </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Academic, Publication & Research 2-Grid Section -->
    <section class="academic-section py-5" id="academic">
        <div class="container">
            <div class="row">
                <!-- Left Grid: Blue Profile Card -->
                <div class="col-lg-4 col-md-5 col-12 mb-4 mb-md-0">
                    <div class="academic-profile-sidebar">
                        <div class="academic-profile-header">
                            <div class="academic-profile-badge">
                                <i class="fa-solid fa-graduation-cap"></i>
                            </div>
                            <h3 class="academic-profile-title"><?= e($settings['academic_profile_title'] ?? 'Profil Akademik') ?></h3>
                            <p class="academic-profile-desc"><?= e($settings['academic_profile_subtitle'] ?? 'Profil Peneliti & Tautan Publikasi Terindeks') ?></p>
                        </div>

                        <div class="academic-profile-links">
                            <!-- Email Pribadi -->
                            <?php if (!empty($settings['academic_email'])): ?>
                            <a href="mailto:<?= e($settings['academic_email']) ?>" target="_blank" rel="noopener noreferrer" class="academic-profile-link" title="Kirim email ke <?= e($settings['academic_email']) ?>">
                                <div class="profile-link-icon">
                                    <i class="fa-solid fa-envelope"></i>
                                </div>
                                <div class="profile-link-content">
                                    <span class="profile-link-title">Email Pribadi</span>
                                    <span class="profile-link-meta"><?= e($settings['academic_email']) ?></span>
                                </div>
                                <i class="fa-solid fa-arrow-up-right-from-square profile-link-arrow"></i>
                            </a>
                            <?php endif; ?>

                            <!-- LinkedIn -->
                            <?php if (!empty($settings['academic_linkedin'])): ?>
                            <a href="<?= e($settings['academic_linkedin']) ?>" target="_blank" rel="noopener noreferrer" class="academic-profile-link" title="Buka LinkedIn">
                                <div class="profile-link-icon">
                                    <i class="fa-brands fa-linkedin-in"></i>
                                </div>
                                <div class="profile-link-content">
                                    <span class="profile-link-title">LinkedIn</span>
                                    <span class="profile-link-meta">Koneksi Profesional</span>
                                </div>
                                <i class="fa-solid fa-arrow-up-right-from-square profile-link-arrow"></i>
                            </a>
                            <?php endif; ?>

                            <!-- Google Scholar -->
                            <?php if (!empty($settings['academic_scholar'])): ?>
                            <a href="<?= e($settings['academic_scholar']) ?>" target="_blank" rel="noopener noreferrer" class="academic-profile-link" title="Buka Google Scholar">
                                <div class="profile-link-icon">
                                    <i class="fa-brands fa-google-scholar"></i>
                                </div>
                                <div class="profile-link-content">
                                    <span class="profile-link-title">Google Scholar</span>
                                    <span class="profile-link-meta">Sitasi & Indeks Karya</span>
                                </div>
                                <i class="fa-solid fa-arrow-up-right-from-square profile-link-arrow"></i>
                            </a>
                            <?php endif; ?>

                            <!-- Scopus -->
                            <?php if (!empty($settings['academic_scopus'])): ?>
                            <a href="<?= e($settings['academic_scopus']) ?>" target="_blank" rel="noopener noreferrer" class="academic-profile-link" title="Buka Profil Scopus">
                                <div class="profile-link-icon">
                                    <i class="fa-solid fa-award"></i>
                                </div>
                                <div class="profile-link-content">
                                    <span class="profile-link-title">Scopus</span>
                                    <span class="profile-link-meta">Author Profile Terindeks</span>
                                </div>
                                <i class="fa-solid fa-arrow-up-right-from-square profile-link-arrow"></i>
                            </a>
                            <?php endif; ?>

                            <!-- SINTA Dikti -->
                            <?php if (!empty($settings['academic_sinta'])): ?>
                            <a href="<?= e($settings['academic_sinta']) ?>" target="_blank" rel="noopener noreferrer" class="academic-profile-link" title="Buka Profil SINTA Dikti">
                                <div class="profile-link-icon">
                                    <i class="fa-solid fa-certificate"></i>
                                </div>
                                <div class="profile-link-content">
                                    <span class="profile-link-title">SINTA Dikti</span>
                                    <span class="profile-link-meta">Profil Dosen & Peneliti</span>
                                </div>
                                <i class="fa-solid fa-arrow-up-right-from-square profile-link-arrow"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Grid: Tabs & Scrollable Content -->
                <div class="col-lg-8 col-md-7 col-12">
                    <div class="academic-main-card">
                        <!-- Navigation Tabs matching screenshot -->
                        <ul class="nav nav-tabs academic-nav-tabs" id="academicTabs" role="tablist">
                            <li class="nav-item">
                                <button type="button" class="nav-link active" id="tab-publikasi-btn" data-toggle="tab" data-target="#tab-publikasi" role="tab" aria-controls="tab-publikasi" aria-selected="true">
                                    Publikasi
                                </button>
                            </li>
                            <li class="nav-item">
                                <button type="button" class="nav-link" id="tab-riset-btn" data-toggle="tab" data-target="#tab-riset" role="tab" aria-controls="tab-riset" aria-selected="false">
                                    Riset
                                </button>
                            </li>
                            <li class="nav-item">
                                <button type="button" class="nav-link" id="tab-pengabdian-btn" data-toggle="tab" data-target="#tab-pengabdian" role="tab" aria-controls="tab-pengabdian" aria-selected="false">
                                    Pengabdian
                                </button>
                            </li>
                            <li class="nav-item">
                                <button type="button" class="nav-link" id="tab-hki-btn" data-toggle="tab" data-target="#tab-hki" role="tab" aria-controls="tab-hki" aria-selected="false">
                                    HKI/Paten
                                </button>
                            </li>
                            <li class="nav-item">
                                <button type="button" class="nav-link" id="tab-buku-btn" data-toggle="tab" data-target="#tab-buku" role="tab" aria-controls="tab-buku" aria-selected="false">
                                    Buku
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Content Container (Scrollable Box) -->
                        <div class="tab-content academic-tab-content" id="academicTabContent">
                            <!-- 1. TAB PUBLIKASI -->
                            <div class="tab-pane fade show active" id="tab-publikasi" role="tabpanel" aria-labelledby="tab-publikasi-btn">
                                <div class="academic-scroll-area">
                                    <?php
                                    $publicationCategories = [
                                        'Jurnal Internasional' => $publicationsBySubcategory['Jurnal Internasional'] ?? [],
                                        'Prosiding Internasional' => $publicationsBySubcategory['Prosiding Internasional'] ?? [],
                                        'Jurnal Nasional' => $publicationsBySubcategory['Jurnal Nasional'] ?? [],
                                    ];
                                    $hasAnyPub = false;
                                    ?>
                                    <?php foreach ($publicationCategories as $subHeading => $items): ?>
                                        <?php if (!empty($items)): ?>
                                            <?php $hasAnyPub = true; ?>
                                            <div class="academic-group">
                                                <h3 class="academic-group-heading"><?= e($subHeading) ?></h3>
                                                <div class="academic-items-list">
                                                    <?php foreach ($items as $item): ?>
                                                        <div class="academic-item">
                                                            <div class="academic-item-title-wrap">
                                                                <?php if (!empty($item['url']) && $item['url'] !== '#'): ?>
                                                                    <a href="<?= e($item['url']) ?>" target="_blank" rel="noopener noreferrer" class="academic-item-title academic-link-hover">
                                                                        <?= e($item['title']) ?>
                                                                        <i class="fa-solid fa-arrow-up-right-from-square academic-external-icon"></i>
                                                                    </a>
                                                                <?php else: ?>
                                                                    <h4 class="academic-item-title"><?= e($item['title']) ?></h4>
                                                                <?php endif; ?>
                                                            </div>

                                                            <?php if (!empty($item['authors'])): ?>
                                                                <p class="academic-item-authors"><?= e($item['authors']) ?></p>
                                                            <?php endif; ?>

                                                            <?php if (!empty($item['journal_meta'])): ?>
                                                                <p class="academic-item-citation">
                                                                    <?= e($item['journal_meta']) ?>
                                                                    <?php if (!empty($item['url']) && $item['url'] !== '#'): ?>
                                                                        <a href="<?= e($item['url']) ?>" target="_blank" rel="noopener noreferrer" class="academic-citation-link">[Buka URL]</a>
                                                                    <?php endif; ?>
                                                                </p>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>

                                    <?php if (!$hasAnyPub): ?>
                                        <div class="text-center py-5 text-muted">
                                            <i class="uil uil-file-slash" style="font-size: 38px;"></i>
                                            <p class="mt-2 mb-0">Belum ada data publikasi.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- 2. TAB RISET -->
                            <div class="tab-pane fade" id="tab-riset" role="tabpanel" aria-labelledby="tab-riset-btn">
                                <div class="academic-scroll-area">
                                    <?php if (!empty($researchList)): ?>
                                        <div class="academic-items-list">
                                            <?php foreach ($researchList as $item): ?>
                                                <div class="academic-item">
                                                    <h4 class="academic-item-title"><?= e($item['title']) ?></h4>
                                                    <?php if (!empty($item['authors'])): ?>
                                                        <p class="academic-item-authors"><?= e($item['authors']) ?></p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['journal_meta'])): ?>
                                                        <p class="academic-item-citation"><?= e($item['journal_meta']) ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-5 text-muted">
                                            <i class="uil uil-search" style="font-size: 38px;"></i>
                                            <p class="mt-2 mb-0">Belum ada data riset.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- 3. TAB PENGABDIAN -->
                            <div class="tab-pane fade" id="tab-pengabdian" role="tabpanel" aria-labelledby="tab-pengabdian-btn">
                                <div class="academic-scroll-area">
                                    <?php if (!empty($serviceList)): ?>
                                        <div class="academic-items-list">
                                            <?php foreach ($serviceList as $item): ?>
                                                <div class="academic-item">
                                                    <h4 class="academic-item-title"><?= e($item['title']) ?></h4>
                                                    <?php if (!empty($item['authors'])): ?>
                                                        <p class="academic-item-authors"><?= e($item['authors']) ?></p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['journal_meta'])): ?>
                                                        <p class="academic-item-citation"><?= e($item['journal_meta']) ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-5 text-muted">
                                            <i class="uil uil-users-alt" style="font-size: 38px;"></i>
                                            <p class="mt-2 mb-0">Belum ada data pengabdian.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- 4. TAB HKI / PATEN -->
                            <div class="tab-pane fade" id="tab-hki" role="tabpanel" aria-labelledby="tab-hki-btn">
                                <div class="academic-scroll-area">
                                    <?php if (!empty($patentList)): ?>
                                        <div class="academic-items-list">
                                            <?php foreach ($patentList as $item): ?>
                                                <div class="academic-item">
                                                    <h4 class="academic-item-title"><?= e($item['title']) ?></h4>
                                                    <?php if (!empty($item['authors'])): ?>
                                                        <p class="academic-item-authors"><?= e($item['authors']) ?></p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['journal_meta'])): ?>
                                                        <p class="academic-item-citation"><?= e($item['journal_meta']) ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-5 text-muted">
                                            <i class="uil uil-award" style="font-size: 38px;"></i>
                                            <p class="mt-2 mb-0">Belum ada data HKI / Paten.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- 5. TAB BUKU -->
                            <div class="tab-pane fade" id="tab-buku" role="tabpanel" aria-labelledby="tab-buku-btn">
                                <div class="academic-scroll-area">
                                    <?php if (!empty($bookList)): ?>
                                        <div class="academic-items-list">
                                            <?php foreach ($bookList as $item): ?>
                                                <div class="academic-item">
                                                    <h4 class="academic-item-title"><?= e($item['title']) ?></h4>
                                                    <?php if (!empty($item['authors'])): ?>
                                                        <p class="academic-item-authors"><?= e($item['authors']) ?></p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['journal_meta'])): ?>
                                                        <p class="academic-item-citation"><?= e($item['journal_meta']) ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-5 text-muted">
                                            <i class="uil uil-book" style="font-size: 38px;"></i>
                                            <p class="mt-2 mb-0">Belum ada data buku.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Tech Stack Infinite Marquee Slider -->
    <?php if (!empty($techStacks)): ?>
    <section class="tech-stack-section" id="tech-stack">
        <div class="container text-center mb-4">
            <div class="row justify-content-center">
                <div class="col-lg-8 col-12">
                    <small class="small-text text-uppercase font-weight-bold" style="letter-spacing: 2px; color: #ffc200;">Technologies & Tools</small>
                    <h2 class="mb-3"><?= e($settings['tech_stack_heading'] ?? 'Tech Stack & Technologies') ?></h2>
                    <p class="text-muted mb-0"><?= e($settings['tech_stack_subtitle'] ?? 'Kumpulan teknologi, bahasa pemrograman, framework, dan tools modern yang saya gunakan dalam membangun solusi digital inovatif.') ?></p>
                </div>
            </div>
        </div>

        <div class="tech-marquee-wrapper">
            <div class="tech-marquee-track">
                <!-- Track 1 (Original items) -->
                <?php foreach ($techStacks as $tech): ?>
                    <?php
                      $logoSrc = str_starts_with($tech['logo_path'], 'http://') || str_starts_with($tech['logo_path'], 'https://')
                          ? $tech['logo_path']
                          : ltrim($tech['logo_path'], '/');
                    ?>
                    <div class="tech-card" title="<?= e($tech['name']) ?> (<?= e($tech['category'] ?? 'Tech') ?>)">
                        <div class="tech-card-logo">
                            <img src="<?= e($logoSrc) ?>" alt="<?= e($tech['name']) ?>" loading="lazy">
                        </div>
                        <div class="tech-card-info">
                            <h6 class="tech-card-name"><?= e($tech['name']) ?></h6>
                            <?php if (!empty($tech['category'])): ?>
                                <span class="tech-card-category"><?= e($tech['category']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Track 2 (Duplicate for smooth infinite continuous loop) -->
                <?php foreach ($techStacks as $tech): ?>
                    <?php
                      $logoSrc = str_starts_with($tech['logo_path'], 'http://') || str_starts_with($tech['logo_path'], 'https://')
                          ? $tech['logo_path']
                          : ltrim($tech['logo_path'], '/');
                    ?>
                    <div class="tech-card" aria-hidden="true">
                        <div class="tech-card-logo">
                            <img src="<?= e($logoSrc) ?>" alt="<?= e($tech['name']) ?>" loading="lazy">
                        </div>
                        <div class="tech-card-info">
                            <h6 class="tech-card-name"><?= e($tech['name']) ?></h6>
                            <?php if (!empty($tech['category'])): ?>
                                <span class="tech-card-category"><?= e($tech['category']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="contact py-5" id="contact">
      <div class="container">
        <div class="row">
          <div class="col-lg-5 mr-lg-5 col-12">
            <div class="google-map w-100">
              <iframe src="<?= e($settings['contact_map_url'] ?? '') ?>" width="400" height="300" frameborder="0" style="border:0" allowfullscreen></iframe>
            </div>

            <div class="contact-info d-flex justify-content-between align-items-center py-4 px-lg-5">
                <div class="contact-info-item">
                  <h3 class="mb-3 text-white">Say hello</h3>
                  <p class="footer-text mb-0"><?= e($settings['contact_phone'] ?? '') ?></p>
                  <p><a href="mailto:<?= e($settings['contact_email'] ?? '') ?>"><?= e($settings['contact_email'] ?? '') ?></a></p>
                </div>

                <ul class="social-links">
                  <?php if (!empty($settings['social_dribbble'])): ?><li><a href="<?= e($settings['social_dribbble']) ?>" class="uil uil-dribbble" data-toggle="tooltip" data-placement="left" title="Dribbble"></a></li><?php endif; ?>
                  <?php if (!empty($settings['social_instagram'])): ?><li><a href="<?= e($settings['social_instagram']) ?>" class="uil uil-instagram" data-toggle="tooltip" data-placement="left" title="Instagram"></a></li><?php endif; ?>
                  <?php if (!empty($settings['social_youtube'])): ?><li><a href="<?= e($settings['social_youtube']) ?>" class="uil uil-youtube" data-toggle="tooltip" data-placement="left" title="Youtube"></a></li><?php endif; ?>
                </ul>
            </div>
          </div>

          <div class="col-lg-6 col-12">
            <div class="contact-form">
              <h2 class="mb-4"><?= e($settings['contact_heading'] ?? '') ?></h2>

              <?php if ($contactStatus): ?>
                <div class="alert alert-<?= e($contactStatus) ?>" role="alert"><?= e($contactMessage) ?></div>
              <?php endif; ?>

              <form action="index.php#contact" method="post">
                <input type="hidden" name="form_type" value="contact">
                <div class="row">
                  <div class="col-lg-6 col-12">
                    <input type="text" class="form-control" name="name" placeholder="Your Name" id="name" required>
                  </div>

                  <div class="col-lg-6 col-12">
                    <input type="email" class="form-control" name="email" placeholder="Email" id="email" required>
                  </div>

                  <div class="col-12">
                    <textarea name="message" rows="6" class="form-control" id="message" placeholder="Message" required></textarea>
                  </div>

                  <div class="ml-lg-auto col-lg-5 col-12">
                    <input type="submit" class="form-control submit-btn" value="Send Button">
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </section>

     <footer class="footer py-5">
          <div class="container">
               <div class="row">
                    <div class="col-lg-12 col-12">
                        <p class="copyright-text text-center">Copyright &copy; <?= date('Y') ?> <?= e($settings['footer_company'] ?? 'Company Name') ?>. All rights reserved</p>
                        
                    </div>
               </div>
          </div>
     </footer>

    <script src="js/jquery-3.3.1.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/Headroom.js"></script>
    <script src="js/jQuery.headroom.js"></script>
    <script src="js/owl.carousel.min.js"></script>
    <script src="js/smoothscroll.js"></script>
    <script src="js/custom.js?v=20260901-academic-tabs"></script>

  </body>
</html>

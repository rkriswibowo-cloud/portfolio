<?php
declare(strict_types=1);

require_once __DIR__ . '/config/content.php';
require_once __DIR__ . '/config/lang.php';

start_app_session();

$pdo = pdo(true);
$settings = get_settings($pdo);
$carouselImages = get_news_carousel_images($pdo, true);
$slug = trim((string) ($_GET['slug'] ?? ''));
$currentPost = $slug !== '' ? get_news_post_by_slug($slug, $pdo, true) : null;
$posts = $currentPost ? [] : get_news_posts($pdo, true);
$pageTitle = $currentPost ? $currentPost['title'] . ' - ' . ($settings['site_brand'] ?? 'Marvel') : ($settings['news_page_title'] ?? 'Berita Terbaru');

function news_date(?string $value): string
{
    if (!$value) {
        return '';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d M Y', $timestamp) : '';
}
?>
<!doctype html>
<html lang="<?= e(current_lang()) ?>">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="images/favicon/logo2.png" type="image/png" />

    <title><?= e($pageTitle) ?></title>

    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/unicons.css">
    <link rel="stylesheet" href="css/owl.carousel.min.css">
    <link rel="stylesheet" href="css/owl.theme.default.min.css">
    <link rel="stylesheet" href="css/tooplate-style.css?v=20260901-bilingual">
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
                        <a href="index.php#academic" class="nav-link"><span data-hover="<?= e(__t('nav_publications')) ?>"><?= e(__t('nav_publications')) ?></span></a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php#contact" class="nav-link"><span data-hover="<?= e(__t('nav_contact')) ?>"><?= e(__t('nav_contact')) ?></span></a>
                    </li>
                    <li class="nav-item active">
                        <a href="berita.php" class="nav-link"><span data-hover="<?= e(__t('nav_news')) ?>"><?= e(__t('nav_news')) ?></span></a>
                    </li>
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" id="courseDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span data-hover="<?= e(__t('nav_course')) ?>"><?= e(__t('nav_course')) ?></span></a>
                        <div class="dropdown-menu navbar-course-dropdown" aria-labelledby="courseDropdown">
                            <a class="dropdown-item" href="course.php"><i class="uil uil-book-open"></i> <?= e(__t('nav_free')) ?></a>
                            <a class="dropdown-item" href="https://lms.rksolusindo.com" target="_blank" rel="noopener noreferrer"><i class="uil uil-star"></i> <?= e(__t('nav_premium')) ?></a>
                            <a class="dropdown-item" href="cv-generator.php"><i class="uil uil-file-alt"></i> <?= e(__t('nav_cv_generator')) ?></a>
                        </div>
                    </li>
                    <?php if (user_logged_in()): ?>
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

    <main class="news-page">
      <?php if ($currentPost): ?>
        <section class="news-detail py-5">
          <div class="container">
            <div class="row justify-content-center">
              <div class="col-lg-9 col-12">
                <a class="news-back-link" href="berita.php"><i class="uil uil-angle-left"></i> <?= e($settings['news_detail_back_text'] ?? 'Kembali ke daftar berita') ?></a>
                <article class="news-article">
                  <?php if (!empty($currentPost['image_path'])): ?>
                    <img class="news-detail-image" src="<?= e($currentPost['image_path']) ?>" alt="<?= e($currentPost['title']) ?>">
                  <?php endif; ?>
                  <div class="news-meta">
                    <?php if (!empty($currentPost['author'])): ?><span><i class="uil uil-user"></i> <?= e($currentPost['author']) ?></span><?php endif; ?>
                    <?php if (news_date($currentPost['published_at'] ?? null)): ?><span><i class="uil uil-calendar-alt"></i> <?= e(news_date($currentPost['published_at'])) ?></span><?php endif; ?>
                  </div>
                  <h1><?= e($currentPost['title']) ?></h1>
                  <?php if (!empty($currentPost['excerpt'])): ?>
                    <p class="news-lead"><?= e($currentPost['excerpt']) ?></p>
                  <?php endif; ?>
                  <div class="news-content">
                    <?= render_rich_text_content($currentPost['content'] ?? '') ?>
                  </div>
                </article>
              </div>
            </div>
          </div>
        </section>
      <?php else: ?>
        <section class="news-hero py-5">
          <div class="container">
            <div class="row align-items-center">
              <div class="col-lg-5 col-12">
                <small class="small-text">News</small>
                <h1><?= e($settings['news_page_title'] ?? 'Berita Terbaru') ?></h1>
                <p><?= e($settings['news_page_subtitle'] ?? '') ?></p>
              </div>
              <div class="col-lg-7 col-12">
                <?php if ($carouselImages): ?>
                  <div class="owl-carousel owl-theme news-carousel">
                    <?php foreach ($carouselImages as $image): ?>
                      <div class="item">
                        <div class="news-slide">
                          <?php if (!empty($image['link_url']) && $image['link_url'] !== '#'): ?><a href="<?= e($image['link_url']) ?>"><?php endif; ?>
                            <img src="<?= e($image['image_path']) ?>" alt="<?= e($image['title']) ?>">
                          <?php if (!empty($image['link_url']) && $image['link_url'] !== '#'): ?></a><?php endif; ?>
                          <div class="news-slide-caption">
                            <h2><?= e($image['title']) ?></h2>
                            <?php if (!empty($image['caption'])): ?><p><?= e($image['caption']) ?></p><?php endif; ?>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </section>

        <section class="news-list py-5">
          <div class="container">
            <?php if (!$posts): ?>
              <div class="text-center">
                <h2>Belum ada berita</h2>
                <p>Berita yang dipublish akan tampil di halaman ini.</p>
              </div>
            <?php else: ?>
              <div class="row">
                <?php foreach ($posts as $post): ?>
                  <div class="col-lg-4 col-md-6 col-12">
                    <article class="news-card">
                      <?php if (!empty($post['image_path'])): ?>
                        <a href="berita.php?slug=<?= e($post['slug']) ?>" class="news-card-image">
                          <img src="<?= e($post['image_path']) ?>" alt="<?= e($post['title']) ?>">
                        </a>
                      <?php endif; ?>
                      <div class="news-card-body">
                        <div class="news-meta">
                          <?php if (news_date($post['published_at'] ?? null)): ?><span><i class="uil uil-calendar-alt"></i> <?= e(news_date($post['published_at'])) ?></span><?php endif; ?>
                          <?php if (!empty($post['author'])): ?><span><i class="uil uil-user"></i> <?= e($post['author']) ?></span><?php endif; ?>
                        </div>
                        <h2><a href="berita.php?slug=<?= e($post['slug']) ?>"><?= e($post['title']) ?></a></h2>
                        <?php if (!empty($post['excerpt'])): ?><p><?= e($post['excerpt']) ?></p><?php endif; ?>
                        <a class="news-read-more" href="berita.php?slug=<?= e($post['slug']) ?>"><?= e(__t('news_read_more')) ?> <i class="uil uil-arrow-right"></i></a>
                      </div>
                    </article>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </section>
      <?php endif; ?>
    </main>

    <footer class="footer py-5">
      <div class="container">
        <div class="row">
          <div class="col-lg-12 col-12">
            <p class="copyright-text text-center">Copyright &copy; <?= date('Y') ?> <?= e($settings['footer_company'] ?? 'Marvel') ?>. <?= e(__t('footer_rights')) ?></p>
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
    <script src="js/custom.js?v=20260506-dark-mobile"></script>
  </body>
</html>

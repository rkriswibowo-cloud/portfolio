<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

function admin_header(string $title): void
{
    $current = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $flash = get_admin_flash();
    $menuStructure = [
        [
            'type' => 'single',
            'file' => 'index.php',
            'label' => 'Dashboard',
            'icon' => 'fa-solid fa-gauge-high',
        ],
        [
            'type' => 'group',
            'id' => 'menuProfile',
            'label' => 'Profil & Resume',
            'icon' => 'fa-solid fa-user-tie',
            'items' => [
                'profile.php' => ['label' => 'Edit Profil', 'icon' => 'fa-solid fa-user-pen'],
                'about.php' => ['label' => 'Tentang (About)', 'icon' => 'fa-solid fa-circle-info'],
                'resume.php' => ['label' => 'Resume', 'icon' => 'fa-solid fa-file-lines'],
                'tech_stack.php' => ['label' => 'Tech Stack', 'icon' => 'fa-solid fa-layer-group'],
            ],
        ],
        [
            'type' => 'group',
            'id' => 'menuWorks',
            'label' => 'Publikasi & Karya',
            'icon' => 'fa-solid fa-graduation-cap',
            'items' => [
                'academic.php' => ['label' => 'Publikasi & Riset', 'icon' => 'fa-solid fa-book-bookmark'],
                'projects.php' => ['label' => 'Projects', 'icon' => 'fa-solid fa-laptop-code'],
                'news.php' => ['label' => 'News / Berita', 'icon' => 'fa-solid fa-newspaper'],
            ],
        ],
        [
            'type' => 'group',
            'id' => 'menuCourses',
            'label' => 'Courses & Token',
            'icon' => 'fa-solid fa-chalkboard-user',
            'items' => [
                'courses.php' => ['label' => 'Daftar Course', 'icon' => 'fa-solid fa-book-open-reader'],
                'course_tokens.php' => ['label' => 'Token Course', 'icon' => 'fa-solid fa-key'],
                'course_grades.php' => ['label' => 'Penilaian & Export Nilai', 'icon' => 'fa-solid fa-graduation-cap'],
            ],
        ],
        [
            'type' => 'single',
            'file' => 'contact.php',
            'label' => 'Pesan Kontak',
            'icon' => 'fa-solid fa-envelope',
        ],
        [
            'type' => 'single',
            'file' => 'users.php',
            'label' => 'Manajemen User',
            'icon' => 'fa-solid fa-users-gear',
        ],
    ];
    $toastIcon = ($flash['type'] ?? '') === 'danger' ? 'fa-solid fa-triangle-exclamation' : 'fa-solid fa-circle-check';
    ?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= e($title) ?> - Admin Portfolio</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/unicons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="admin.css?v=20260901-sidebar-icons">
    <link rel="icon" href="../images/favicon/logo2.png" type="image/png" />
  </head>
  <body class="admin-body">
    <div class="admin-shell">
      <aside class="admin-sidebar" id="adminSidebar">
        <a class="sidebar-brand" href="index.php">
          <span class="sidebar-brand-icon"><i class="fa-solid fa-shield-halved"></i></span>
          <span>
            <strong>Admin Portfolio</strong>
            <small>Enterprise CMS</small>
          </span>
        </a>

        <div class="sidebar-label">Menu Utama</div>
        <nav class="sidebar-nav" aria-label="Admin menu">
          <?php foreach ($menuStructure as $item): ?>
            <?php if ($item['type'] === 'single'): ?>
              <a class="sidebar-link <?= $current === $item['file'] ? 'active' : '' ?>" href="<?= e($item['file']) ?>">
                <i class="<?= e($item['icon']) ?>"></i>
                <span><?= e($item['label']) ?></span>
              </a>
            <?php elseif ($item['type'] === 'group'): ?>
              <?php
                $isGroupActive = array_key_exists($current, $item['items']);
              ?>
              <div class="sidebar-group">
                <button type="button" class="sidebar-link sidebar-group-toggle <?= $isGroupActive ? 'group-active' : '' ?> <?= $isGroupActive ? '' : 'collapsed' ?>" data-toggle="collapse" data-target="#<?= e($item['id']) ?>" aria-expanded="<?= $isGroupActive ? 'true' : 'false' ?>" aria-controls="<?= e($item['id']) ?>">
                  <i class="<?= e($item['icon']) ?>"></i>
                  <span class="flex-grow-1 text-left"><?= e($item['label']) ?></span>
                  <i class="fa-solid fa-chevron-down sidebar-arrow"></i>
                </button>
                <div class="collapse sidebar-submenu <?= $isGroupActive ? 'show' : '' ?>" id="<?= e($item['id']) ?>">
                  <?php foreach ($item['items'] as $subFile => $subLink): ?>
                    <a class="sidebar-sublink <?= $current === $subFile ? 'active' : '' ?>" href="<?= e($subFile) ?>">
                      <i class="<?= e($subLink['icon']) ?>"></i>
                      <span><?= e($subLink['label']) ?></span>
                    </a>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
          <div class="sidebar-user">
            <i class="fa-solid fa-circle-user"></i>
            <div>
              <strong><?= e(current_admin_name()) ?></strong>
              <small>Administrator</small>
            </div>
          </div>
        </div>
      </aside>

      <button class="sidebar-overlay" type="button" data-sidebar-close aria-label="Tutup menu"></button>

      <div class="admin-content">
        <header class="admin-topbar">
          <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-label="Buka menu admin">
            <i class="fa-solid fa-bars"></i>
          </button>
          <div class="admin-topbar-title">
            <small>Portfolio Control Center</small>
            <h1><?= e($title) ?></h1>
          </div>
          <div class="admin-topbar-actions">
            <a class="btn btn-sm btn-outline-secondary font-weight-bold" href="../index.php"><i class="fa-solid fa-arrow-up-right-from-square mr-1"></i> Lihat Website</a>
            <a class="btn btn-sm btn-warning font-weight-bold text-dark" href="logout.php"><i class="fa-solid fa-right-from-bracket mr-1"></i> Logout</a>
          </div>
        </header>

        <main class="admin-main">
          <div class="container-fluid admin-container">

        <?php if ($flash): ?>
          <div class="toast-stack">
            <div class="toast admin-toast" role="alert" aria-live="assertive" aria-atomic="true" data-delay="3600">
              <div class="toast-header toast-header-<?= e($flash['type']) ?>">
                <i class="<?= e($toastIcon) ?> mr-2"></i>
                <strong class="mr-auto">Notifikasi</strong>
                <small>Baru saja</small>
                <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>
              <div class="toast-body"><?= e($flash['message']) ?></div>
            </div>
          </div>
        <?php endif; ?>
<?php
}

function admin_footer(): void
{
    ?>
          </div>
        </main>
      </div>
    </div>

    <script src="../js/jquery-3.3.1.min.js"></script>
    <script src="../js/popper.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script>
      $(function () {
        $('.admin-toast').toast({ autohide: true, delay: 3600 }).toast('show');
        $('[data-sidebar-toggle]').on('click', function () {
          $('body').toggleClass('sidebar-open');
        });
        $('[data-sidebar-close]').on('click', function () {
          $('body').removeClass('sidebar-open');
        });
      });
    </script>
  </body>
</html>
<?php
}

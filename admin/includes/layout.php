<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

function admin_header(string $title): void
{
    $current = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $flash = get_admin_flash();
    $links = [
        'index.php' => ['label' => 'Dashboard', 'icon' => 'uil-window-grid'],
        'users.php' => ['label' => 'Users', 'icon' => 'uil-users-alt'],
        'profile.php' => ['label' => 'Edit Profil', 'icon' => 'uil-user-square'],
        'about.php' => ['label' => 'About', 'icon' => 'uil-user'],
        'projects.php' => ['label' => 'Projects', 'icon' => 'uil-images'],
        'news.php' => ['label' => 'News', 'icon' => 'uil-newspaper'],
        'courses.php' => ['label' => 'Courses', 'icon' => 'uil-book-open'],
        'course_tokens.php' => ['label' => 'Token Course', 'icon' => 'uil-key-skeleton'],
        'resume.php' => ['label' => 'Resume', 'icon' => 'uil-file-alt'],
        'contact.php' => ['label' => 'Contact', 'icon' => 'uil-envelope'],
    ];
    $toastIcon = ($flash['type'] ?? '') === 'danger' ? 'uil-exclamation-triangle' : 'uil-check-circle';
    ?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= e($title) ?> - Admin Portfolio</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/unicons.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="icon" href="../images/favicon/logo2.png" type="image/png" />
  </head>
  <body class="admin-body">
    <div class="admin-shell">
      <aside class="admin-sidebar" id="adminSidebar">
        <a class="sidebar-brand" href="index.php">
          <span class="sidebar-brand-icon"><i class="uil uil-shield-check"></i></span>
          <span>
            <strong>Admin Portfolio</strong>
            <small>Enterprise CMS</small>
          </span>
        </a>

        <div class="sidebar-label">Workspace</div>
        <nav class="sidebar-nav" aria-label="Admin menu">
          <?php foreach ($links as $file => $link): ?>
            <a class="sidebar-link <?= $current === $file ? 'active' : '' ?>" href="<?= e($file) ?>">
              <i class="uil <?= e($link['icon']) ?>"></i>
              <span><?= e($link['label']) ?></span>
            </a>
          <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
          <div class="sidebar-user">
            <i class="uil uil-user-circle"></i>
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
            <i class="uil uil-bars"></i>
          </button>
          <div class="admin-topbar-title">
            <small>Portfolio Control Center</small>
            <h1><?= e($title) ?></h1>
          </div>
          <div class="admin-topbar-actions">
            <a class="btn btn-sm btn-outline-secondary" href="../index.php"><i class="uil uil-external-link-alt"></i> Lihat Website</a>
            <a class="btn btn-sm btn-warning" href="logout.php"><i class="uil uil-sign-out-alt"></i> Logout</a>
          </div>
        </header>

        <main class="admin-main">
          <div class="container-fluid admin-container">

        <?php if ($flash): ?>
          <div class="toast-stack">
            <div class="toast admin-toast" role="alert" aria-live="assertive" aria-atomic="true" data-delay="3600">
              <div class="toast-header toast-header-<?= e($flash['type']) ?>">
                <i class="uil <?= e($toastIcon) ?> mr-2"></i>
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

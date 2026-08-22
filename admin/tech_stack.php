<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

require_admin();

$pdo = pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $action = (string) ($_POST['action'] ?? 'save');

    try {
        if ($action === 'save_heading') {
            save_settings([
                'tech_stack_heading' => trim((string) ($_POST['tech_stack_heading'] ?? '')),
                'tech_stack_subtitle' => trim((string) ($_POST['tech_stack_subtitle'] ?? '')),
            ]);
            set_admin_flash('success', 'Pengaturan judul Tech Stack berhasil disimpan.');
        } elseif ($action === 'delete') {
            $statement = $pdo->prepare('DELETE FROM tech_stacks WHERE id = ?');
            $statement->execute([(int) ($_POST['id'] ?? 0)]);
            set_admin_flash('success', 'Tech stack berhasil dihapus.');
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $logoPath = trim((string) ($_POST['logo_path'] ?? ''));
            $uploadedLogo = upload_tech_stack_logo($_FILES['logo_file'] ?? []);

            if ($uploadedLogo !== null) {
                $logoPath = $uploadedLogo;
            }

            if ($logoPath === '') {
                throw new RuntimeException('Logo wajib diisi (bisa upload file gambar/SVG atau isi URL CDN).');
            }

            $name = trim((string) ($_POST['name'] ?? ''));
            $category = trim((string) ($_POST['category'] ?? 'Tech'));
            $sortOrder = (int) ($_POST['sort_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($name === '') {
                throw new RuntimeException('Nama tech stack wajib diisi.');
            }

            if ($id > 0) {
                $statement = $pdo->prepare('UPDATE tech_stacks SET name = ?, category = ?, logo_path = ?, sort_order = ?, is_active = ? WHERE id = ?');
                $statement->execute([$name, $category, $logoPath, $sortOrder, $isActive, $id]);
            } else {
                $statement = $pdo->prepare('INSERT INTO tech_stacks (name, category, logo_path, sort_order, is_active) VALUES (?, ?, ?, ?, ?)');
                $statement->execute([$name, $category, $logoPath, $sortOrder, $isActive]);
            }

            set_admin_flash('success', 'Tech stack berhasil disimpan.');
        }
    } catch (Throwable $exception) {
        set_admin_flash('danger', $exception->getMessage());
    }

    redirect('tech_stack.php');
}

$settings = get_settings($pdo);
$techStacks = get_tech_stacks($pdo, false);

admin_header('Kelola Tech Stack');
?>

<div class="settings-accordion" id="techAccordion">
  <!-- Pengaturan Judul Section -->
  <div class="admin-card setting-card">
    <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#techHeadingSettings" aria-expanded="true" aria-controls="techHeadingSettings">
      <span><i class="uil uil-text"></i> Judul & Deskripsi Section <small>Heading yang tampil di atas slider Tech Stack di halaman utama.</small></span>
      <i class="uil uil-angle-down"></i>
    </button>
    <div id="techHeadingSettings" class="collapse show" data-parent="#techAccordion">
      <form method="post" action="tech_stack.php" class="settings-body">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_heading">
        <div class="form-group">
          <label for="tech_stack_heading">Heading Section</label>
          <input type="text" class="form-control" id="tech_stack_heading" name="tech_stack_heading" value="<?= e($settings['tech_stack_heading'] ?? '') ?>" placeholder="Tech Stack & Technologies">
        </div>
        <div class="form-group">
          <label for="tech_stack_subtitle">Subtitle / Deskripsi</label>
          <textarea class="form-control" id="tech_stack_subtitle" name="tech_stack_subtitle" rows="2" placeholder="Kumpulan teknologi, framework, dan tools modern yang saya gunakan..."><?= e($settings['tech_stack_subtitle'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-warning font-weight-bold"><i class="uil uil-check-circle"></i> Simpan Pengaturan</button>
      </form>
    </div>
  </div>

  <!-- Form Tambah Tech Stack Baru -->
  <div class="admin-card setting-card">
    <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#newTechSettings" aria-expanded="false" aria-controls="newTechSettings">
      <span><i class="uil uil-plus-circle"></i> Tambah Tech Stack Baru <small>Buka form ini untuk menambahkan teknologi atau tools baru ke slider.</small></span>
      <i class="uil uil-angle-down"></i>
    </button>
    <div id="newTechSettings" class="collapse" data-parent="#techAccordion">
      <form method="post" action="tech_stack.php" class="settings-body" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="0">
        <div class="row">
          <div class="form-group col-md-5">
            <label>Nama Teknologi / Tool <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="name" placeholder="Contoh: Laravel, React, Docker" required>
          </div>
          <div class="form-group col-md-4">
            <label>Kategori / Peran</label>
            <input type="text" class="form-control" name="category" placeholder="Contoh: Backend, Frontend, Database, DevOps" value="Tech">
          </div>
          <div class="form-group col-md-3">
            <label>Urutan Tampil</label>
            <input type="number" class="form-control" name="sort_order" value="0">
          </div>
        </div>
        <div class="row">
          <div class="form-group col-md-6">
            <label>Upload File Logo (PNG, SVG, JPG, WEBP)</label>
            <input type="file" class="form-control-file" name="logo_file" accept=".jpg,.jpeg,.png,.gif,.webp,.svg">
            <small class="form-text text-muted">Disarankan format SVG atau PNG transparan (maks. 2MB).</small>
          </div>
          <div class="form-group col-md-6">
            <label>Atau URL / Path Logo</label>
            <input type="text" class="form-control" name="logo_path" placeholder="https://cdn.jsdelivr.net/gh/devicons/... atau images/...">
          </div>
        </div>
        <div class="custom-control custom-checkbox mb-3">
          <input type="checkbox" class="custom-control-input" id="new_is_active" name="is_active" checked>
          <label class="custom-control-label" for="new_is_active">Aktifkan & Tampilkan di Infinity Slider</label>
        </div>
        <button type="submit" class="btn btn-warning font-weight-bold"><i class="uil uil-plus-circle"></i> Tambah Tech Stack</button>
      </form>
    </div>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3 mt-4">
  <h4 class="mb-0 font-weight-bold"><i class="uil uil-layer-group text-warning mr-1"></i> Daftar Tech Stack (<?= count($techStacks) ?>)</h4>
  <small class="text-muted">Klik pada item untuk mengedit data atau mengganti logo.</small>
</div>

<div class="settings-accordion" id="techListAccordion">
  <?php if (!$techStacks): ?>
    <div class="admin-card p-4 text-center text-muted">
      <i class="uil uil-info-circle text-warning" style="font-size: 32px;"></i>
      <div class="mt-2">Belum ada tech stack yang ditambahkan. Silakan tambahkan melalui form di atas.</div>
    </div>
  <?php else: ?>
    <?php foreach ($techStacks as $tech): ?>
      <?php
        $logoSrc = str_starts_with($tech['logo_path'], 'http://') || str_starts_with($tech['logo_path'], 'https://')
            ? $tech['logo_path']
            : '../' . ltrim($tech['logo_path'], '/');
      ?>
      <div class="admin-card setting-card mb-2">
        <button class="settings-toggle d-flex align-items-center" type="button" data-toggle="collapse" data-target="#tech_<?= e((string) $tech['id']) ?>" aria-expanded="false" aria-controls="tech_<?= e((string) $tech['id']) ?>">
          <div class="d-flex align-items-center flex-grow-1">
            <div class="mr-3 p-1 bg-light rounded d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; border: 1px solid #e2e8f0;">
              <img src="<?= e($logoSrc) ?>" alt="<?= e($tech['name']) ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
            </div>
            <span>
              <strong><?= e($tech['name']) ?></strong>
              <small class="badge badge-light border ml-2"><?= e($tech['category'] ?? 'Tech') ?></small>
              <small class="ml-2 text-muted"><?= (int) $tech['is_active'] === 1 ? '<span class="text-success font-weight-bold">● Aktif</span>' : '<span class="text-secondary">○ Disembunyikan</span>' ?> · Urutan <?= e((string) $tech['sort_order']) ?></small>
            </span>
          </div>
          <i class="uil uil-angle-down"></i>
        </button>
        <div id="tech_<?= e((string) $tech['id']) ?>" class="collapse" data-parent="#techListAccordion">
          <form method="post" action="tech_stack.php" class="settings-body" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= e((string) $tech['id']) ?>">
            <div class="row align-items-center mb-3">
              <div class="col-auto">
                <div class="p-2 bg-light rounded d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; border: 1px solid #cbd5e1;">
                  <img src="<?= e($logoSrc) ?>" alt="<?= e($tech['name']) ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                </div>
              </div>
              <div class="col">
                <small class="text-muted d-block">Preview Logo Saat Ini</small>
                <code class="small text-break"><?= e($tech['logo_path']) ?></code>
              </div>
            </div>

            <div class="row">
              <div class="form-group col-md-5">
                <label>Nama Teknologi / Tool <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name" value="<?= e($tech['name']) ?>" required>
              </div>
              <div class="form-group col-md-4">
                <label>Kategori / Peran</label>
                <input type="text" class="form-control" name="category" value="<?= e($tech['category'] ?? 'Tech') ?>">
              </div>
              <div class="form-group col-md-3">
                <label>Urutan Tampil</label>
                <input type="number" class="form-control" name="sort_order" value="<?= e((string) $tech['sort_order']) ?>">
              </div>
            </div>

            <div class="row">
              <div class="form-group col-md-6">
                <label>Ganti File Logo (PNG, SVG, JPG, WEBP)</label>
                <input type="file" class="form-control-file" name="logo_file" accept=".jpg,.jpeg,.png,.gif,.webp,.svg">
              </div>
              <div class="form-group col-md-6">
                <label>Atau Edit Path / URL Logo</label>
                <input type="text" class="form-control" name="logo_path" value="<?= e($tech['logo_path']) ?>">
              </div>
            </div>

            <div class="d-flex flex-wrap align-items-center mt-2">
              <div class="custom-control custom-checkbox mr-3 mb-2">
                <input type="checkbox" class="custom-control-input" id="active_<?= e((string) $tech['id']) ?>" name="is_active" <?= (int) $tech['is_active'] === 1 ? 'checked' : '' ?>>
                <label class="custom-control-label" for="active_<?= e((string) $tech['id']) ?>">Tampilkan di Slider</label>
              </div>
              <button type="submit" name="action" value="save" class="btn btn-warning font-weight-bold mr-2 mb-2">
                <i class="uil uil-check-circle"></i> Simpan Perubahan
              </button>
              <button type="submit" name="action" value="delete" class="btn btn-outline-danger mb-2" onclick="return confirm('Apakah Anda yakin ingin menghapus tech stack <?= e(addslashes($tech['name'])) ?>?')">
                <i class="uil uil-trash-alt"></i> Hapus
              </button>
            </div>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php
admin_footer();

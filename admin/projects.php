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
                'project_heading' => trim((string) ($_POST['project_heading'] ?? '')),
            ]);
            set_admin_flash('success', 'Judul section project berhasil disimpan.');
        } elseif ($action === 'delete') {
            $statement = $pdo->prepare('DELETE FROM projects WHERE id = ?');
            $statement->execute([(int) ($_POST['id'] ?? 0)]);
            set_admin_flash('success', 'Project berhasil dihapus.');
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $imagePath = trim((string) ($_POST['image_path'] ?? ''));
            $uploadedImage = upload_project_image($_FILES['image_file'] ?? []);

            if ($uploadedImage !== null) {
                $imagePath = $uploadedImage;
            }

            if ($imagePath === '') {
                throw new RuntimeException('Path gambar wajib diisi atau upload gambar baru.');
            }

            $data = [
                trim((string) ($_POST['title'] ?? '')),
                trim((string) ($_POST['description'] ?? '')),
                $imagePath,
                trim((string) ($_POST['link_url'] ?? '#')),
                (int) ($_POST['sort_order'] ?? 0),
                isset($_POST['is_active']) ? 1 : 0,
            ];

            if ($data[0] === '') {
                throw new RuntimeException('Judul project wajib diisi.');
            }

            if ($id > 0) {
                $statement = $pdo->prepare('UPDATE projects SET title = ?, description = ?, image_path = ?, link_url = ?, sort_order = ?, is_active = ? WHERE id = ?');
                $statement->execute([...$data, $id]);
            } else {
                $statement = $pdo->prepare('INSERT INTO projects (title, description, image_path, link_url, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)');
                $statement->execute($data);
            }

            set_admin_flash('success', 'Project berhasil disimpan.');
        }
    } catch (Throwable $exception) {
        set_admin_flash('danger', $exception->getMessage());
    }

    redirect('projects.php');
}

$settings = get_settings($pdo);
$projects = get_projects($pdo, false);

admin_header('Edit Projects');
?>
<div class="settings-accordion" id="projectAccordion">
  <div class="admin-card setting-card">
    <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#projectHeadingSettings" aria-expanded="true" aria-controls="projectHeadingSettings">
      <span><i class="uil uil-text"></i> Judul Section Project <small>Heading yang tampil di website bagian project.</small></span>
      <i class="uil uil-angle-down"></i>
    </button>
    <div id="projectHeadingSettings" class="collapse show" data-parent="#projectAccordion">
      <form method="post" action="projects.php" class="settings-body">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_heading">
        <div class="form-group">
          <label for="project_heading">Heading</label>
          <input type="text" class="form-control" id="project_heading" name="project_heading" value="<?= e($settings['project_heading'] ?? '') ?>">
        </div>
        <button type="submit" class="btn btn-warning font-weight-bold"><i class="uil uil-check-circle"></i> Simpan Heading</button>
      </form>
    </div>
  </div>

  <div class="admin-card setting-card">
    <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#newProjectSettings" aria-expanded="false" aria-controls="newProjectSettings">
      <span><i class="uil uil-plus-circle"></i> Tambah Project <small>Buka form ini untuk menambah project baru.</small></span>
      <i class="uil uil-angle-down"></i>
    </button>
    <div id="newProjectSettings" class="collapse" data-parent="#projectAccordion">
      <form method="post" action="projects.php" class="settings-body" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="0">
        <div class="row">
          <div class="form-group col-md-6">
            <label>Judul</label>
            <input type="text" class="form-control" name="title" required>
          </div>
          <div class="form-group col-md-3">
            <label>Urutan</label>
            <input type="number" class="form-control" name="sort_order" value="0">
          </div>
          <div class="form-group col-md-3">
            <label>Link project</label>
            <input type="text" class="form-control" name="link_url" value="#">
          </div>
        </div>
        <div class="form-group">
          <label>Deskripsi</label>
          <textarea class="form-control" name="description" rows="3"></textarea>
        </div>
        <div class="row">
          <div class="form-group col-md-6">
            <label>Path gambar</label>
            <input type="text" class="form-control" name="image_path" placeholder="images/project/project-baru.png">
          </div>
          <div class="form-group col-md-6">
            <label>Upload gambar</label>
            <input type="file" class="form-control-file" name="image_file" accept=".jpg,.jpeg,.png,.gif,.webp">
          </div>
        </div>
        <div class="custom-control custom-checkbox mb-3">
          <input type="checkbox" class="custom-control-input" id="new_is_active" name="is_active" checked>
          <label class="custom-control-label" for="new_is_active">Tampilkan project</label>
        </div>
        <button type="submit" class="btn btn-warning font-weight-bold"><i class="uil uil-plus-circle"></i> Tambah Project</button>
      </form>
    </div>
  </div>
</div>

<div class="settings-accordion" id="projectListAccordion">
  <?php foreach ($projects as $project): ?>
    <div class="admin-card setting-card">
      <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#project_<?= e((string) $project['id']) ?>" aria-expanded="false" aria-controls="project_<?= e((string) $project['id']) ?>">
        <span><i class="uil uil-edit"></i> <?= e($project['title']) ?> <small><?= (int) $project['is_active'] === 1 ? 'Aktif' : 'Disembunyikan' ?> · Urutan <?= e((string) $project['sort_order']) ?></small></span>
        <i class="uil uil-angle-down"></i>
      </button>
      <div id="project_<?= e((string) $project['id']) ?>" class="collapse" data-parent="#projectListAccordion">
        <form method="post" action="projects.php" class="settings-body" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= e((string) $project['id']) ?>">
          <div class="row">
            <div class="col-lg-3">
              <img class="project-preview mb-3 mb-lg-0" src="../<?= e($project['image_path']) ?>" alt="<?= e($project['title']) ?>">
            </div>
            <div class="col-lg-9">
              <div class="row">
                <div class="form-group col-md-6">
                  <label>Judul</label>
                  <input type="text" class="form-control" name="title" value="<?= e($project['title']) ?>" required>
                </div>
                <div class="form-group col-md-3">
                  <label>Urutan</label>
                  <input type="number" class="form-control" name="sort_order" value="<?= e((string) $project['sort_order']) ?>">
                </div>
                <div class="form-group col-md-3">
                  <label>Link project</label>
                  <input type="text" class="form-control" name="link_url" value="<?= e($project['link_url']) ?>">
                </div>
              </div>
              <div class="form-group">
                <label>Deskripsi</label>
                <textarea class="form-control" name="description" rows="3"><?= e($project['description']) ?></textarea>
              </div>
              <div class="row">
                <div class="form-group col-md-6">
                  <label>Path gambar</label>
                  <input type="text" class="form-control" name="image_path" value="<?= e($project['image_path']) ?>">
                </div>
                <div class="form-group col-md-6">
                  <label>Ganti gambar</label>
                  <input type="file" class="form-control-file" name="image_file" accept=".jpg,.jpeg,.png,.gif,.webp">
                </div>
              </div>
              <div class="d-flex flex-wrap align-items-center">
                <div class="custom-control custom-checkbox mr-3 mb-2">
                  <input type="checkbox" class="custom-control-input" id="active_<?= e((string) $project['id']) ?>" name="is_active" <?= (int) $project['is_active'] === 1 ? 'checked' : '' ?>>
                  <label class="custom-control-label" for="active_<?= e((string) $project['id']) ?>">Tampilkan project</label>
                </div>
                <button type="submit" name="action" value="save" class="btn btn-warning font-weight-bold mr-2 mb-2"><i class="uil uil-check-circle"></i> Simpan</button>
                <button type="submit" name="action" value="delete" class="btn btn-outline-danger mb-2" onclick="return confirm('Hapus project ini?')"><i class="uil uil-trash-alt"></i> Hapus</button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php admin_footer(); ?>

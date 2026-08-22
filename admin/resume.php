<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

require_admin();

$pdo = pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $action = (string) ($_POST['action'] ?? 'save');

    try {
        if ($action === 'delete') {
            $statement = $pdo->prepare('DELETE FROM resume_items WHERE id = ?');
            $statement->execute([(int) ($_POST['id'] ?? 0)]);
            set_admin_flash('success', 'Item resume berhasil dihapus.');
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $type = (string) ($_POST['type'] ?? 'experience');
            if (!in_array($type, ['experience', 'education'], true)) {
                $type = 'experience';
            }

            $data = [
                $type,
                trim((string) ($_POST['year_label'] ?? '')),
                trim((string) ($_POST['title'] ?? '')),
                trim((string) ($_POST['subtitle'] ?? '')),
                trim((string) ($_POST['description'] ?? '')),
                (int) ($_POST['sort_order'] ?? 0),
                isset($_POST['is_active']) ? 1 : 0,
            ];

            if ($data[1] === '' || $data[2] === '') {
                throw new RuntimeException('Tahun dan judul wajib diisi.');
            }

            if ($id > 0) {
                $statement = $pdo->prepare('UPDATE resume_items SET type = ?, year_label = ?, title = ?, subtitle = ?, description = ?, sort_order = ?, is_active = ? WHERE id = ?');
                $statement->execute([...$data, $id]);
            } else {
                $statement = $pdo->prepare('INSERT INTO resume_items (type, year_label, title, subtitle, description, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $statement->execute($data);
            }

            set_admin_flash('success', 'Item resume berhasil disimpan.');
        }
    } catch (Throwable $exception) {
        set_admin_flash('danger', $exception->getMessage());
    }

    redirect('resume.php');
}

$items = get_resume_items($pdo, null, false);

admin_header('Edit Resume');
?>
<div class="settings-accordion" id="resumeAccordion">
  <div class="admin-card setting-card">
    <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#newResumeSettings" aria-expanded="true" aria-controls="newResumeSettings">
      <span><i class="uil uil-plus-circle"></i> Tambah Item Resume <small>Tambahkan pengalaman kerja atau pendidikan baru.</small></span>
      <i class="uil uil-angle-down"></i>
    </button>
    <div id="newResumeSettings" class="collapse show" data-parent="#resumeAccordion">
      <form method="post" action="resume.php" class="settings-body">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="0">
        <div class="row">
          <div class="form-group col-md-3">
            <label>Tipe</label>
            <select class="form-control" name="type">
              <option value="experience">Experience</option>
              <option value="education">Education</option>
            </select>
          </div>
          <div class="form-group col-md-3">
            <label>Tahun</label>
            <input type="text" class="form-control" name="year_label" placeholder="2026" required>
          </div>
          <div class="form-group col-md-4">
            <label>Judul</label>
            <input type="text" class="form-control" name="title" required>
          </div>
          <div class="form-group col-md-2">
            <label>Urutan</label>
            <input type="number" class="form-control" name="sort_order" value="0">
          </div>
        </div>
        <div class="form-group">
          <label>Subjudul / tempat</label>
          <input type="text" class="form-control" name="subtitle">
        </div>
        <div class="form-group">
          <label>Deskripsi</label>
          <textarea class="form-control" name="description" rows="3"></textarea>
        </div>
        <div class="custom-control custom-checkbox mb-3">
          <input type="checkbox" class="custom-control-input" id="new_resume_active" name="is_active" checked>
          <label class="custom-control-label" for="new_resume_active">Tampilkan item</label>
        </div>
        <button type="submit" class="btn btn-warning font-weight-bold"><i class="uil uil-plus-circle"></i> Tambah Item</button>
      </form>
    </div>
  </div>
</div>

<div class="settings-accordion" id="resumeListAccordion">
  <?php foreach ($items as $item): ?>
    <div class="admin-card setting-card">
      <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#resume_<?= e((string) $item['id']) ?>" aria-expanded="false" aria-controls="resume_<?= e((string) $item['id']) ?>">
        <span><i class="uil uil-edit"></i> <?= e($item['title']) ?> <small><?= e(ucfirst($item['type'])) ?> · <?= e($item['year_label']) ?> · Urutan <?= e((string) $item['sort_order']) ?></small></span>
        <i class="uil uil-angle-down"></i>
      </button>
      <div id="resume_<?= e((string) $item['id']) ?>" class="collapse" data-parent="#resumeListAccordion">
        <form method="post" action="resume.php" class="settings-body">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
          <div class="row">
            <div class="form-group col-md-3">
              <label>Tipe</label>
              <select class="form-control" name="type">
                <option value="experience" <?= $item['type'] === 'experience' ? 'selected' : '' ?>>Experience</option>
                <option value="education" <?= $item['type'] === 'education' ? 'selected' : '' ?>>Education</option>
              </select>
            </div>
            <div class="form-group col-md-3">
              <label>Tahun</label>
              <input type="text" class="form-control" name="year_label" value="<?= e($item['year_label']) ?>" required>
            </div>
            <div class="form-group col-md-4">
              <label>Judul</label>
              <input type="text" class="form-control" name="title" value="<?= e($item['title']) ?>" required>
            </div>
            <div class="form-group col-md-2">
              <label>Urutan</label>
              <input type="number" class="form-control" name="sort_order" value="<?= e((string) $item['sort_order']) ?>">
            </div>
          </div>
          <div class="form-group">
            <label>Subjudul / tempat</label>
            <input type="text" class="form-control" name="subtitle" value="<?= e($item['subtitle']) ?>">
          </div>
          <div class="form-group">
            <label>Deskripsi</label>
            <textarea class="form-control" name="description" rows="3"><?= e($item['description']) ?></textarea>
          </div>
          <div class="d-flex flex-wrap align-items-center">
            <div class="custom-control custom-checkbox mr-3 mb-2">
              <input type="checkbox" class="custom-control-input" id="resume_active_<?= e((string) $item['id']) ?>" name="is_active" <?= (int) $item['is_active'] === 1 ? 'checked' : '' ?>>
              <label class="custom-control-label" for="resume_active_<?= e((string) $item['id']) ?>">Tampilkan item</label>
            </div>
            <button type="submit" name="action" value="save" class="btn btn-warning font-weight-bold mr-2 mb-2"><i class="uil uil-check-circle"></i> Simpan</button>
            <button type="submit" name="action" value="delete" class="btn btn-outline-danger mb-2" onclick="return confirm('Hapus item resume ini?')"><i class="uil uil-trash-alt"></i> Hapus</button>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php admin_footer(); ?>

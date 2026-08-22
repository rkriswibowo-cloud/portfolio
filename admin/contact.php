<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

require_admin();

$pdo = pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $action = (string) ($_POST['action'] ?? 'save_settings');

    try {
        if ($action === 'delete_message') {
            $statement = $pdo->prepare('DELETE FROM contact_messages WHERE id = ?');
            $statement->execute([(int) ($_POST['id'] ?? 0)]);
            set_admin_flash('success', 'Pesan berhasil dihapus.');
        } else {
            $mapUrl = trim((string) ($_POST['contact_map_url'] ?? ''));
            if (preg_match('/src=["\']([^"\']+)["\']/', $mapUrl, $matches)) {
                $mapUrl = $matches[1];
            }

            save_settings([
                'contact_heading' => trim((string) ($_POST['contact_heading'] ?? '')),
                'contact_map_url' => $mapUrl,
                'contact_phone' => trim((string) ($_POST['contact_phone'] ?? '')),
                'contact_email' => trim((string) ($_POST['contact_email'] ?? '')),
                'social_dribbble' => trim((string) ($_POST['social_dribbble'] ?? '')),
                'social_instagram' => trim((string) ($_POST['social_instagram'] ?? '')),
                'social_youtube' => trim((string) ($_POST['social_youtube'] ?? '')),
            ]);
            set_admin_flash('success', 'Konten contact berhasil disimpan.');
        }
    } catch (Throwable $exception) {
        set_admin_flash('danger', $exception->getMessage());
    }

    redirect('contact.php');
}

$settings = get_settings($pdo);
$messages = $pdo->query('SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 50')->fetchAll();

admin_header('Edit Contact');
?>
<div class="settings-accordion" id="contactAccordion">
  <div class="admin-card setting-card">
    <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#contactInfoSettings" aria-expanded="true" aria-controls="contactInfoSettings">
      <span><i class="uil uil-phone"></i> Informasi Contact <small>Heading, Google Maps, telepon, email, dan social link.</small></span>
      <i class="uil uil-angle-down"></i>
    </button>
    <div id="contactInfoSettings" class="collapse show" data-parent="#contactAccordion">
      <form method="post" action="contact.php" class="settings-body">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_settings">
        <div class="form-group">
          <label for="contact_heading">Heading form contact</label>
          <input type="text" class="form-control" id="contact_heading" name="contact_heading" value="<?= e($settings['contact_heading'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="contact_map_url">URL embed Google Maps</label>
          <textarea class="form-control" id="contact_map_url" name="contact_map_url" rows="3"><?= e($settings['contact_map_url'] ?? '') ?></textarea>
        </div>
        <div class="row">
          <div class="form-group col-md-6">
            <label for="contact_phone">Nomor telepon</label>
            <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="<?= e($settings['contact_phone'] ?? '') ?>">
          </div>
          <div class="form-group col-md-6">
            <label for="contact_email">Email</label>
            <input type="email" class="form-control" id="contact_email" name="contact_email" value="<?= e($settings['contact_email'] ?? '') ?>">
          </div>
        </div>
        <div class="row">
          <div class="form-group col-md-4">
            <label for="social_dribbble">Dribbble</label>
            <input type="text" class="form-control" id="social_dribbble" name="social_dribbble" value="<?= e($settings['social_dribbble'] ?? '') ?>">
          </div>
          <div class="form-group col-md-4">
            <label for="social_instagram">Instagram</label>
            <input type="text" class="form-control" id="social_instagram" name="social_instagram" value="<?= e($settings['social_instagram'] ?? '') ?>">
          </div>
          <div class="form-group col-md-4">
            <label for="social_youtube">Youtube</label>
            <input type="text" class="form-control" id="social_youtube" name="social_youtube" value="<?= e($settings['social_youtube'] ?? '') ?>">
          </div>
        </div>
        <button type="submit" class="btn btn-warning font-weight-bold"><i class="uil uil-check-circle"></i> Simpan Contact</button>
      </form>
    </div>
  </div>

  <div class="admin-card setting-card">
    <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#contactMessages" aria-expanded="false" aria-controls="contactMessages">
      <span><i class="uil uil-envelope"></i> Pesan Masuk <small><?= e((string) count($messages)) ?> pesan terbaru dari form contact.</small></span>
      <i class="uil uil-angle-down"></i>
    </button>
    <div id="contactMessages" class="collapse" data-parent="#contactAccordion">
      <div class="settings-body">
        <?php if (!$messages): ?>
          <p class="mb-0 text-muted">Belum ada pesan masuk.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-striped message-table">
              <thead>
                <tr>
                  <th>Nama</th>
                  <th>Email</th>
                  <th>Pesan</th>
                  <th>Tanggal</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($messages as $message): ?>
                  <tr>
                    <td><?= e($message['name']) ?></td>
                    <td><a href="mailto:<?= e($message['email']) ?>"><?= e($message['email']) ?></a></td>
                    <td><?= nl2br(e($message['message'])) ?></td>
                    <td><?= e($message['created_at']) ?></td>
                    <td>
                      <form method="post" action="contact.php">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete_message">
                        <input type="hidden" name="id" value="<?= e((string) $message['id']) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus pesan ini?')"><i class="uil uil-trash-alt"></i> Hapus</button>
                      </form>
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
</div>
<?php admin_footer(); ?>

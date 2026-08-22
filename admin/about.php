<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    save_settings([
        'site_brand' => trim((string) ($_POST['site_brand'] ?? '')),
        'page_title' => trim((string) ($_POST['page_title'] ?? '')),
        'about_welcome' => trim((string) ($_POST['about_welcome'] ?? '')),
        'about_prefix' => trim((string) ($_POST['about_prefix'] ?? '')),
        'about_name' => trim((string) ($_POST['about_name'] ?? '')),
        'about_role_1' => trim((string) ($_POST['about_role_1'] ?? '')),
        'about_role_2' => trim((string) ($_POST['about_role_2'] ?? '')),
        'about_role_3' => trim((string) ($_POST['about_role_3'] ?? '')),
        'about_description' => trim((string) ($_POST['about_description'] ?? '')),
        'about_image' => trim((string) ($_POST['about_image'] ?? '')),
        'resume_file_url' => trim((string) ($_POST['resume_file_url'] ?? '')),
        'quote_button_text' => trim((string) ($_POST['quote_button_text'] ?? '')),
        'footer_company' => trim((string) ($_POST['footer_company'] ?? '')),
    ]);

    set_admin_flash('success', 'Konten about berhasil disimpan.');
    redirect('about.php');
}

$settings = get_settings(pdo());

admin_header('Edit About');
?>
<form method="post" action="about.php">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

  <div class="settings-accordion" id="aboutAccordion">
    <div class="admin-card setting-card">
      <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#identitySettings" aria-expanded="true" aria-controls="identitySettings">
        <span><i class="uil uil-globe"></i> Identitas Website <small>Brand, judul tab browser, dan copyright.</small></span>
        <i class="uil uil-angle-down"></i>
      </button>
      <div id="identitySettings" class="collapse show" data-parent="#aboutAccordion">
        <div class="settings-body">
          <div class="row">
            <div class="form-group col-md-6">
              <label for="site_brand">Nama brand</label>
              <input type="text" class="form-control" id="site_brand" name="site_brand" value="<?= e($settings['site_brand'] ?? '') ?>" required>
            </div>
            <div class="form-group col-md-6">
              <label for="page_title">Judul tab browser</label>
              <input type="text" class="form-control" id="page_title" name="page_title" value="<?= e($settings['page_title'] ?? '') ?>" required>
            </div>
          </div>
          <div class="form-group mb-0">
            <label for="footer_company">Nama copyright footer</label>
            <input type="text" class="form-control" id="footer_company" name="footer_company" value="<?= e($settings['footer_company'] ?? '') ?>">
          </div>
        </div>
      </div>
    </div>

    <div class="admin-card setting-card">
      <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#aboutContentSettings" aria-expanded="false" aria-controls="aboutContentSettings">
        <span><i class="uil uil-user"></i> Konten About <small>Nama, role animasi, deskripsi, gambar, dan tombol resume.</small></span>
        <i class="uil uil-angle-down"></i>
      </button>
      <div id="aboutContentSettings" class="collapse" data-parent="#aboutAccordion">
        <div class="settings-body">
          <div class="form-group">
            <label for="about_welcome">Teks kecil</label>
            <input type="text" class="form-control" id="about_welcome" name="about_welcome" value="<?= e($settings['about_welcome'] ?? '') ?>">
          </div>
          <div class="row">
            <div class="form-group col-md-6">
              <label for="about_prefix">Teks pembuka</label>
              <input type="text" class="form-control" id="about_prefix" name="about_prefix" value="<?= e($settings['about_prefix'] ?? '') ?>">
            </div>
            <div class="form-group col-md-6">
              <label for="about_name">Nama</label>
              <input type="text" class="form-control" id="about_name" name="about_name" value="<?= e($settings['about_name'] ?? '') ?>">
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-4">
              <label for="about_role_1">Role 1</label>
              <input type="text" class="form-control" id="about_role_1" name="about_role_1" value="<?= e($settings['about_role_1'] ?? '') ?>">
            </div>
            <div class="form-group col-md-4">
              <label for="about_role_2">Role 2</label>
              <input type="text" class="form-control" id="about_role_2" name="about_role_2" value="<?= e($settings['about_role_2'] ?? '') ?>">
            </div>
            <div class="form-group col-md-4">
              <label for="about_role_3">Role 3</label>
              <input type="text" class="form-control" id="about_role_3" name="about_role_3" value="<?= e($settings['about_role_3'] ?? '') ?>">
            </div>
          </div>
          <div class="form-group">
            <label for="about_description">Deskripsi</label>
            <textarea class="form-control" id="about_description" name="about_description" rows="4"><?= e($settings['about_description'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label for="about_image">Path gambar about</label>
            <input type="text" class="form-control" id="about_image" name="about_image" value="<?= e($settings['about_image'] ?? '') ?>">
          </div>
          <div class="row">
            <div class="form-group col-md-6">
              <label for="resume_file_url">URL download resume</label>
              <input type="text" class="form-control" id="resume_file_url" name="resume_file_url" value="<?= e($settings['resume_file_url'] ?? '') ?>">
            </div>
            <div class="form-group col-md-6">
              <label for="quote_button_text">Teks tombol contact</label>
              <input type="text" class="form-control" id="quote_button_text" name="quote_button_text" value="<?= e($settings['quote_button_text'] ?? '') ?>">
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-warning font-weight-bold"><i class="uil uil-check-circle"></i> Simpan About</button>
</form>
<?php admin_footer(); ?>

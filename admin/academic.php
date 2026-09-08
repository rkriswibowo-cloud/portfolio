<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

require_admin();

$pdo = pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $action = (string) ($_POST['action'] ?? 'save');
    $currentCat = (string) ($_POST['current_cat'] ?? 'all');
    $currentPage = (int) ($_POST['current_page'] ?? 1);

    try {
        if ($action === 'save_settings') {
            save_settings([
                'academic_heading' => trim((string) ($_POST['academic_heading'] ?? '')),
                'academic_subtitle' => trim((string) ($_POST['academic_subtitle'] ?? '')),
                'academic_profile_title' => trim((string) ($_POST['academic_profile_title'] ?? '')),
                'academic_profile_subtitle' => trim((string) ($_POST['academic_profile_subtitle'] ?? '')),
                'academic_email' => trim((string) ($_POST['academic_email'] ?? '')),
                'academic_linkedin' => trim((string) ($_POST['academic_linkedin'] ?? '')),
                'academic_scholar' => trim((string) ($_POST['academic_scholar'] ?? '')),
                'academic_scopus' => trim((string) ($_POST['academic_scopus'] ?? '')),
                'academic_sinta' => trim((string) ($_POST['academic_sinta'] ?? '')),
            ]);
            set_admin_flash('success', 'Pengaturan profil akademik & tautan berhasil disimpan.');
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            delete_academic_record($id, $pdo);
            set_admin_flash('success', 'Data akademik berhasil dihapus.');
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $category = trim((string) ($_POST['category'] ?? 'publikasi'));
            $subcategory = trim((string) ($_POST['subcategory'] ?? ''));
            $title = trim((string) ($_POST['title'] ?? ''));
            $authors = trim((string) ($_POST['authors'] ?? ''));
            $journalMeta = trim((string) ($_POST['journal_meta'] ?? ''));
            $url = trim((string) ($_POST['url'] ?? ''));
            $year = trim((string) ($_POST['year'] ?? ''));
            $sortOrder = (int) ($_POST['sort_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($title === '') {
                throw new RuntimeException('Judul karya/riset/publikasi wajib diisi.');
            }

            if ($category === 'publikasi') {
                if (!in_array($subcategory, ['Jurnal Internasional', 'Prosiding Internasional', 'Jurnal Nasional'], true)) {
                    $subcategory = 'Jurnal Internasional';
                }
            } elseif ($category === 'hki') {
                if ($subcategory === '' || in_array($subcategory, ['Jurnal Internasional', 'Prosiding Internasional', 'Jurnal Nasional'], true)) {
                    $subcategory = 'Hak Cipta';
                }
            } elseif ($category === 'buku') {
                if ($subcategory === '' || in_array($subcategory, ['Jurnal Internasional', 'Prosiding Internasional', 'Jurnal Nasional'], true)) {
                    $subcategory = 'Buku Referensi';
                }
            } elseif ($category === 'riset') {
                $subcategory = 'Penelitian';
            } elseif ($category === 'pengabdian') {
                $subcategory = 'Pengabdian Masyarakat';
            }

            save_academic_record([
                'id' => $id,
                'category' => $category,
                'subcategory' => $subcategory,
                'title' => $title,
                'authors' => $authors,
                'journal_meta' => $journalMeta,
                'url' => $url,
                'year' => $year,
                'sort_order' => $sortOrder,
                'is_active' => $isActive,
            ], $pdo);

            set_admin_flash('success', $id > 0 ? 'Data karya akademik berhasil diperbarui.' : 'Data karya akademik baru berhasil ditambahkan.');
        }
    } catch (Throwable $exception) {
        set_admin_flash('danger', $exception->getMessage());
    }

    redirect('academic.php?cat=' . urlencode($currentCat) . '&page=' . max(1, $currentPage));
}

// Auto-repair subcategories in database if any mismatched entries exist
try {
    if ($pdo) {
        $pdo->exec("UPDATE academic_records SET subcategory = 'Hak Cipta' WHERE category = 'hki' AND (subcategory = 'Jurnal Internasional' OR subcategory = '' OR subcategory IS NULL)");
        $pdo->exec("UPDATE academic_records SET subcategory = 'Buku Referensi' WHERE category = 'buku' AND (subcategory = 'Jurnal Internasional' OR subcategory = '' OR subcategory IS NULL)");
        $pdo->exec("UPDATE academic_records SET subcategory = 'Penelitian' WHERE category = 'riset' AND (subcategory = 'Jurnal Internasional' OR subcategory = '' OR subcategory IS NULL)");
        $pdo->exec("UPDATE academic_records SET subcategory = 'Pengabdian Masyarakat' WHERE category = 'pengabdian' AND (subcategory = 'Jurnal Internasional' OR subcategory = '' OR subcategory IS NULL)");
    }
} catch (Throwable $e) {
    // ignore
}

$settings = get_settings($pdo);
$allRecords = get_academic_records($pdo, null, false);

// Subcategory configuration map
$subcatMap = [
    'publikasi' => ['Jurnal Internasional', 'Prosiding Internasional', 'Jurnal Nasional'],
    'hki' => ['Hak Cipta', 'Paten', 'Paten Sederhana', 'Desain Industri', 'Merek'],
    'buku' => ['Buku Referensi', 'Buku Ajar', 'Monograf', 'Book Chapter'],
];

// Hitung total per kategori
$counts = [
    'all' => count($allRecords),
    'publikasi' => count(array_filter($allRecords, fn($i) => ($i['category'] ?? '') === 'publikasi')),
    'riset' => count(array_filter($allRecords, fn($i) => ($i['category'] ?? '') === 'riset')),
    'pengabdian' => count(array_filter($allRecords, fn($i) => ($i['category'] ?? '') === 'pengabdian')),
    'hki' => count(array_filter($allRecords, fn($i) => ($i['category'] ?? '') === 'hki')),
    'buku' => count(array_filter($allRecords, fn($i) => ($i['category'] ?? '') === 'buku')),
];

$initCat = (string) ($_GET['cat'] ?? 'all');
if (!in_array($initCat, ['all', 'publikasi', 'riset', 'pengabdian', 'hki', 'buku'], true)) {
    $initCat = 'all';
}
$initPage = max(1, (int) ($_GET['page'] ?? 1));

admin_header('Kelola Publikasi & Riset');
?>

<div class="settings-accordion" id="academicAccordion">

  <!-- Pengaturan Profil Akademik & Tautan Eksternal -->
  <div class="admin-card setting-card">
    <button class="settings-toggle collapsed" type="button" data-toggle="collapse" data-target="#academicProfileSettings" aria-expanded="false" aria-controls="academicProfileSettings">
      <span><i class="fa-solid fa-link mr-1"></i> Profil Akademik & Tautan Eksternal <small>Kelola link Email, LinkedIn, Google Scholar, Scopus, dan SINTA Dikti.</small></span>
      <i class="uil uil-angle-down"></i>
    </button>
    <div id="academicProfileSettings" class="collapse" data-parent="#academicAccordion">
      <form method="post" action="academic.php" class="settings-body">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_settings">
        <input type="hidden" name="current_cat" value="<?= e($initCat) ?>">
        <input type="hidden" name="current_page" value="<?= $initPage ?>">

        <div class="row">
          <div class="form-group col-md-6">
            <label for="academic_heading">Judul Section Utama</label>
            <input type="text" class="form-control" id="academic_heading" name="academic_heading" value="<?= e($settings['academic_heading'] ?? 'Publikasi & Riset') ?>" required>
          </div>
          <div class="form-group col-md-6">
            <label for="academic_subtitle">Subtitle Section</label>
            <input type="text" class="form-control" id="academic_subtitle" name="academic_subtitle" value="<?= e($settings['academic_subtitle'] ?? '') ?>">
          </div>
        </div>

        <div class="row">
          <div class="form-group col-md-6">
            <label for="academic_profile_title">Judul Card Profil (Kiri)</label>
            <input type="text" class="form-control" id="academic_profile_title" name="academic_profile_title" value="<?= e($settings['academic_profile_title'] ?? 'Profil Akademik') ?>">
          </div>
          <div class="form-group col-md-6">
            <label for="academic_profile_subtitle">Keterangan Singkat Card Profil</label>
            <input type="text" class="form-control" id="academic_profile_subtitle" name="academic_profile_subtitle" value="<?= e($settings['academic_profile_subtitle'] ?? 'Profil Peneliti & Tautan Publikasi Terindeks') ?>">
          </div>
        </div>

        <hr class="my-3">
        <h6 class="font-weight-bold text-dark mb-3"><i class="fa-solid fa-arrow-up-right-from-square mr-1"></i> Tautan Profil Eksternal (Mode Buka Tab Baru)</h6>

        <div class="row">
          <div class="form-group col-md-6">
            <label for="academic_email"><i class="fa-solid fa-envelope text-primary mr-1"></i> Email Pribadi</label>
            <input type="email" class="form-control" id="academic_email" name="academic_email" value="<?= e($settings['academic_email'] ?? '') ?>" placeholder="author@univ.ac.id">
          </div>
          <div class="form-group col-md-6">
            <label for="academic_linkedin"><i class="fa-brands fa-linkedin text-primary mr-1"></i> URL LinkedIn</label>
            <input type="url" class="form-control" id="academic_linkedin" name="academic_linkedin" value="<?= e($settings['academic_linkedin'] ?? '') ?>" placeholder="https://www.linkedin.com/in/username">
          </div>
        </div>

        <div class="row">
          <div class="form-group col-md-4">
            <label for="academic_scholar"><i class="fa-brands fa-google-scholar text-warning mr-1"></i> URL Google Scholar</label>
            <input type="url" class="form-control" id="academic_scholar" name="academic_scholar" value="<?= e($settings['academic_scholar'] ?? '') ?>" placeholder="https://scholar.google.com/citations?user=...">
          </div>
          <div class="form-group col-md-4">
            <label for="academic_scopus"><i class="fa-solid fa-award text-success mr-1"></i> URL Scopus Author</label>
            <input type="url" class="form-control" id="academic_scopus" name="academic_scopus" value="<?= e($settings['academic_scopus'] ?? '') ?>" placeholder="https://www.scopus.com/authid/detail.uri?authorId=...">
          </div>
          <div class="form-group col-md-4">
            <label for="academic_sinta"><i class="fa-solid fa-certificate text-info mr-1"></i> URL SINTA Dikti</label>
            <input type="url" class="form-control" id="academic_sinta" name="academic_sinta" value="<?= e($settings['academic_sinta'] ?? '') ?>" placeholder="https://sinta.kemdikbud.go.id/authors/profile/...">
          </div>
        </div>

        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-primary"><i class="uil uil-save mr-1"></i> Simpan Profil & Tautan</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Form Tambah Item Baru -->
  <div class="admin-card setting-card">
    <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#newAcademicItem" aria-expanded="true" aria-controls="newAcademicItem">
      <span><i class="fa-solid fa-circle-plus text-success mr-1"></i> Tambah Data Publikasi / Riset / Pengabdian / HKI / Buku <small>Tambahkan entri data baru ke salah satu kategori.</small></span>
      <i class="uil uil-angle-down"></i>
    </button>
    <div id="newAcademicItem" class="collapse show" data-parent="#academicAccordion">
      <form method="post" action="academic.php" class="settings-body">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="0">
        <input type="hidden" name="current_cat" id="formCurrentCat" value="<?= e($initCat) ?>">
        <input type="hidden" name="current_page" id="formCurrentPage" value="<?= $initPage ?>">

        <div class="row">
          <div class="form-group col-md-4">
            <label class="font-weight-bold">Kategori Data <span class="text-danger">*</span></label>
            <select class="form-control" name="category" id="newItemCategory" onchange="handleCategoryChange(this.value, 'newSubcatWrap', 'newSubcatSelect', 'newSubcatLabel', 'newMetaLabel', 'newUrlLabel', 'newUrlHint')">
              <option value="publikasi" <?= $initCat === 'publikasi' ? 'selected' : '' ?>>Publikasi</option>
              <option value="riset" <?= $initCat === 'riset' ? 'selected' : '' ?>>Riset</option>
              <option value="pengabdian" <?= $initCat === 'pengabdian' ? 'selected' : '' ?>>Pengabdian</option>
              <option value="hki" <?= $initCat === 'hki' ? 'selected' : '' ?>>HKI / Paten</option>
              <option value="buku" <?= $initCat === 'buku' ? 'selected' : '' ?>>Buku</option>
            </select>
          </div>

          <?php
            $initActiveCat = in_array($initCat, ['publikasi', 'hki', 'buku'], true) ? $initCat : 'publikasi';
            $initSubcatOpts = $subcatMap[$initActiveCat] ?? $subcatMap['publikasi'];
            $initSubcatLabel = match($initCat) {
                'hki' => 'Jenis HKI / Hak Cipta / Paten',
                'buku' => 'Kategori / Jenis Buku',
                default => 'Subkategori Header (Publikasi)',
            };
            $showInitSubcat = in_array($initCat, ['all', 'publikasi', 'hki', 'buku'], true);
          ?>
          <div class="form-group col-md-4" id="newSubcatWrap" style="<?= !$showInitSubcat ? 'display:none;' : '' ?>">
            <label class="font-weight-bold" id="newSubcatLabel"><?= $initSubcatLabel ?></label>
            <select class="form-control" name="subcategory" id="newSubcatSelect">
              <?php foreach ($initSubcatOpts as $opt): ?>
                <option value="<?= e($opt) ?>"><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group col-md-2">
            <label class="font-weight-bold">Tahun</label>
            <input type="text" class="form-control" name="year" placeholder="2026">
          </div>

          <div class="form-group col-md-2">
            <label class="font-weight-bold">Urutan Tampil</label>
            <input type="number" class="form-control" name="sort_order" value="0">
          </div>
        </div>

        <div class="form-group">
          <label class="font-weight-bold">Judul <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="title" placeholder="Masukkan judul artikel jurnal, riset, pengabdian, paten, atau buku..." required>
        </div>

        <div class="form-group">
          <label class="font-weight-bold">Author / Penulis / Tim Peneliti / Inventor</label>
          <input type="text" class="form-control" name="authors" placeholder="Contoh: Qomariyah, F., Utaminingrum, F., Mahmudy, W. F.">
        </div>

        <div class="row">
          <div class="form-group col-md-7">
            <label class="font-weight-bold" id="newMetaLabel">Metadata / Sitasi / No. Paten / Penerbit</label>
            <input type="text" class="form-control" name="journal_meta" placeholder="Contoh: (2024), Journal Name Vol. 10 / No. Paten: EC002021... / Penerbit: UB Press">
          </div>
          <div class="form-group col-md-5">
            <label class="font-weight-bold" id="newUrlLabel"><i class="fa-solid fa-link text-primary mr-1"></i> Link URL / Google Drive / DOI / Dokumen</label>
            <input type="url" class="form-control" name="url" id="newUrlInput" placeholder="https://drive.google.com/... atau https://...">
            <small class="form-text text-muted" id="newUrlHint">Bisa diisi link Google Drive (PDF Buku, Sertifikat HKI, Dokumen), DOI, atau URL website.</small>
          </div>
        </div>

        <div class="custom-control custom-switch mb-3">
          <input type="checkbox" class="custom-control-input" id="new_is_active" name="is_active" value="1" checked>
          <label class="custom-control-label font-weight-bold" for="new_is_active">Aktifkan data ini di halaman utama website</label>
        </div>

        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-success px-4 font-weight-bold"><i class="fa-solid fa-plus mr-1"></i> Simpan Data Baru</button>
        </div>
      </form>
    </div>
  </div>

</div>

<!-- Daftar Item Tersimpan dengan Tab Filter & Client Pagination (10 item/page) -->
<div class="admin-card mt-4" id="academicListCard">
  <div class="admin-card-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
      <div>
        <h3 class="mb-1"><i class="fa-solid fa-list-check mr-2"></i> Daftar Publikasi, Riset & Karya</h3>
        <small class="text-muted">Kelola data per kategori dengan navigasi halaman (10 data per halaman)</small>
      </div>
      <div>
        <span class="badge badge-pill badge-secondary px-3 py-2">Total: <?= $counts['all'] ?> data</span>
      </div>
    </div>

    <!-- Filter Pills Button (No Page Jump / No Scroll Up) -->
    <div class="d-flex flex-wrap" style="gap: 6px;" id="catFilterPills">
      <button type="button" class="btn btn-sm cat-filter-btn <?= $initCat === 'all' ? 'btn-dark' : 'btn-outline-secondary' ?> font-weight-bold px-3 py-2" data-cat="all">
        <i class="fa-solid fa-layer-group mr-1"></i> Semua <span class="badge badge-light ml-1"><?= $counts['all'] ?></span>
      </button>
      <button type="button" class="btn btn-sm cat-filter-btn <?= $initCat === 'publikasi' ? 'btn-primary' : 'btn-outline-secondary' ?> font-weight-bold px-3 py-2" data-cat="publikasi">
        <i class="fa-solid fa-newspaper mr-1"></i> Publikasi <span class="badge badge-light ml-1"><?= $counts['publikasi'] ?></span>
      </button>
      <button type="button" class="btn btn-sm cat-filter-btn <?= $initCat === 'riset' ? 'btn-info' : 'btn-outline-secondary' ?> font-weight-bold px-3 py-2" data-cat="riset">
        <i class="fa-solid fa-microscope mr-1"></i> Riset <span class="badge badge-light ml-1"><?= $counts['riset'] ?></span>
      </button>
      <button type="button" class="btn btn-sm cat-filter-btn <?= $initCat === 'pengabdian' ? 'btn-success' : 'btn-outline-secondary' ?> font-weight-bold px-3 py-2" data-cat="pengabdian">
        <i class="fa-solid fa-hand-holding-heart mr-1"></i> Pengabdian <span class="badge badge-light ml-1"><?= $counts['pengabdian'] ?></span>
      </button>
      <button type="button" class="btn btn-sm cat-filter-btn <?= $initCat === 'hki' ? 'btn-warning text-dark' : 'btn-outline-secondary' ?> font-weight-bold px-3 py-2" data-cat="hki">
        <i class="fa-solid fa-shield-halved mr-1"></i> HKI/Paten <span class="badge badge-light ml-1"><?= $counts['hki'] ?></span>
      </button>
      <button type="button" class="btn btn-sm cat-filter-btn <?= $initCat === 'buku' ? 'btn-secondary' : 'btn-outline-secondary' ?> font-weight-bold px-3 py-2" data-cat="buku">
        <i class="fa-solid fa-book mr-1"></i> Buku <span class="badge badge-light ml-1"><?= $counts['buku'] ?></span>
      </button>
    </div>
  </div>

  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0" id="academicTable">
        <thead class="thead-light">
          <tr>
            <th style="width: 50px;">#</th>
            <th style="width: 140px;">Kategori</th>
            <th>Judul & Author</th>
            <th style="width: 250px;">Metadata / Link</th>
            <th style="width: 80px;" class="text-center">Status</th>
            <th style="width: 130px;" class="text-right">Aksi</th>
          </tr>
        </thead>
        <tbody id="academicTableBody">
          <?php if (empty($allRecords)): ?>
            <tr id="emptyRowGlobal">
              <td colspan="6" class="text-center py-5 text-muted">
                <i class="fa-regular fa-folder-open mb-2" style="font-size: 32px; display: block;"></i>
                Belum ada data karya akademik tersimpan.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($allRecords as $idx => $item): ?>
              <?php
                $cat = $item['category'] ?? 'publikasi';
                $badgeClass = match($cat) {
                  'publikasi' => 'badge-primary',
                  'riset' => 'badge-info',
                  'pengabdian' => 'badge-success',
                  'hki' => 'badge-warning',
                  'buku' => 'badge-secondary',
                  default => 'badge-light',
                };
              ?>
              <tr class="academic-row" data-category="<?= e($cat) ?>" data-id="<?= (int) $item['id'] ?>">
                <td class="font-weight-bold text-muted row-idx"><?= $idx + 1 ?></td>
                <td>
                  <span class="badge <?= $badgeClass ?> text-uppercase"><?= e($cat) ?></span>
                  <?php if (!empty($item['subcategory'])): ?>
                    <div class="small text-muted mt-1 font-weight-bold"><?= e($item['subcategory']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="font-weight-bold text-dark mb-1" style="font-size: 15px;"><?= e($item['title']) ?></div>
                  <?php if (!empty($item['authors'])): ?>
                    <div class="small text-muted"><i class="fa-solid fa-user-pen mr-1"></i><?= e($item['authors']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!empty($item['journal_meta'])): ?>
                    <div class="small text-truncate text-muted" style="max-width: 240px;" title="<?= e($item['journal_meta']) ?>"><?= e($item['journal_meta']) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($item['url']) && $item['url'] !== '#'): ?>
                    <?php $isDrive = str_contains(strtolower($item['url']), 'drive.google.com') || str_contains(strtolower($item['url']), 'docs.google.com'); ?>
                    <div>
                      <a href="<?= e($item['url']) ?>" target="_blank" rel="noopener noreferrer" class="small <?= $isDrive ? 'text-success' : 'text-primary' ?> font-weight-bold d-inline-flex align-items-center mt-1">
                        <?php if ($isDrive): ?>
                          <i class="fa-brands fa-google-drive text-warning mr-1"></i> Google Drive
                        <?php else: ?>
                          <i class="fa-solid fa-arrow-up-right-from-square mr-1"></i> Buka Tautan
                        <?php endif; ?>
                      </a>
                    </div>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <?php if ((int) ($item['is_active'] ?? 1) === 1): ?>
                    <span class="badge badge-success">Aktif</span>
                  <?php else: ?>
                    <span class="badge badge-secondary">Nonaktif</span>
                  <?php endif; ?>
                </td>
                <td class="text-right">
                  <button type="button" class="btn btn-sm btn-outline-primary mr-1 edit-toggle-btn" data-target="#editRow<?= (int) $item['id'] ?>" title="Edit Data">
                    <i class="fa-solid fa-pen-to-square"></i>
                  </button>
                  <form method="post" action="academic.php" class="d-inline" onsubmit="return confirm('Apakah kamu yakin ingin menghapus data ini?');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                    <input type="hidden" name="current_cat" class="inputCurrentCat" value="<?= e($initCat) ?>">
                    <input type="hidden" name="current_page" class="inputCurrentPage" value="<?= $initPage ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Data">
                      <i class="fa-solid fa-trash-can"></i>
                    </button>
                  </form>
                </td>
              </tr>

              <!-- Collapsible Edit Form -->
              <tr class="collapse bg-light edit-row" id="editRow<?= (int) $item['id'] ?>" data-for-category="<?= e($cat) ?>">
                <td colspan="6" class="p-3 border-bottom">
                  <div class="card card-body bg-white border">
                    <h6 class="font-weight-bold text-primary mb-3"><i class="fa-solid fa-pen-to-square mr-1"></i> Edit Data #<?= (int) $item['id'] ?> (<?= strtoupper(e($cat)) ?>)</h6>
                    <form method="post" action="academic.php">
                      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="save">
                      <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                      <input type="hidden" name="current_cat" class="inputCurrentCat" value="<?= e($initCat) ?>">
                      <input type="hidden" name="current_page" class="inputCurrentPage" value="<?= $initPage ?>">

                      <div class="row">
                        <div class="form-group col-md-3">
                          <label class="font-weight-bold">Kategori</label>
                          <select class="form-control" name="category" onchange="handleCategoryChange(this.value, 'editSubcatWrap<?= (int) $item['id'] ?>', 'editSubcatSelect<?= (int) $item['id'] ?>', 'editSubcatLabel<?= (int) $item['id'] ?>', 'editMetaLabel<?= (int) $item['id'] ?>', 'editUrlLabel<?= (int) $item['id'] ?>', 'editUrlHint<?= (int) $item['id'] ?>')">
                            <option value="publikasi" <?= $cat === 'publikasi' ? 'selected' : '' ?>>Publikasi</option>
                            <option value="riset" <?= $cat === 'riset' ? 'selected' : '' ?>>Riset</option>
                            <option value="pengabdian" <?= $cat === 'pengabdian' ? 'selected' : '' ?>>Pengabdian</option>
                            <option value="hki" <?= $cat === 'hki' ? 'selected' : '' ?>>HKI / Paten</option>
                            <option value="buku" <?= $cat === 'buku' ? 'selected' : '' ?>>Buku</option>
                          </select>
                        </div>

                        <?php
                          $itemSubcatOptions = $subcatMap[$cat] ?? [];
                          $showSubcat = !empty($itemSubcatOptions);
                          $itemSubcatLabel = match($cat) {
                              'hki' => 'Jenis HKI',
                              'buku' => 'Kategori Buku',
                              default => 'Subkategori',
                          };
                        ?>
                        <div class="form-group col-md-3" id="editSubcatWrap<?= (int) $item['id'] ?>" style="<?= !$showSubcat ? 'display:none;' : '' ?>">
                          <label class="font-weight-bold" id="editSubcatLabel<?= (int) $item['id'] ?>"><?= $itemSubcatLabel ?></label>
                          <select class="form-control" name="subcategory" id="editSubcatSelect<?= (int) $item['id'] ?>">
                            <?php if ($showSubcat): ?>
                              <?php foreach ($itemSubcatOptions as $opt): ?>
                                <option value="<?= e($opt) ?>" <?= ($item['subcategory'] ?? '') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                              <?php endforeach; ?>
                            <?php else: ?>
                              <option value="<?= e($item['subcategory'] ?? '') ?>" selected><?= e($item['subcategory'] ?? '') ?></option>
                            <?php endif; ?>
                          </select>
                        </div>

                        <div class="form-group col-md-2">
                          <label class="font-weight-bold">Tahun</label>
                          <input type="text" class="form-control" name="year" value="<?= e($item['year'] ?? '') ?>">
                        </div>

                        <div class="form-group col-md-2">
                          <label class="font-weight-bold">Urutan</label>
                          <input type="number" class="form-control" name="sort_order" value="<?= (int) ($item['sort_order'] ?? 0) ?>">
                        </div>

                        <div class="form-group col-md-2 d-flex align-items-center">
                          <div class="custom-control custom-switch mt-3">
                            <input type="checkbox" class="custom-control-input" id="edit_active_<?= (int) $item['id'] ?>" name="is_active" value="1" <?= (int) ($item['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                            <label class="custom-control-label font-weight-bold" for="edit_active_<?= (int) $item['id'] ?>">Aktif</label>
                          </div>
                        </div>
                      </div>

                      <div class="form-group">
                        <label class="font-weight-bold">Judul <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" value="<?= e($item['title'] ?? '') ?>" required>
                      </div>

                      <div class="form-group">
                        <label class="font-weight-bold">Author / Penulis / Tim Peneliti / Inventor</label>
                        <input type="text" class="form-control" name="authors" value="<?= e($item['authors'] ?? '') ?>">
                      </div>

                      <div class="row">
                        <div class="form-group col-md-7">
                          <label class="font-weight-bold" id="editMetaLabel<?= (int) $item['id'] ?>">Metadata Sitasi / No. Paten / Penerbit</label>
                          <input type="text" class="form-control" name="journal_meta" value="<?= e($item['journal_meta'] ?? '') ?>">
                        </div>
                        <div class="form-group col-md-5">
                          <label class="font-weight-bold" id="editUrlLabel<?= (int) $item['id'] ?>"><i class="fa-solid fa-link text-primary mr-1"></i> Link URL / Google Drive / Dokumen</label>
                          <input type="url" class="form-control" name="url" value="<?= e($item['url'] ?? '') ?>" placeholder="https://drive.google.com/... atau https://...">
                          <small class="form-text text-muted" id="editUrlHint<?= (int) $item['id'] ?>">Bisa diisi link Google Drive (PDF Buku / Sertifikat HKI), DOI, atau web eksternal.</small>
                        </div>
                      </div>

                      <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-light mr-2 close-edit-btn" data-target="#editRow<?= (int) $item['id'] ?>">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check mr-1"></i> Simpan Perubahan</button>
                      </div>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>

          <tr id="emptyCategoryRow" style="display: none;">
            <td colspan="6" class="text-center py-5 text-muted">
              <i class="fa-regular fa-folder-open mb-2" style="font-size: 32px; display: block;"></i>
              Tidak ada data pada kategori ini.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination Footer Container -->
    <div class="p-3 d-flex justify-content-between align-items-center flex-wrap border-top bg-light" id="paginationFooter">
      <div class="text-muted small mb-2 mb-sm-0" id="paginationInfo">
        Menampilkan data...
      </div>
      <nav aria-label="Navigasi halaman">
        <ul class="pagination pagination-sm mb-0" id="paginationList">
          <!-- Generated via JS -->
        </ul>
      </nav>
    </div>

  </div>
</div>

<script>
var currentCategory = '<?= e($initCat) ?>';
var currentPage = <?= (int) $initPage ?>;
var pageSize = 10;

var subcategoryOptions = {
  publikasi: [
    { value: 'Jurnal Internasional', text: 'Jurnal Internasional' },
    { value: 'Prosiding Internasional', text: 'Prosiding Internasional' },
    { value: 'Jurnal Nasional', text: 'Jurnal Nasional' }
  ],
  hki: [
    { value: 'Hak Cipta', text: 'Hak Cipta' },
    { value: 'Paten', text: 'Paten' },
    { value: 'Paten Sederhana', text: 'Paten Sederhana' },
    { value: 'Desain Industri', text: 'Desain Industri' },
    { value: 'Merek', text: 'Merek' }
  ],
  buku: [
    { value: 'Buku Referensi', text: 'Buku Referensi' },
    { value: 'Buku Ajar', text: 'Buku Ajar' },
    { value: 'Monograf', text: 'Monograf' },
    { value: 'Book Chapter', text: 'Book Chapter' }
  ]
};

function handleCategoryChange(category, subcatWrapId, subcatSelectId, subcatLabelId, metaLabelId, urlLabelId, urlHintId) {
  var subcatWrap = subcatWrapId ? document.getElementById(subcatWrapId) : null;
  var subcatSelect = subcatSelectId ? document.getElementById(subcatSelectId) : null;
  var subcatLabel = subcatLabelId ? document.getElementById(subcatLabelId) : null;

  if (subcatWrap && subcatSelect) {
    if (subcategoryOptions[category]) {
      subcatWrap.style.display = 'block';
      if (subcatLabel) {
        if (category === 'publikasi') subcatLabel.innerText = 'Subkategori Header (Publikasi)';
        else if (category === 'hki') subcatLabel.innerText = 'Jenis HKI / Hak Cipta / Paten';
        else if (category === 'buku') subcatLabel.innerText = 'Kategori / Jenis Buku';
      }
      var currentVal = subcatSelect.value;
      subcatSelect.innerHTML = '';
      var matched = false;
      subcategoryOptions[category].forEach(function(opt) {
        var el = document.createElement('option');
        el.value = opt.value;
        el.innerText = opt.text;
        if (opt.value === currentVal) {
          el.selected = true;
          matched = true;
        }
        subcatSelect.appendChild(el);
      });
      if (!matched && subcategoryOptions[category].length > 0) {
        subcatSelect.options[0].selected = true;
      }
    } else {
      subcatWrap.style.display = 'none';
      subcatSelect.innerHTML = '';
      var defaultVal = (category === 'riset') ? 'Penelitian' : (category === 'pengabdian' ? 'Pengabdian Masyarakat' : '');
      var el = document.createElement('option');
      el.value = defaultVal;
      el.innerText = defaultVal;
      el.selected = true;
      subcatSelect.appendChild(el);
    }
  }

  var metaLabel = metaLabelId ? document.getElementById(metaLabelId) : null;
  if (metaLabel) {
    if (category === 'publikasi') {
      metaLabel.innerText = 'Metadata Jurnal / Sitasi / DOI';
    } else if (category === 'hki') {
      metaLabel.innerText = 'Nomor Permohonan / No. Paten / Status DJKI';
    } else if (category === 'buku') {
      metaLabel.innerText = 'Penerbit / ISBN / Jumlah Halaman';
    } else if (category === 'riset') {
      metaLabel.innerText = 'Tahun & Skema / Sumber Hibah Penelitian';
    } else if (category === 'pengabdian') {
      metaLabel.innerText = 'Tahun & Skema / Mitra Pengabdian';
    } else {
      metaLabel.innerText = 'Metadata / Informasi Tambahan';
    }
  }

  var urlLabel = urlLabelId ? document.getElementById(urlLabelId) : null;
  var urlHint = urlHintId ? document.getElementById(urlHintId) : null;
  if (urlLabel) {
    if (category === 'buku') {
      urlLabel.innerHTML = '<i class="fa-brands fa-google-drive text-success mr-1"></i> Link Google Drive / URL Buku';
    } else if (category === 'hki') {
      urlLabel.innerHTML = '<i class="fa-brands fa-google-drive text-warning mr-1"></i> Link Google Drive / Sertifikat HKI';
    } else if (category === 'publikasi') {
      urlLabel.innerHTML = '<i class="fa-solid fa-link text-primary mr-1"></i> Link URL Jurnal / DOI / Scholar';
    } else {
      urlLabel.innerHTML = '<i class="fa-solid fa-link text-primary mr-1"></i> Link Dokumen / Google Drive / URL';
    }
  }
  if (urlHint) {
    if (category === 'buku') {
      urlHint.innerText = 'Bisa diisi link Google Drive file buku/preview atau URL penerbit (akan tampil tombol klik di bawah penerbit).';
    } else if (category === 'hki') {
      urlHint.innerText = 'Bisa diisi link Google Drive file sertifikat HKI/paten (akan tampil tombol klik di bawah nomor/metadata).';
    } else if (category === 'publikasi') {
      urlHint.innerText = 'Bisa diisi link URL artikel jurnal, prosiding, atau profil Google Scholar.';
    } else {
      urlHint.innerText = 'Link dokumen/web (opsional).';
    }
  }
}

function renderAcademicTable() {
  var allRows = Array.from(document.querySelectorAll('#academicTableBody tr.academic-row'));
  var editRows = Array.from(document.querySelectorAll('#academicTableBody tr.edit-row'));

  // Saring row berdasarkan kategori
  var matchedRows = allRows.filter(function(row) {
    var cat = row.getAttribute('data-category');
    return (currentCategory === 'all' || cat === currentCategory);
  });

  var totalMatched = matchedRows.length;
  var totalPages = Math.max(1, Math.ceil(totalMatched / pageSize));
  if (currentPage > totalPages) {
    currentPage = totalPages;
  }
  if (currentPage < 1) {
    currentPage = 1;
  }

  var startIndex = (currentPage - 1) * pageSize;
  var endIndex = startIndex + pageSize;

  // Sembunyikan semua row dulu
  allRows.forEach(function(row) { row.style.display = 'none'; });
  editRows.forEach(function(er) {
    // jika sedang ditutup biarkan class collapse
    if (!er.classList.contains('show')) {
      er.style.display = 'none';
    }
  });

  // Tampilkan row yang berada di page aktif
  matchedRows.forEach(function(row, idx) {
    if (idx >= startIndex && idx < endIndex) {
      row.style.display = '';
      var idxCell = row.querySelector('.row-idx');
      if (idxCell) {
        idxCell.textContent = (idx + 1);
      }
    }
  });

  // Handle empty state
  var emptyCatRow = document.getElementById('emptyCategoryRow');
  if (emptyCatRow) {
    emptyCatRow.style.display = (totalMatched === 0) ? '' : 'none';
  }

  // Update info text
  var infoEl = document.getElementById('paginationInfo');
  if (infoEl) {
    if (totalMatched === 0) {
      infoEl.innerHTML = 'Tidak ada data';
    } else {
      var startNum = startIndex + 1;
      var endNum = Math.min(totalMatched, endIndex);
      var catName = (currentCategory === 'all') ? 'Semua Kategori' : currentCategory.toUpperCase();
      infoEl.innerHTML = 'Menampilkan <strong>' + startNum + ' - ' + endNum + '</strong> dari <strong>' + totalMatched + '</strong> data (' + catName + ')';
    }
  }

  // Render pagination buttons
  var pagList = document.getElementById('paginationList');
  if (pagList) {
    pagList.innerHTML = '';
    if (totalPages > 1) {
      // Prev button
      var prevLi = document.createElement('li');
      prevLi.className = 'page-item' + (currentPage <= 1 ? ' disabled' : '');
      prevLi.innerHTML = '<a class="page-link" href="javascript:void(0)" aria-label="Previous">&laquo; Prev</a>';
      if (currentPage > 1) {
        prevLi.onclick = function(e) {
          e.preventDefault();
          currentPage--;
          renderAcademicTable();
        };
      }
      pagList.appendChild(prevLi);

      // Page numbers
      for (var p = 1; p <= totalPages; p++) {
        (function(pageNumber) {
          var pLi = document.createElement('li');
          pLi.className = 'page-item' + (pageNumber === currentPage ? ' active' : '');
          pLi.innerHTML = '<a class="page-link" href="javascript:void(0)">' + pageNumber + '</a>';
          pLi.onclick = function(e) {
            e.preventDefault();
            currentPage = pageNumber;
            renderAcademicTable();
          };
          pagList.appendChild(pLi);
        })(p);
      }

      // Next button
      var nextLi = document.createElement('li');
      nextLi.className = 'page-item' + (currentPage >= totalPages ? ' disabled' : '');
      nextLi.innerHTML = '<a class="page-link" href="javascript:void(0)" aria-label="Next">Next &raquo;</a>';
      if (currentPage < totalPages) {
        nextLi.onclick = function(e) {
          e.preventDefault();
          currentPage++;
          renderAcademicTable();
        };
      }
      pagList.appendChild(nextLi);
    }
  }

  // Update hidden inputs in forms
  document.querySelectorAll('.inputCurrentCat').forEach(function(inp) { inp.value = currentCategory; });
  document.querySelectorAll('.inputCurrentPage').forEach(function(inp) { inp.value = currentPage; });
  var formCat = document.getElementById('formCurrentCat');
  if (formCat) formCat.value = currentCategory;
  var formPage = document.getElementById('formCurrentPage');
  if (formPage) formPage.value = currentPage;
}

document.addEventListener('DOMContentLoaded', function() {
  // Filter pill click handler
  document.querySelectorAll('.cat-filter-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      var selected = this.getAttribute('data-cat') || 'all';
      currentCategory = selected;
      currentPage = 1;

      // Update button visual styles
      document.querySelectorAll('.cat-filter-btn').forEach(function(b) {
        var cat = b.getAttribute('data-cat');
        b.className = 'btn btn-sm cat-filter-btn font-weight-bold px-3 py-2 btn-outline-secondary';
      });

      if (selected === 'all') this.className = 'btn btn-sm cat-filter-btn font-weight-bold px-3 py-2 btn-dark';
      else if (selected === 'publikasi') this.className = 'btn btn-sm cat-filter-btn font-weight-bold px-3 py-2 btn-primary';
      else if (selected === 'riset') this.className = 'btn btn-sm cat-filter-btn font-weight-bold px-3 py-2 btn-info';
      else if (selected === 'pengabdian') this.className = 'btn btn-sm cat-filter-btn font-weight-bold px-3 py-2 btn-success';
      else if (selected === 'hki') this.className = 'btn btn-sm cat-filter-btn font-weight-bold px-3 py-2 btn-warning text-dark';
      else if (selected === 'buku') this.className = 'btn btn-sm cat-filter-btn font-weight-bold px-3 py-2 btn-secondary';

      renderAcademicTable();
    });
  });

  // Edit toggle click handler
  document.addEventListener('click', function(e) {
    var editBtn = e.target.closest('.edit-toggle-btn');
    if (editBtn) {
      e.preventDefault();
      var targetSelector = editBtn.getAttribute('data-target');
      var targetRow = document.querySelector(targetSelector);
      if (targetRow) {
        if (targetRow.classList.contains('show')) {
          targetRow.classList.remove('show');
          targetRow.style.display = 'none';
        } else {
          // Tutup edit row lain
          document.querySelectorAll('.edit-row.show').forEach(function(r) {
            r.classList.remove('show');
            r.style.display = 'none';
          });
          targetRow.classList.add('show');
          targetRow.style.display = '';
        }
      }
    }

    var closeBtn = e.target.closest('.close-edit-btn');
    if (closeBtn) {
      e.preventDefault();
      var targetSelector2 = closeBtn.getAttribute('data-target');
      var targetRow2 = document.querySelector(targetSelector2);
      if (targetRow2) {
        targetRow2.classList.remove('show');
        targetRow2.style.display = 'none';
      }
    }
  });

  // Render awal
  renderAcademicTable();
});
</script>

<?php
admin_footer();

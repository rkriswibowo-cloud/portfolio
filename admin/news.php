<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

require_admin();

$pdo = pdo();

function unique_news_slug(PDO $pdo, string $slug, int $ignoreId = 0): string
{
    $baseSlug = make_slug($slug);
    $candidate = $baseSlug;
    $counter = 2;

    while (true) {
        $statement = $pdo->prepare('SELECT id FROM news_posts WHERE slug = ? AND id <> ? LIMIT 1');
        $statement->execute([$candidate, $ignoreId]);

        if (!$statement->fetch()) {
            return $candidate;
        }

        $candidate = $baseSlug . '-' . $counter;
        $counter++;
    }
}

function datetime_input(?string $value): string
{
    if (!$value) {
        return '';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d\TH:i', $timestamp) : '';
}

function admin_news_editor(string $content, string $editorId): void
{
    if (function_exists('wp_editor')) {
        wp_editor($content, $editorId, [
            'textarea_name' => 'content',
            'textarea_rows' => 12,
            'media_buttons' => true,
            'teeny' => false,
            'quicktags' => true,
        ]);
        return;
    }
    ?>
    <div class="rich-text-editor" data-rich-editor>
      <div class="rich-text-toolbar" role="toolbar" aria-label="Toolbar isi berita">
        <button type="button" data-command="bold" title="Bold"><span class="toolbar-mark toolbar-mark-bold">B</span></button>
        <button type="button" data-command="italic" title="Italic"><span class="toolbar-mark toolbar-mark-italic">I</span></button>
        <button type="button" data-command="underline" title="Underline"><span class="toolbar-mark toolbar-mark-underline">U</span></button>
        <span class="toolbar-divider"></span>
        <button type="button" data-format="h2" title="Heading"><span class="toolbar-mark toolbar-mark-heading">H</span></button>
        <button type="button" data-format="p" title="Paragraph"><i class="uil uil-paragraph"></i></button>
        <button type="button" data-command="insertUnorderedList" title="Bullet list"><i class="uil uil-list-ul"></i></button>
        <button type="button" data-command="insertOrderedList" title="Numbered list"><span class="toolbar-mark toolbar-mark-list">1.</span></button>
        <span class="toolbar-divider"></span>
        <button type="button" data-command="justifyLeft" title="Rata kiri"><i class="uil uil-align-left"></i></button>
        <button type="button" data-command="justifyCenter" title="Rata tengah"><i class="uil uil-align-center"></i></button>
        <button type="button" data-command="justifyRight" title="Rata kanan"><i class="uil uil-align-right"></i></button>
        <button type="button" data-link title="Link"><i class="uil uil-link"></i></button>
        <input type="color" value="#474559" title="Warna teks" data-color>
      </div>
      <div class="rich-text-surface" id="<?= e($editorId) ?>_surface" contenteditable="true" data-editor-surface></div>
      <textarea class="rich-text-value" id="<?= e($editorId) ?>" name="content"><?= e($content) ?></textarea>
    </div>
    <?php
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $action = (string) ($_POST['action'] ?? 'save_post');

    try {
        if ($action === 'save_settings') {
            save_settings([
                'news_page_title' => trim((string) ($_POST['news_page_title'] ?? '')),
                'news_page_subtitle' => trim((string) ($_POST['news_page_subtitle'] ?? '')),
                'news_detail_back_text' => trim((string) ($_POST['news_detail_back_text'] ?? '')),
            ]);
            set_admin_flash('success', 'Pengaturan halaman berita berhasil disimpan.');
        } elseif ($action === 'delete_post') {
            $statement = $pdo->prepare('DELETE FROM news_posts WHERE id = ?');
            $statement->execute([(int) ($_POST['id'] ?? 0)]);
            set_admin_flash('success', 'Berita berhasil dihapus.');
        } elseif ($action === 'delete_carousel') {
            $statement = $pdo->prepare('DELETE FROM news_carousel_images WHERE id = ?');
            $statement->execute([(int) ($_POST['id'] ?? 0)]);
            set_admin_flash('success', 'Gambar carousel berhasil dihapus.');
        } elseif ($action === 'save_carousel') {
            $id = (int) ($_POST['id'] ?? 0);
            $imagePath = trim((string) ($_POST['image_path'] ?? ''));
            $uploadedImage = upload_news_image($_FILES['image_file'] ?? []);

            if ($uploadedImage !== null) {
                $imagePath = $uploadedImage;
            }

            if ($imagePath === '') {
                throw new RuntimeException('Path gambar carousel wajib diisi atau upload gambar baru.');
            }

            $data = [
                trim((string) ($_POST['title'] ?? '')),
                trim((string) ($_POST['caption'] ?? '')),
                $imagePath,
                trim((string) ($_POST['link_url'] ?? '#')),
                (int) ($_POST['sort_order'] ?? 0),
                isset($_POST['is_active']) ? 1 : 0,
            ];

            if ($data[0] === '') {
                throw new RuntimeException('Judul carousel wajib diisi.');
            }

            if ($id > 0) {
                $statement = $pdo->prepare('UPDATE news_carousel_images SET title = ?, caption = ?, image_path = ?, link_url = ?, sort_order = ?, is_active = ? WHERE id = ?');
                $statement->execute([...$data, $id]);
            } else {
                $statement = $pdo->prepare('INSERT INTO news_carousel_images (title, caption, image_path, link_url, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)');
                $statement->execute($data);
            }

            set_admin_flash('success', 'Gambar carousel berhasil disimpan.');
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $title = trim((string) ($_POST['title'] ?? ''));
            $slug = unique_news_slug($pdo, trim((string) ($_POST['slug'] ?? '')) ?: $title, $id);
            $imagePath = trim((string) ($_POST['image_path'] ?? ''));
            $uploadedImage = upload_news_image($_FILES['image_file'] ?? []);
            $publishedAt = trim((string) ($_POST['published_at'] ?? ''));

            if ($uploadedImage !== null) {
                $imagePath = $uploadedImage;
            }

            if ($title === '') {
                throw new RuntimeException('Judul berita wajib diisi.');
            }

            $data = [
                $title,
                $slug,
                trim((string) ($_POST['excerpt'] ?? '')),
                trim((string) ($_POST['content'] ?? '')),
                $imagePath,
                trim((string) ($_POST['author'] ?? '')),
                $publishedAt !== '' ? date('Y-m-d H:i:s', strtotime($publishedAt)) : null,
                isset($_POST['is_published']) ? 1 : 0,
            ];

            if ($id > 0) {
                $statement = $pdo->prepare('UPDATE news_posts SET title = ?, slug = ?, excerpt = ?, content = ?, image_path = ?, author = ?, published_at = ?, is_published = ? WHERE id = ?');
                $statement->execute([...$data, $id]);
            } else {
                $statement = $pdo->prepare('INSERT INTO news_posts (title, slug, excerpt, content, image_path, author, published_at, is_published) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $statement->execute($data);
            }

            set_admin_flash('success', 'Berita berhasil disimpan.');
        }
    } catch (Throwable $exception) {
        set_admin_flash('danger', $exception->getMessage());
    }

    redirect('news.php');
}

$settings = get_settings($pdo);
$posts = get_news_posts($pdo, false);
$carouselImages = get_news_carousel_images($pdo, false);

admin_header('Edit News');
?>
<div class="settings-accordion" id="newsSettingsAccordion">
  <div class="admin-card setting-card">
    <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#newsPageSettings" aria-expanded="true" aria-controls="newsPageSettings">
      <span><i class="uil uil-newspaper"></i> Pengaturan Halaman Berita <small>Judul halaman, subtitle, dan teks kembali di detail berita.</small></span>
      <i class="uil uil-angle-down"></i>
    </button>
    <div id="newsPageSettings" class="collapse show" data-parent="#newsSettingsAccordion">
      <form method="post" action="news.php" class="settings-body">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_settings">
        <div class="form-group">
          <label for="news_page_title">Judul halaman</label>
          <input type="text" class="form-control" id="news_page_title" name="news_page_title" value="<?= e($settings['news_page_title'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="news_page_subtitle">Subtitle</label>
          <textarea class="form-control" id="news_page_subtitle" name="news_page_subtitle" rows="3"><?= e($settings['news_page_subtitle'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
          <label for="news_detail_back_text">Teks tombol kembali</label>
          <input type="text" class="form-control" id="news_detail_back_text" name="news_detail_back_text" value="<?= e($settings['news_detail_back_text'] ?? '') ?>">
        </div>
        <button type="submit" class="btn btn-warning font-weight-bold"><i class="uil uil-check-circle"></i> Simpan Pengaturan</button>
      </form>
    </div>
  </div>
</div>

<div class="settings-accordion" id="newsCarouselAccordion">
  <div class="admin-card setting-card">
    <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#newCarouselSettings" aria-expanded="false" aria-controls="newCarouselSettings">
      <span><i class="uil uil-plus-circle"></i> Tambah Gambar Carousel <small>Gambar sorotan yang tampil di atas halaman berita.</small></span>
      <i class="uil uil-angle-down"></i>
    </button>
    <div id="newCarouselSettings" class="collapse" data-parent="#newsCarouselAccordion">
      <form method="post" action="news.php" class="settings-body" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_carousel">
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
            <label>Link</label>
            <input type="text" class="form-control" name="link_url" value="#">
          </div>
        </div>
        <div class="form-group">
          <label>Caption</label>
          <textarea class="form-control" name="caption" rows="3"></textarea>
        </div>
        <div class="row">
          <div class="form-group col-md-6">
            <label>Path gambar</label>
            <input type="text" class="form-control" name="image_path" placeholder="images/news/carousel.png">
          </div>
          <div class="form-group col-md-6">
            <label>Upload gambar</label>
            <input type="file" class="form-control-file" name="image_file" accept=".jpg,.jpeg,.png,.gif,.webp">
          </div>
        </div>
        <div class="custom-control custom-checkbox mb-3">
          <input type="checkbox" class="custom-control-input" id="new_carousel_active" name="is_active" checked>
          <label class="custom-control-label" for="new_carousel_active">Tampilkan di carousel</label>
        </div>
        <button type="submit" class="btn btn-warning font-weight-bold"><i class="uil uil-plus-circle"></i> Tambah Carousel</button>
      </form>
    </div>
  </div>

  <?php foreach ($carouselImages as $image): ?>
    <div class="admin-card setting-card">
      <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#carousel_<?= e((string) $image['id']) ?>" aria-expanded="false" aria-controls="carousel_<?= e((string) $image['id']) ?>">
        <span><i class="uil uil-images"></i> <?= e($image['title']) ?> <small><?= (int) $image['is_active'] === 1 ? 'Aktif' : 'Disembunyikan' ?> · Urutan <?= e((string) $image['sort_order']) ?></small></span>
        <i class="uil uil-angle-down"></i>
      </button>
      <div id="carousel_<?= e((string) $image['id']) ?>" class="collapse" data-parent="#newsCarouselAccordion">
        <form method="post" action="news.php" class="settings-body" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= e((string) $image['id']) ?>">
          <div class="row">
            <div class="col-lg-3">
              <img class="project-preview mb-3 mb-lg-0" src="../<?= e($image['image_path']) ?>" alt="<?= e($image['title']) ?>">
            </div>
            <div class="col-lg-9">
              <div class="row">
                <div class="form-group col-md-6">
                  <label>Judul</label>
                  <input type="text" class="form-control" name="title" value="<?= e($image['title']) ?>" required>
                </div>
                <div class="form-group col-md-3">
                  <label>Urutan</label>
                  <input type="number" class="form-control" name="sort_order" value="<?= e((string) $image['sort_order']) ?>">
                </div>
                <div class="form-group col-md-3">
                  <label>Link</label>
                  <input type="text" class="form-control" name="link_url" value="<?= e($image['link_url']) ?>">
                </div>
              </div>
              <div class="form-group">
                <label>Caption</label>
                <textarea class="form-control" name="caption" rows="3"><?= e($image['caption']) ?></textarea>
              </div>
              <div class="row">
                <div class="form-group col-md-6">
                  <label>Path gambar</label>
                  <input type="text" class="form-control" name="image_path" value="<?= e($image['image_path']) ?>">
                </div>
                <div class="form-group col-md-6">
                  <label>Ganti gambar</label>
                  <input type="file" class="form-control-file" name="image_file" accept=".jpg,.jpeg,.png,.gif,.webp">
                </div>
              </div>
              <div class="d-flex flex-wrap align-items-center">
                <div class="custom-control custom-checkbox mr-3 mb-2">
                  <input type="checkbox" class="custom-control-input" id="carousel_active_<?= e((string) $image['id']) ?>" name="is_active" <?= (int) $image['is_active'] === 1 ? 'checked' : '' ?>>
                  <label class="custom-control-label" for="carousel_active_<?= e((string) $image['id']) ?>">Tampilkan di carousel</label>
                </div>
                <button type="submit" name="action" value="save_carousel" class="btn btn-warning font-weight-bold mr-2 mb-2"><i class="uil uil-check-circle"></i> Simpan</button>
                <button type="submit" name="action" value="delete_carousel" class="btn btn-outline-danger mb-2" onclick="return confirm('Hapus gambar carousel ini?')"><i class="uil uil-trash-alt"></i> Hapus</button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="settings-accordion" id="newsPostAccordion">
  <div class="admin-card setting-card">
    <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#newPostSettings" aria-expanded="false" aria-controls="newPostSettings">
      <span><i class="uil uil-plus-circle"></i> Tambah Berita <small>Buat artikel baru seperti posting WordPress sederhana.</small></span>
      <i class="uil uil-angle-down"></i>
    </button>
    <div id="newPostSettings" class="collapse" data-parent="#newsPostAccordion">
      <form method="post" action="news.php" class="settings-body" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_post">
        <input type="hidden" name="id" value="0">
        <div class="row">
          <div class="form-group col-md-8">
            <label>Judul</label>
            <input type="text" class="form-control" name="title" required>
          </div>
          <div class="form-group col-md-4">
            <label>Slug</label>
            <input type="text" class="form-control" name="slug" placeholder="otomatis-jika-kosong">
          </div>
        </div>
        <div class="row">
          <div class="form-group col-md-6">
            <label>Author</label>
            <input type="text" class="form-control" name="author" value="<?= e(current_admin_name()) ?>">
          </div>
          <div class="form-group col-md-6">
            <label>Tanggal publish</label>
            <input type="datetime-local" class="form-control" name="published_at" value="<?= e(date('Y-m-d\TH:i')) ?>">
          </div>
        </div>
        <div class="form-group">
          <label>Ringkasan</label>
          <textarea class="form-control" name="excerpt" rows="3"></textarea>
        </div>
        <div class="form-group">
          <label>Isi berita</label>
          <?php admin_news_editor('', 'new_post_content'); ?>
        </div>
        <div class="row">
          <div class="form-group col-md-6">
            <label>Path gambar utama</label>
            <input type="text" class="form-control" name="image_path" placeholder="images/news/berita.png">
          </div>
          <div class="form-group col-md-6">
            <label>Upload gambar utama</label>
            <input type="file" class="form-control-file" name="image_file" accept=".jpg,.jpeg,.png,.gif,.webp">
          </div>
        </div>
        <div class="custom-control custom-checkbox mb-3">
          <input type="checkbox" class="custom-control-input" id="new_post_published" name="is_published" checked>
          <label class="custom-control-label" for="new_post_published">Publish berita</label>
        </div>
        <button type="submit" class="btn btn-warning font-weight-bold"><i class="uil uil-plus-circle"></i> Tambah Berita</button>
      </form>
    </div>
  </div>

  <?php foreach ($posts as $post): ?>
    <div class="admin-card setting-card">
      <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#post_<?= e((string) $post['id']) ?>" aria-expanded="false" aria-controls="post_<?= e((string) $post['id']) ?>">
        <span><i class="uil uil-edit"></i> <?= e($post['title']) ?> <small><?= (int) $post['is_published'] === 1 ? 'Published' : 'Draft' ?> · <?= e($post['slug']) ?></small></span>
        <i class="uil uil-angle-down"></i>
      </button>
      <div id="post_<?= e((string) $post['id']) ?>" class="collapse" data-parent="#newsPostAccordion">
        <form method="post" action="news.php" class="settings-body" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= e((string) $post['id']) ?>">
          <div class="row">
            <div class="col-lg-3">
              <?php if (!empty($post['image_path'])): ?>
                <img class="project-preview mb-3 mb-lg-0" src="../<?= e($post['image_path']) ?>" alt="<?= e($post['title']) ?>">
              <?php endif; ?>
            </div>
            <div class="col-lg-9">
              <div class="row">
                <div class="form-group col-md-8">
                  <label>Judul</label>
                  <input type="text" class="form-control" name="title" value="<?= e($post['title']) ?>" required>
                </div>
                <div class="form-group col-md-4">
                  <label>Slug</label>
                  <input type="text" class="form-control" name="slug" value="<?= e($post['slug']) ?>">
                </div>
              </div>
              <div class="row">
                <div class="form-group col-md-6">
                  <label>Author</label>
                  <input type="text" class="form-control" name="author" value="<?= e($post['author']) ?>">
                </div>
                <div class="form-group col-md-6">
                  <label>Tanggal publish</label>
                  <input type="datetime-local" class="form-control" name="published_at" value="<?= e(datetime_input($post['published_at'] ?? null)) ?>">
                </div>
              </div>
              <div class="form-group">
                <label>Ringkasan</label>
                <textarea class="form-control" name="excerpt" rows="3"><?= e($post['excerpt']) ?></textarea>
              </div>
              <div class="form-group">
                <label>Isi berita</label>
                <?php admin_news_editor((string) $post['content'], 'post_content_' . (string) $post['id']); ?>
              </div>
              <div class="row">
                <div class="form-group col-md-6">
                  <label>Path gambar utama</label>
                  <input type="text" class="form-control" name="image_path" value="<?= e($post['image_path']) ?>">
                </div>
                <div class="form-group col-md-6">
                  <label>Ganti gambar utama</label>
                  <input type="file" class="form-control-file" name="image_file" accept=".jpg,.jpeg,.png,.gif,.webp">
                </div>
              </div>
              <div class="d-flex flex-wrap align-items-center">
                <div class="custom-control custom-checkbox mr-3 mb-2">
                  <input type="checkbox" class="custom-control-input" id="post_published_<?= e((string) $post['id']) ?>" name="is_published" <?= (int) $post['is_published'] === 1 ? 'checked' : '' ?>>
                  <label class="custom-control-label" for="post_published_<?= e((string) $post['id']) ?>">Publish berita</label>
                </div>
                <a href="../berita.php?slug=<?= e($post['slug']) ?>" class="btn btn-outline-secondary mr-2 mb-2" target="_blank"><i class="uil uil-eye"></i> Lihat</a>
                <button type="submit" name="action" value="save_post" class="btn btn-warning font-weight-bold mr-2 mb-2"><i class="uil uil-check-circle"></i> Simpan</button>
                <button type="submit" name="action" value="delete_post" class="btn btn-outline-danger mb-2" onclick="return confirm('Hapus berita ini?')"><i class="uil uil-trash-alt"></i> Hapus</button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-rich-editor]').forEach(function (editor) {
      var surface = editor.querySelector('[data-editor-surface]');
      var textarea = editor.querySelector('.rich-text-value');

      if (!surface || !textarea) {
        return;
      }

      surface.innerHTML = textarea.value;

      var syncValue = function () {
        textarea.value = surface.innerHTML.trim();
      };

      editor.querySelectorAll('[data-command]').forEach(function (button) {
        button.addEventListener('click', function () {
          surface.focus();
          document.execCommand(button.dataset.command, false, null);
          syncValue();
        });
      });

      editor.querySelectorAll('[data-format]').forEach(function (button) {
        button.addEventListener('click', function () {
          surface.focus();
          document.execCommand('formatBlock', false, button.dataset.format);
          syncValue();
        });
      });

      var colorInput = editor.querySelector('[data-color]');
      if (colorInput) {
        colorInput.addEventListener('input', function () {
          surface.focus();
          document.execCommand('foreColor', false, colorInput.value);
          syncValue();
        });
      }

      var linkButton = editor.querySelector('[data-link]');
      if (linkButton) {
        linkButton.addEventListener('click', function () {
          var url = window.prompt('Masukkan URL link');
          if (!url) {
            return;
          }
          surface.focus();
          document.execCommand('createLink', false, url);
          syncValue();
        });
      }

      surface.addEventListener('input', syncValue);
      surface.addEventListener('blur', syncValue);
      editor.closest('form').addEventListener('submit', syncValue);
    });
  });
</script>
<?php admin_footer(); ?>

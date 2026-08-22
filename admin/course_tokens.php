<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

require_admin();

$pdo = pdo();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_token();

        $action = (string) ($_POST['action'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);

        if ($action === 'delete_token' && $id > 0) {
            $st = $pdo->prepare('DELETE FROM enrollment_tokens WHERE id = ?');
            $st->execute([$id]);

            set_admin_flash('success', 'Token enrollment berhasil dihapus.');
            redirect('course_tokens.php');
        }

        if ($action === 'regenerate_token' && $id > 0) {
            $token = generate_course_token(12);

            $st = $pdo->prepare('UPDATE enrollment_tokens SET token = ? WHERE id = ?');
            $st->execute([$token, $id]);

            set_admin_flash('success', 'Token berhasil diregenerate: ' . $token);
            redirect('course_tokens.php');
        }

        if ($action === 'save_token') {
            $label = trim((string) ($_POST['label'] ?? ''));
            $token = strtoupper(trim((string) ($_POST['token'] ?? '')));
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $courseIds = array_values(array_unique(array_map('intval', $_POST['course_ids'] ?? [])));

            if ($label === '') {
                throw new RuntimeException('Nama token wajib diisi.');
            }

            if ($token === '') {
                $token = generate_course_token(12);
            }

            if (!$courseIds) {
                throw new RuntimeException('Pilih minimal satu course untuk token ini.');
            }

            if ($id > 0) {
                $st = $pdo->prepare('UPDATE enrollment_tokens SET label = ?, token = ?, is_active = ? WHERE id = ?');
                $st->execute([$label, $token, $isActive, $id]);
                $tokenId = $id;
            } else {
                $st = $pdo->prepare('INSERT INTO enrollment_tokens (label, token, is_active) VALUES (?, ?, ?)');
                $st->execute([$label, $token, $isActive]);
                $tokenId = (int) $pdo->lastInsertId();
            }

            $pdo->prepare('DELETE FROM enrollment_token_courses WHERE token_id = ?')->execute([$tokenId]);

            $ins = $pdo->prepare('INSERT INTO enrollment_token_courses (token_id, course_id) VALUES (?, ?)');
            foreach ($courseIds as $cid) {
                $ins->execute([$tokenId, $cid]);
            }

            set_admin_flash('success', 'Token enrollment berhasil disimpan.');
            redirect('course_tokens.php');
        }
    }
} catch (Throwable $e) {
    set_admin_flash('danger', $e->getMessage());
    redirect('course_tokens.php');
}

$courses = get_courses($pdo, false);
$tokens = get_enrollment_tokens($pdo);

admin_header('Token Course');
?>

<div class="admin-section-heading">
    <div>
        <small>Course Access</small>
        <h2>Token Enrollment</h2>
        <p>
            Token di sini dipakai user untuk mendaftarkan akses course ke akun mereka.
            Satu token bisa dihubungkan ke banyak course dan dipakai banyak user.
            Pengaturan waktu course tetap dikelola dari menu Courses.
        </p>
    </div>
</div>

<div class="admin-card setting-card mb-4">
    <button class="settings-toggle" type="button" data-toggle="collapse" data-target="#newToken">
        <span>
            <i class="uil uil-key-skeleton"></i>
            Tambah Token Enrollment
            <small>Buat token baru dan pilih course yang dibuka.</small>
        </span>
        <i class="uil uil-angle-down"></i>
    </button>

    <div id="newToken" class="collapse show">
        <form method="post" class="settings-body">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_token">
            <input type="hidden" name="id" value="0">

            <div class="row">
                <div class="form-group col-md-6">
                    <label>Nama Token</label>
                    <input
                        class="form-control"
                        name="label"
                        placeholder="Batch Januari / Promo A"
                        required
                    >
                </div>

                <div class="form-group col-md-6">
                    <label>Token</label>
                    <div class="input-group">
                        <input
                            class="form-control course-token-input"
                            name="token"
                            placeholder="Kosongkan untuk otomatis"
                        >
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary js-generate-token" type="button">
                                Generate
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Course yang dibuka token ini</label>

                <?php if (!$courses): ?>
                    <div class="alert alert-warning mb-0">
                        Belum ada course. Silakan buat course terlebih dahulu di menu Courses.
                    </div>
                <?php else: ?>
                    <div class="course-token-course-list">
                        <?php foreach ($courses as $course): ?>
                            <div class="custom-control custom-checkbox course-token-course-item">
                                <input
                                    type="checkbox"
                                    class="custom-control-input"
                                    id="new_course_<?= e((string) $course['id']) ?>"
                                    name="course_ids[]"
                                    value="<?= e((string) $course['id']) ?>"
                                >
                                <label
                                    class="custom-control-label"
                                    for="new_course_<?= e((string) $course['id']) ?>"
                                >
                                    <?= e($course['title']) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="custom-control custom-checkbox mb-3">
                <input
                    type="checkbox"
                    class="custom-control-input"
                    id="new_token_active"
                    name="is_active"
                    checked
                >
                <label class="custom-control-label" for="new_token_active">
                    Token aktif
                </label>
            </div>

            <button class="btn btn-warning font-weight-bold" type="submit">
                <i class="uil uil-plus-circle"></i>
                Simpan Token
            </button>
        </form>
    </div>
</div>

<div class="settings-accordion" id="tokenAccordion">
    <?php foreach ($tokens as $token): ?>
        <div class="admin-card setting-card">
            <button
                class="settings-toggle"
                type="button"
                data-toggle="collapse"
                data-target="#token_<?= e((string) $token['id']) ?>"
            >
                <span>
                    <i class="uil uil-key-skeleton"></i>
                    <?= e($token['label']) ?>
                    <small>
                        <?= (int) $token['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?>
                        · Token: <?= e($token['token']) ?>
                        · <?= count($token['course_ids'] ?? []) ?> course
                    </small>
                </span>
                <i class="uil uil-angle-down"></i>
            </button>

            <div
                id="token_<?= e((string) $token['id']) ?>"
                class="collapse"
                data-parent="#tokenAccordion"
            >
                <form method="post" class="settings-body">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= e((string) $token['id']) ?>">

                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>Nama Token</label>
                            <input
                                class="form-control"
                                name="label"
                                value="<?= e($token['label']) ?>"
                                required
                            >
                        </div>

                        <div class="form-group col-md-6">
                            <label>Token</label>
                            <div class="input-group">
                                <input
                                    class="form-control course-token-input"
                                    name="token"
                                    value="<?= e($token['token']) ?>"
                                >
                                <div class="input-group-append">
                                    <button
                                        class="btn btn-outline-secondary js-generate-token"
                                        type="button"
                                    >
                                        Generate
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Course yang dibuka token ini</label>

                        <?php if (!$courses): ?>
                            <div class="alert alert-warning mb-0">
                                Belum ada course. Silakan buat course terlebih dahulu di menu Courses.
                            </div>
                        <?php else: ?>
                            <div class="course-token-course-list">
                                <?php foreach ($courses as $course): ?>
                                    <?php
                                    $checked = in_array(
                                        (int) $course['id'],
                                        $token['course_ids'] ?? [],
                                        true
                                    );
                                    ?>
                                    <div class="custom-control custom-checkbox course-token-course-item">
                                        <input
                                            type="checkbox"
                                            class="custom-control-input"
                                            id="token_<?= e((string) $token['id']) ?>_course_<?= e((string) $course['id']) ?>"
                                            name="course_ids[]"
                                            value="<?= e((string) $course['id']) ?>"
                                            <?= $checked ? 'checked' : '' ?>
                                        >
                                        <label
                                            class="custom-control-label"
                                            for="token_<?= e((string) $token['id']) ?>_course_<?= e((string) $course['id']) ?>"
                                        >
                                            <?= e($course['title']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="custom-control custom-checkbox mb-3">
                        <input
                            type="checkbox"
                            class="custom-control-input"
                            id="active_<?= e((string) $token['id']) ?>"
                            name="is_active"
                            <?= (int) $token['is_active'] === 1 ? 'checked' : '' ?>
                        >
                        <label
                            class="custom-control-label"
                            for="active_<?= e((string) $token['id']) ?>"
                        >
                            Token aktif
                        </label>
                    </div>

                    <button
                        class="btn btn-warning font-weight-bold mr-2 mb-2"
                        name="action"
                        value="save_token"
                        type="submit"
                    >
                        <i class="uil uil-check-circle"></i>
                        Simpan
                    </button>

                    <button
                        class="btn btn-outline-primary mr-2 mb-2"
                        name="action"
                        value="regenerate_token"
                        type="submit"
                        onclick="return confirm('Regenerate token ini?')"
                    >
                        <i class="uil uil-refresh"></i>
                        Regenerate
                    </button>

                    <button
                        class="btn btn-outline-danger mb-2"
                        name="action"
                        value="delete_token"
                        type="submit"
                        onclick="return confirm('Hapus token ini? Enrollment lama tetap tersimpan, tapi token tidak bisa dipakai lagi.')"
                    >
                        <i class="uil uil-trash-alt"></i>
                        Hapus
                    </button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
document.addEventListener('click', function (event) {
    if (!event.target.classList.contains('js-generate-token')) return;

    var group = event.target.closest('.input-group');
    var input = group ? group.querySelector('.course-token-input') : null;

    if (!input) return;

    var alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    var token = '';

    for (var i = 0; i < 12; i++) {
        token += alphabet[Math.floor(Math.random() * alphabet.length)];
    }

    input.value = token;
});
</script>

<?php admin_footer(); ?>

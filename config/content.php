<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

function render_rich_text_content(?string $content): string
{
    $content = trim((string) $content);

    if ($content === '') {
        return '';
    }

    if ($content === strip_tags($content)) {
        return nl2br(e($content));
    }

    $content = preg_replace('#<(script|style|iframe|object|embed|form|input|button|meta|link)\b[^>]*>.*?</\1>#is', '', $content) ?? '';
    $content = preg_replace('#<(script|style|iframe|object|embed|form|input|button|meta|link)\b[^>]*\/?>#is', '', $content) ?? '';
    $content = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $content) ?? '';
    $content = preg_replace('/\s+(href|src)\s*=\s*([\'"])\s*javascript:[^\'"]*\2/i', ' $1="#"', $content) ?? '';

    return strip_tags($content, '<p><br><strong><b><em><i><u><s><h2><h3><h4><ul><ol><li><blockquote><a><img><figure><figcaption><span><div><pre><code><hr>');
}

function default_settings(): array
{
    return [
        'site_brand' => 'Marvel',
        'page_title' => 'Marvel Portfolio',
        'about_welcome' => 'Welcome to my portfolio website!',
        'about_prefix' => "Hey folks, I'm",
        'about_name' => 'Marvel Sann',
        'about_role_1' => 'Web Designer',
        'about_role_2' => 'UI Specialist',
        'about_role_3' => 'Frontend Developer',
        'about_description' => 'Building a successful product is a challenge. I am highly energetic in user experience design, interfaces and web development.',
        'about_image' => 'images/undraw/undraw_software_engineer_lvl5.svg',
        'resume_file_url' => '#',
        'quote_button_text' => 'Get a free quote',
        'project_heading' => 'Things I have designed for digital media agencies',
        'contact_heading' => "Interested to work together? Let's talk",
        'contact_map_url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d12088.558402180099!2d-73.99373482142036!3d40.75895421922642!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x89c25855b8fb3083%3A0xa0f9aef176042a5c!2sTheater+District%2C+New+York%2C+NY%2C+USA!5e0!3m2!1sen!2smm!4v1549875377188',
        'contact_phone' => '010 020 0960',
        'contact_email' => 'hello@company.co',
        'social_dribbble' => '#',
        'social_instagram' => '#',
        'social_youtube' => '#',
        'footer_company' => 'Company Name',
        'news_page_title' => 'Berita Terbaru',
        'news_page_subtitle' => 'Kumpulan kabar, cerita project, dan update terbaru.',
        'news_detail_back_text' => 'Kembali ke daftar berita',
        'course_page_title' => 'Course',
        'course_page_subtitle' => 'Pilih kelas, masukkan token akses, lalu mulai belajar.',
        'course_access_label' => 'Masukkan kode akses kelas',
        'tech_stack_heading' => 'Tech Stack & Technologies',
        'tech_stack_subtitle' => 'Kumpulan teknologi, bahasa pemrograman, framework, dan tools modern yang saya gunakan dalam membangun solusi digital inovatif.',
    ];
}

function default_tech_stacks(): array
{
    return [
        ['id' => 1, 'name' => 'PHP', 'category' => 'Backend', 'logo_path' => 'https://cdn.jsdelivr.net/gh/devicons/devicon/icons/php/php-original.svg', 'sort_order' => 1, 'is_active' => 1],
        ['id' => 2, 'name' => 'JavaScript', 'category' => 'Frontend / Script', 'logo_path' => 'https://cdn.jsdelivr.net/gh/devicons/devicon/icons/javascript/javascript-original.svg', 'sort_order' => 2, 'is_active' => 1],
        ['id' => 3, 'name' => 'Laravel', 'category' => 'PHP Framework', 'logo_path' => 'https://cdn.jsdelivr.net/gh/devicons/devicon/icons/laravel/laravel-original.svg', 'sort_order' => 3, 'is_active' => 1],
        ['id' => 4, 'name' => 'React', 'category' => 'Frontend Library', 'logo_path' => 'https://cdn.jsdelivr.net/gh/devicons/devicon/icons/react/react-original.svg', 'sort_order' => 4, 'is_active' => 1],
        ['id' => 5, 'name' => 'MySQL', 'category' => 'Database', 'logo_path' => 'https://cdn.jsdelivr.net/gh/devicons/devicon/icons/mysql/mysql-original.svg', 'sort_order' => 5, 'is_active' => 1],
        ['id' => 6, 'name' => 'Python', 'category' => 'Language / AI', 'logo_path' => 'https://cdn.jsdelivr.net/gh/devicons/devicon/icons/python/python-original.svg', 'sort_order' => 6, 'is_active' => 1],
        ['id' => 7, 'name' => 'Node.js', 'category' => 'Runtime', 'logo_path' => 'https://cdn.jsdelivr.net/gh/devicons/devicon/icons/nodejs/nodejs-original.svg', 'sort_order' => 7, 'is_active' => 1],
        ['id' => 8, 'name' => 'Tailwind CSS', 'category' => 'Modern CSS', 'logo_path' => 'https://cdn.jsdelivr.net/gh/devicons/devicon/icons/tailwindcss/tailwindcss-original.svg', 'sort_order' => 8, 'is_active' => 1],
        ['id' => 9, 'name' => 'Bootstrap', 'category' => 'CSS Framework', 'logo_path' => 'https://cdn.jsdelivr.net/gh/devicons/devicon/icons/bootstrap/bootstrap-original.svg', 'sort_order' => 9, 'is_active' => 1],
        ['id' => 10, 'name' => 'Git & GitHub', 'category' => 'Version Control', 'logo_path' => 'https://cdn.jsdelivr.net/gh/devicons/devicon/icons/git/git-original.svg', 'sort_order' => 10, 'is_active' => 1],
        ['id' => 11, 'name' => 'Docker', 'category' => 'DevOps', 'logo_path' => 'https://cdn.jsdelivr.net/gh/devicons/devicon/icons/docker/docker-original.svg', 'sort_order' => 11, 'is_active' => 1],
        ['id' => 12, 'name' => 'Figma', 'category' => 'UI/UX Design', 'logo_path' => 'https://cdn.jsdelivr.net/gh/devicons/devicon/icons/figma/figma-original.svg', 'sort_order' => 12, 'is_active' => 1],
    ];
}

function default_projects(): array
{
    return [
        ['id' => 1, 'title' => 'Project 01', 'description' => '', 'image_path' => 'images/project/project-image01.png', 'link_url' => '#', 'sort_order' => 1, 'is_active' => 1],
        ['id' => 2, 'title' => 'Project 02', 'description' => '', 'image_path' => 'images/project/project-image02.png', 'link_url' => '#', 'sort_order' => 2, 'is_active' => 1],
        ['id' => 3, 'title' => 'Project 03', 'description' => '', 'image_path' => 'images/project/project-image03.png', 'link_url' => '#', 'sort_order' => 3, 'is_active' => 1],
        ['id' => 4, 'title' => 'Project 04', 'description' => '', 'image_path' => 'images/project/project-image04.png', 'link_url' => '#', 'sort_order' => 4, 'is_active' => 1],
        ['id' => 5, 'title' => 'Project 05', 'description' => '', 'image_path' => 'images/project/project-image05.png', 'link_url' => '#', 'sort_order' => 5, 'is_active' => 1],
    ];
}

function default_resume_items(): array
{
    return [
        ['id' => 1, 'type' => 'experience', 'year_label' => '2019', 'title' => 'Project Manager', 'subtitle' => 'Best Studio', 'description' => 'Proin ornare non purus ut rutrum. Nulla facilisi. Aliquam laoreet libero ac pharetra feugiat. Cras ac fermentum nunc, a faucibus nunc.', 'sort_order' => 1, 'is_active' => 1],
        ['id' => 2, 'type' => 'experience', 'year_label' => '2018', 'title' => 'UX Designer', 'subtitle' => 'Digital Ace', 'description' => 'Fusce rutrum augue id orci rhoncus molestie. Nunc auctor dignissim lacus vel iaculis.', 'sort_order' => 2, 'is_active' => 1],
        ['id' => 3, 'type' => 'experience', 'year_label' => '2016', 'title' => 'UI Freelancer', 'subtitle' => '', 'description' => 'Sed fringilla vitae enim sit amet cursus. Sed cursus dictum tortor quis pharetra. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas.', 'sort_order' => 3, 'is_active' => 1],
        ['id' => 4, 'type' => 'experience', 'year_label' => '2014', 'title' => 'Junior Designer', 'subtitle' => 'Crafted Co.', 'description' => 'Cras scelerisque scelerisque condimentum. Nullam at volutpat mi. Nunc auctor ipsum eget magna consequat viverra.', 'sort_order' => 4, 'is_active' => 1],
        ['id' => 5, 'type' => 'education', 'year_label' => '2017', 'title' => 'Mobile Web', 'subtitle' => 'Master Design', 'description' => 'Please tell your friends about Tooplate website. That would be very helpful. We need your support.', 'sort_order' => 1, 'is_active' => 1],
        ['id' => 6, 'type' => 'education', 'year_label' => '2015', 'title' => 'User Interfaces', 'subtitle' => 'Creative Agency', 'description' => 'Tooplate is a great website to download HTML templates without any login or email.', 'sort_order' => 2, 'is_active' => 1],
        ['id' => 7, 'type' => 'education', 'year_label' => '2013', 'title' => 'Artwork Design', 'subtitle' => 'New Art School', 'description' => "You can freely use Tooplate's templates for your business or personal sites. You cannot redistribute this template without a permission.", 'sort_order' => 3, 'is_active' => 1],
    ];
}


function default_courses(): array
{
    return [
        ['id' => 1, 'title' => 'Contoh Kelas Portfolio', 'description' => 'Kelas demo untuk menampilkan fitur course, token akses, jadwal mulai-akhir, dan video YouTube.', 'thumbnail_path' => 'images/project/project-image01.png', 'access_token' => 'DEMO123', 'start_at' => null, 'end_at' => null, 'sort_order' => 1, 'is_active' => 1],
    ];
}

function default_course_meetings(): array
{
    return [
        ['id' => 1, 'course_id' => 1, 'title' => 'Pertemuan 1 - Pengenalan', 'description' => 'Contoh pertemuan pertama. Ganti video dan deskripsi dari admin.', 'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'sort_order' => 1, 'is_active' => 1],
    ];
}

function default_news_posts(): array
{
    return [
        [
            'id' => 1,
            'title' => 'Contoh Berita Portfolio',
            'slug' => 'contoh-berita-portfolio',
            'excerpt' => 'Ini adalah contoh berita pertama yang bisa kamu edit dari dashboard admin.',
            'content' => "Tulis isi berita lengkap di sini. Kamu bisa memakai beberapa paragraf agar tampil seperti artikel blog sederhana.\n\nKonten ini dapat diganti dari menu Admin > Berita.",
            'image_path' => 'images/project/project-image01.png',
            'author' => 'Admin',
            'published_at' => date('Y-m-d H:i:s'),
            'is_published' => 1,
        ],
        [
            'id' => 2,
            'title' => 'Update Project Terbaru',
            'slug' => 'update-project-terbaru',
            'excerpt' => 'Ringkasan singkat tentang perkembangan project terbaru.',
            'content' => "Bagikan perkembangan pekerjaan, dokumentasi proses, atau kabar lain yang relevan dengan portfolio kamu.\n\nSetiap berita punya gambar utama, ringkasan, konten, author, tanggal publikasi, dan status publish.",
            'image_path' => 'images/project/project-image02.png',
            'author' => 'Admin',
            'published_at' => date('Y-m-d H:i:s'),
            'is_published' => 1,
        ],
    ];
}

function default_news_carousel_images(): array
{
    return [
        ['id' => 1, 'title' => 'Sorotan Berita', 'caption' => 'Gambar carousel ini bisa diganti dari dashboard admin.', 'image_path' => 'images/project/project-image03.png', 'link_url' => '#', 'sort_order' => 1, 'is_active' => 1],
        ['id' => 2, 'title' => 'Cerita Project', 'caption' => 'Pakai carousel untuk menonjolkan kabar penting.', 'image_path' => 'images/project/project-image04.png', 'link_url' => '#', 'sort_order' => 2, 'is_active' => 1],
    ];
}

function get_settings(?PDO $pdo = null): array
{
    $settings = default_settings();
    $pdo = $pdo ?: pdo(true);

    if (!$pdo) {
        return $settings;
    }

    try {
        $rows = $pdo->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll();
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Throwable $exception) {
        return $settings;
    }

    return $settings;
}

function get_projects(?PDO $pdo = null, bool $activeOnly = true): array
{
    $pdo = $pdo ?: pdo(true);

    if (!$pdo) {
        return default_projects();
    }

    try {
        $sql = 'SELECT * FROM projects';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';

        return $pdo->query($sql)->fetchAll();
    } catch (Throwable $exception) {
        return default_projects();
    }
}

function get_resume_items(?PDO $pdo = null, ?string $type = null, bool $activeOnly = true): array
{
    $pdo = $pdo ?: pdo(true);

    if (!$pdo) {
        return array_values(array_filter(default_resume_items(), function (array $item) use ($type, $activeOnly): bool {
            if ($type !== null && $item['type'] !== $type) {
                return false;
            }

            return !$activeOnly || (int) $item['is_active'] === 1;
        }));
    }

    try {
        $conditions = [];
        $params = [];

        if ($type !== null) {
            $conditions[] = 'type = ?';
            $params[] = $type;
        }

        if ($activeOnly) {
            $conditions[] = 'is_active = 1';
        }

        $sql = 'SELECT * FROM resume_items';
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';

        $statement = $pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    } catch (Throwable $exception) {
        return array_values(array_filter(default_resume_items(), function (array $item) use ($type, $activeOnly): bool {
            if ($type !== null && $item['type'] !== $type) {
                return false;
            }

            return !$activeOnly || (int) $item['is_active'] === 1;
        }));
    }
}

function get_tech_stacks(?PDO $pdo = null, bool $activeOnly = true): array
{
    $pdo = $pdo ?: pdo(true);

    if (!$pdo) {
        $items = default_tech_stacks();
        return $activeOnly ? array_values(array_filter($items, fn(array $i): bool => (int) $i['is_active'] === 1)) : $items;
    }

    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS tech_stacks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            category VARCHAR(50) DEFAULT "Tech",
            logo_path VARCHAR(255) NOT NULL,
            sort_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_sort_order (sort_order),
            KEY idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $count = (int) $pdo->query('SELECT COUNT(*) FROM tech_stacks')->fetchColumn();
        if ($count === 0) {
            $stmt = $pdo->prepare('INSERT INTO tech_stacks (name, category, logo_path, sort_order, is_active) VALUES (?, ?, ?, ?, ?)');
            foreach (default_tech_stacks() as $item) {
                $stmt->execute([
                    $item['name'],
                    $item['category'] ?? 'Tech',
                    $item['logo_path'],
                    (int) $item['sort_order'],
                    (int) $item['is_active'],
                ]);
            }
        }

        $sql = 'SELECT * FROM tech_stacks';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';

        return $pdo->query($sql)->fetchAll();
    } catch (Throwable $exception) {
        $items = default_tech_stacks();
        return $activeOnly ? array_values(array_filter($items, fn(array $i): bool => (int) $i['is_active'] === 1)) : $items;
    }
}

function get_news_posts(?PDO $pdo = null, bool $publishedOnly = true, ?int $limit = null): array
{
    $pdo = $pdo ?: pdo(true);

    if (!$pdo) {
        $posts = default_news_posts();
        return $limit === null ? $posts : array_slice($posts, 0, $limit);
    }

    try {
        $conditions = [];
        if ($publishedOnly) {
            $conditions[] = 'is_published = 1';
            $conditions[] = '(published_at IS NULL OR published_at <= NOW())';
        }

        $sql = 'SELECT * FROM news_posts';
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY COALESCE(published_at, created_at) DESC, id DESC';

        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, $limit);
        }

        return $pdo->query($sql)->fetchAll();
    } catch (Throwable $exception) {
        $posts = default_news_posts();
        return $limit === null ? $posts : array_slice($posts, 0, $limit);
    }
}

function get_news_post_by_slug(string $slug, ?PDO $pdo = null, bool $publishedOnly = true): ?array
{
    $pdo = $pdo ?: pdo(true);

    if (!$pdo) {
        foreach (default_news_posts() as $post) {
            if ($post['slug'] === $slug) {
                return $post;
            }
        }

        return null;
    }

    try {
        $sql = 'SELECT * FROM news_posts WHERE slug = ?';
        if ($publishedOnly) {
            $sql .= ' AND is_published = 1 AND (published_at IS NULL OR published_at <= NOW())';
        }
        $sql .= ' LIMIT 1';

        $statement = $pdo->prepare($sql);
        $statement->execute([$slug]);
        $post = $statement->fetch();

        return $post ?: null;
    } catch (Throwable $exception) {
        return null;
    }
}

function get_news_carousel_images(?PDO $pdo = null, bool $activeOnly = true): array
{
    $pdo = $pdo ?: pdo(true);

    if (!$pdo) {
        return default_news_carousel_images();
    }

    try {
        $sql = 'SELECT * FROM news_carousel_images';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';

        return $pdo->query($sql)->fetchAll();
    } catch (Throwable $exception) {
        return default_news_carousel_images();
    }
}


function get_courses(?PDO $pdo = null, bool $activeOnly = true): array
{
    $pdo = $pdo ?: pdo(true);

    if (!$pdo) {
        return array_values(array_filter(default_courses(), fn (array $course): bool => !$activeOnly || (int) $course['is_active'] === 1));
    }

    try {
        $sql = 'SELECT * FROM courses';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';

        return $pdo->query($sql)->fetchAll();
    } catch (Throwable $exception) {
        return array_values(array_filter(default_courses(), fn (array $course): bool => !$activeOnly || (int) $course['is_active'] === 1));
    }
}

function get_course_by_id(int $id, ?PDO $pdo = null, bool $activeOnly = true): ?array
{
    foreach (get_courses($pdo, $activeOnly) as $course) {
        if ((int) $course['id'] === $id) {
            return $course;
        }
    }

    return null;
}

function get_course_meetings(int $courseId, ?PDO $pdo = null, bool $activeOnly = true): array
{
    $pdo = $pdo ?: pdo(true);

    if (!$pdo) {
        return array_values(array_filter(default_course_meetings(), function (array $meeting) use ($courseId, $activeOnly): bool {
            return (int) $meeting['course_id'] === $courseId && (!$activeOnly || (int) $meeting['is_active'] === 1);
        }));
    }

    try {
        $sql = 'SELECT * FROM course_meetings WHERE course_id = ?';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';

        $statement = $pdo->prepare($sql);
        $statement->execute([$courseId]);

        return $statement->fetchAll();
    } catch (Throwable $exception) {
        return array_values(array_filter(default_course_meetings(), function (array $meeting) use ($courseId, $activeOnly): bool {
            return (int) $meeting['course_id'] === $courseId && (!$activeOnly || (int) $meeting['is_active'] === 1);
        }));
    }
}



function get_course_progress_summary(int $userId, int $courseId, ?PDO $pdo = null): array
{
    $pdo = $pdo ?: pdo(true);
    $summary = ['total' => 0, 'completed' => 0, 'percent' => 0];
    if (!$pdo) return $summary;
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM course_meetings WHERE course_id = ? AND is_active = 1');
        $stmt->execute([$courseId]);
        $summary['total'] = (int) $stmt->fetchColumn();
        if ($summary['total'] <= 0) return $summary;
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM course_progress cp INNER JOIN course_meetings cm ON cm.id = cp.meeting_id WHERE cp.user_id = ? AND cm.course_id = ? AND cm.is_active = 1');
        $stmt->execute([$userId, $courseId]);
        $summary['completed'] = (int) $stmt->fetchColumn();
        $summary['percent'] = (int) round(($summary['completed'] / $summary['total']) * 100);
    } catch (Throwable $exception) {
        return $summary;
    }
    return $summary;
}

function get_completed_meeting_ids(int $userId, int $courseId, ?PDO $pdo = null): array
{
    $pdo = $pdo ?: pdo(true);
    if (!$pdo) return [];
    try {
        $stmt = $pdo->prepare('SELECT cp.meeting_id FROM course_progress cp INNER JOIN course_meetings cm ON cm.id = cp.meeting_id WHERE cp.user_id = ? AND cm.course_id = ?');
        $stmt->execute([$userId, $courseId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'meeting_id'));
    } catch (Throwable $exception) {
        return [];
    }
}

function mark_course_meeting_completed(int $userId, int $meetingId, ?PDO $pdo = null): bool
{
    $pdo = $pdo ?: pdo(true);
    if (!$pdo) return false;
    try {
        $stmt = $pdo->prepare('INSERT IGNORE INTO course_progress (user_id, meeting_id) VALUES (?, ?)');
        $stmt->execute([$userId, $meetingId]);
        return true;
    } catch (Throwable $exception) {
        return false;
    }
}

function get_quizzes_by_meeting_ids(array $meetingIds, ?PDO $pdo = null): array
{
    $pdo = $pdo ?: pdo(true);
    if (!$pdo || !$meetingIds) return [];
    try {
        $ids = array_values(array_unique(array_map('intval', $meetingIds)));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM course_quizzes WHERE meeting_id IN ($placeholders) AND is_active = 1 ORDER BY sort_order ASC, id ASC");
        $stmt->execute($ids);
        $grouped = [];
        foreach ($stmt->fetchAll() as $quiz) {
            $grouped[(int) $quiz['meeting_id']][] = $quiz;
        }
        return $grouped;
    } catch (Throwable $exception) {
        return [];
    }
}

function get_course_quizzes(int $courseId, ?PDO $pdo = null, bool $activeOnly = false): array
{
    $pdo = $pdo ?: pdo(true);
    if (!$pdo) return [];
    try {
        $sql = 'SELECT q.*, cm.title AS meeting_title FROM course_quizzes q INNER JOIN course_meetings cm ON cm.id = q.meeting_id WHERE cm.course_id = ?';
        if ($activeOnly) $sql .= ' AND q.is_active = 1';
        $sql .= ' ORDER BY cm.sort_order ASC, q.sort_order ASC, q.id ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$courseId]);
        return $stmt->fetchAll();
    } catch (Throwable $exception) {
        return [];
    }
}

function get_latest_quiz_attempts(int $userId, array $quizIds, ?PDO $pdo = null): array
{
    $pdo = $pdo ?: pdo(true);
    if (!$pdo || !$quizIds) return [];
    try {
        $ids = array_values(array_unique(array_map('intval', $quizIds)));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT qa.* FROM quiz_attempts qa INNER JOIN (SELECT quiz_id, MAX(id) latest_id FROM quiz_attempts WHERE user_id = ? AND quiz_id IN ($placeholders) GROUP BY quiz_id) latest ON latest.latest_id = qa.id");
        $stmt->execute(array_merge([$userId], $ids));
        $attempts = [];
        foreach ($stmt->fetchAll() as $attempt) {
            $attempts[(int) $attempt['quiz_id']] = $attempt;
        }
        return $attempts;
    } catch (Throwable $exception) {
        return [];
    }
}

function submit_quiz_attempt(int $userId, int $quizId, string $answer, ?PDO $pdo = null): array
{
    $pdo = $pdo ?: pdo(true);
    if (!$pdo) return ['ok' => false, 'message' => 'Database tidak tersedia.'];
    $answer = strtoupper(trim($answer));
    if (!in_array($answer, ['A','B','C','D'], true)) return ['ok' => false, 'message' => 'Jawaban tidak valid.'];
    try {
        $stmt = $pdo->prepare('SELECT * FROM course_quizzes WHERE id = ? AND is_active = 1');
        $stmt->execute([$quizId]);
        $quiz = $stmt->fetch();
        if (!$quiz) return ['ok' => false, 'message' => 'Quiz tidak ditemukan.'];
        $isCorrect = $answer === strtoupper((string) $quiz['correct_option']) ? 1 : 0;
        $stmt = $pdo->prepare('INSERT INTO quiz_attempts (user_id, quiz_id, selected_option, is_correct) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $quizId, $answer, $isCorrect]);
        return ['ok' => true, 'message' => $isCorrect ? 'Jawaban benar.' : 'Jawaban belum tepat.', 'is_correct' => (bool) $isCorrect];
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => 'Gagal menyimpan jawaban quiz.'];
    }
}

function generate_course_token(int $length = 10): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    $token = '';

    for ($i = 0; $i < $length; $i++) {
        $token .= $alphabet[random_int(0, $max)];
    }

    return $token;
}

function normalize_course_datetime(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function format_course_datetime(?string $value, string $format = 'd M Y H:i'): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '';
    }

    return date($format, $timestamp);
}

function datetime_local_value(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d\\TH:i', $timestamp);
}

function course_availability_status(array $course, ?int $now = null): array
{
    $now = $now ?: time();
    $startAt = trim((string) ($course['start_at'] ?? ''));
    $endAt = trim((string) ($course['end_at'] ?? ''));
    $startTs = $startAt !== '' ? strtotime($startAt) : false;
    $endTs = $endAt !== '' ? strtotime($endAt) : false;

    if ($startTs !== false && $now < $startTs) {
        return [
            'is_open' => false,
            'code' => 'not_started',
            'label' => 'Belum mulai',
            'message' => 'Kelas ini baru bisa diakses mulai ' . format_course_datetime($startAt) . '.',
        ];
    }

    if ($endTs !== false && $now > $endTs) {
        return [
            'is_open' => false,
            'code' => 'ended',
            'label' => 'Berakhir',
            'message' => 'Masa akses kelas ini sudah berakhir pada ' . format_course_datetime($endAt) . '.',
        ];
    }

    return [
        'is_open' => true,
        'code' => 'open',
        'label' => 'Aktif',
        'message' => 'Kelas bisa diakses sekarang.',
    ];
}

function youtube_embed_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    if (preg_match('~youtube\.com/embed/([A-Za-z0-9_-]{6,})~', $url, $matches)) {
        return 'https://www.youtube.com/embed/' . $matches[1];
    }

    if (preg_match('~youtu\.be/([A-Za-z0-9_-]{6,})~', $url, $matches)) {
        return 'https://www.youtube.com/embed/' . $matches[1];
    }

    if (preg_match('~[?&]v=([A-Za-z0-9_-]{6,})~', $url, $matches)) {
        return 'https://www.youtube.com/embed/' . $matches[1];
    }

    return $url;
}

function make_slug(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?: '';
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'berita-' . date('YmdHis');
}

function save_settings(array $settings): void
{
    $statement = pdo()->prepare(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );

    foreach ($settings as $key => $value) {
        $statement->execute([$key, (string) $value]);
    }
}


function user_logged_in(): bool
{
    start_app_session();
    return !empty($_SESSION['user_id']);
}

function current_user_id(): int
{
    start_app_session();
    return (int) ($_SESSION['user_id'] ?? 0);
}

function current_user_name(): string
{
    start_app_session();
    return (string) ($_SESSION['user_name'] ?? 'User');
}

function current_user_email(): string
{
    start_app_session();
    return (string) ($_SESSION['user_email'] ?? '');
}

function set_user_session(array $user): void
{
    start_app_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = (string) $user['name'];
    $_SESSION['user_email'] = (string) $user['email'];
    $_SESSION['user_login_at'] = time();
}

function clear_user_session(): void
{
    start_app_session();
    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['user_login_at']);
}

function require_user_login(string $redirectTo = 'user_login.php'): void
{
    if (!user_logged_in()) {
        redirect($redirectTo);
    }

    $pdo = pdo(true);
    if ($pdo) {
        try {
            $st = $pdo->prepare('SELECT is_active FROM users WHERE id = ? LIMIT 1');
            $st->execute([current_user_id()]);
            $user = $st->fetch();
            if (!$user || (int) ($user['is_active'] ?? 0) !== 1) {
                clear_user_session();
                set_user_flash('danger', 'Akun kamu sudah dinonaktifkan oleh admin.');
                redirect($redirectTo);
            }
        } catch (Throwable $e) {
            // Jika database belum siap, biarkan proses login berjalan seperti sebelumnya.
        }
    }
}

function set_user_flash(string $type, string $message): void
{
    start_app_session();
    $_SESSION['user_flash'] = ['type' => $type, 'message' => $message];
}

function get_user_flash(): ?array
{
    start_app_session();
    if (empty($_SESSION['user_flash'])) {
        return null;
    }
    $flash = $_SESSION['user_flash'];
    unset($_SESSION['user_flash']);
    return $flash;
}

function find_user_by_email(string $email, ?PDO $pdo = null): ?array
{
    $pdo = $pdo ?: pdo(true);
    if (!$pdo) return null;
    try {
        $st = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function user_has_course_enrollment(int $userId, int $courseId, ?PDO $pdo = null): bool
{
    $pdo = $pdo ?: pdo(true);
    if (!$pdo || $userId <= 0 || $courseId <= 0) return false;
    try {
        $st = $pdo->prepare('SELECT id FROM course_enrollments WHERE user_id = ? AND course_id = ? AND COALESCE(is_active, 1) = 1 LIMIT 1');
        $st->execute([$userId, $courseId]);
        return (bool) $st->fetch();
    } catch (Throwable $e) {
        try {
            $st = $pdo->prepare('SELECT id FROM course_enrollments WHERE user_id = ? AND course_id = ? LIMIT 1');
            $st->execute([$userId, $courseId]);
            return (bool) $st->fetch();
        } catch (Throwable $e2) {
            return false;
        }
    }
}

function get_user_enrolled_courses(int $userId, ?PDO $pdo = null): array
{
    $pdo = $pdo ?: pdo(true);
    if (!$pdo || $userId <= 0) return [];
    try {
        $sql = 'SELECT c.*, e.created_at AS enrolled_at, COALESCE(e.is_active, 1) AS enrollment_is_active FROM course_enrollments e INNER JOIN courses c ON c.id = e.course_id WHERE e.user_id = ? AND COALESCE(e.is_active, 1) = 1 ORDER BY e.created_at DESC, c.sort_order ASC';
        $st = $pdo->prepare($sql);
        $st->execute([$userId]);
        return $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function token_availability_status(array $token, ?int $now = null): array
{
    $now = $now ?: time();
    $startAt = trim((string) ($token['start_at'] ?? ''));
    $endAt = trim((string) ($token['end_at'] ?? ''));
    $startTs = $startAt !== '' ? strtotime($startAt) : false;
    $endTs = $endAt !== '' ? strtotime($endAt) : false;
    if ((int) ($token['is_active'] ?? 0) !== 1) {
        return ['is_open' => false, 'message' => 'Token enrollment tidak aktif.'];
    }
    if ($startTs !== false && $now < $startTs) {
        return ['is_open' => false, 'message' => 'Token baru bisa digunakan mulai ' . format_course_datetime($startAt) . '.'];
    }
    if ($endTs !== false && $now > $endTs) {
        return ['is_open' => false, 'message' => 'Token sudah berakhir pada ' . format_course_datetime($endAt) . '.'];
    }
    return ['is_open' => true, 'message' => 'Token bisa digunakan.'];
}

function enroll_user_with_token(int $userId, string $tokenValue, ?int $onlyCourseId = null, ?PDO $pdo = null): array
{
    $pdo = $pdo ?: pdo(true);
    $tokenValue = trim($tokenValue);
    if (!$pdo) return ['ok' => false, 'message' => 'Database belum tersambung.', 'count' => 0];
    if ($userId <= 0) return ['ok' => false, 'message' => 'Silakan login user terlebih dahulu.', 'count' => 0];
    if ($tokenValue === '') return ['ok' => false, 'message' => 'Token enrollment wajib diisi.', 'count' => 0];

    try {
        $st = $pdo->prepare('SELECT * FROM enrollment_tokens WHERE token = ? LIMIT 1');
        $st->execute([$tokenValue]);
        $token = $st->fetch();
        if (!$token) return ['ok' => false, 'message' => 'Token enrollment tidak ditemukan.', 'count' => 0];
        $status = token_availability_status($token);
        if (!$status['is_open']) return ['ok' => false, 'message' => $status['message'], 'count' => 0];

        $sql = 'SELECT c.* FROM enrollment_token_courses tc INNER JOIN courses c ON c.id = tc.course_id WHERE tc.token_id = ? AND c.is_active = 1';
        $params = [(int) $token['id']];
        if ($onlyCourseId !== null) {
            $sql .= ' AND c.id = ?';
            $params[] = $onlyCourseId;
        }
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $courses = $st->fetchAll();
        if (!$courses) return ['ok' => false, 'message' => 'Token ini tidak terhubung ke course yang dipilih.', 'count' => 0];

        $insert = $pdo->prepare('INSERT IGNORE INTO course_enrollments (user_id, course_id, token_id) VALUES (?, ?, ?)');
        $added = 0;
        foreach ($courses as $course) {
            $insert->execute([$userId, (int) $course['id'], (int) $token['id']]);
            $added += $insert->rowCount() > 0 ? 1 : 0;
        }
        return ['ok' => true, 'message' => $added > 0 ? 'Enrollment berhasil untuk ' . $added . ' course.' : 'Akun kamu sudah terdaftar pada course tersebut.', 'count' => $added];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Enrollment gagal. Jalankan migration user enrollment terlebih dahulu.', 'count' => 0];
    }
}


function log_course_access(int $userId, int $courseId, ?PDO $pdo = null): void
{
    $pdo = $pdo ?: pdo(true);
    if (!$pdo || $userId <= 0 || $courseId <= 0) return;

    try {
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $agent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $st = $pdo->prepare('INSERT INTO course_access_logs (user_id, course_id, ip_address, user_agent) VALUES (?, ?, ?, ?)');
        $st->execute([$userId, $courseId, $ip, $agent]);
    } catch (Throwable $e) {
        // Monitoring bersifat tambahan; jangan ganggu akses belajar bila tabel belum dimigrasi.
    }
}

function get_admin_user_course_monitoring(?PDO $pdo = null, int $limit = 10, int $offset = 0): array
{
    $pdo = $pdo ?: pdo(true);
    if (!$pdo) return [];

    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);

    try {
        $sql = "SELECT
                    e.id AS enrollment_id,
                    COALESCE(e.is_active, 1) AS enrollment_is_active,
                    u.id AS user_id,
                    u.name AS user_name,
                    u.email AS user_email,
                    u.is_active AS user_is_active,
                    c.id AS course_id,
                    c.title AS course_title,
                    c.is_active AS course_is_active,
                    c.start_at,
                    c.end_at,
                    e.created_at AS enrolled_at,
                    MAX(cal.accessed_at) AS last_accessed_at,
                    COUNT(DISTINCT cal.id) AS access_count,
                    COUNT(DISTINCT cm.id) AS total_meetings,
                    COUNT(DISTINCT cp.id) AS completed_meetings
                FROM course_enrollments e
                INNER JOIN users u ON u.id = e.user_id
                INNER JOIN courses c ON c.id = e.course_id
                LEFT JOIN course_access_logs cal ON cal.user_id = u.id AND cal.course_id = c.id
                LEFT JOIN course_meetings cm ON cm.course_id = c.id AND cm.is_active = 1
                LEFT JOIN course_progress cp ON cp.user_id = u.id AND cp.meeting_id = cm.id
                GROUP BY e.id, e.is_active, u.id, u.name, u.email, u.is_active, c.id, c.title, c.is_active, c.start_at, c.end_at, e.created_at
                ORDER BY last_accessed_at DESC, e.created_at DESC
                LIMIT :limit OFFSET :offset";
        $st = $pdo->prepare($sql);
        $st->bindValue(':limit', $limit, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function count_admin_user_course_monitoring(?PDO $pdo = null): int
{
    $pdo = $pdo ?: pdo(true);
    if (!$pdo) return 0;
    try {
        return (int) $pdo->query('SELECT COUNT(*) FROM course_enrollments')->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function set_enrollment_active_status(int $enrollmentId, bool $isActive, ?PDO $pdo = null): bool
{
    $pdo = $pdo ?: pdo(true);
    if (!$pdo || $enrollmentId <= 0) return false;

    try {
        $st = $pdo->prepare('UPDATE course_enrollments SET is_active = ? WHERE id = ?');
        $st->execute([$isActive ? 1 : 0, $enrollmentId]);
        return $st->rowCount() >= 0;
    } catch (Throwable $e) {
        return false;
    }
}

function set_user_active_status(int $userId, bool $isActive, ?PDO $pdo = null): bool
{
    $pdo = $pdo ?: pdo(true);
    if (!$pdo || $userId <= 0) return false;

    try {
        $st = $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?');
        $st->execute([$isActive ? 1 : 0, $userId]);
        return $st->rowCount() >= 0;
    } catch (Throwable $e) {
        return false;
    }
}

function get_enrollment_tokens(?PDO $pdo = null): array
{
    $pdo = $pdo ?: pdo(true);
    if (!$pdo) return [];
    try {
        $tokens = $pdo->query('SELECT * FROM enrollment_tokens ORDER BY created_at DESC, id DESC')->fetchAll();
        $st = $pdo->prepare('SELECT course_id FROM enrollment_token_courses WHERE token_id = ?');
        foreach ($tokens as &$token) {
            $st->execute([(int) $token['id']]);
            $token['course_ids'] = array_map('intval', array_column($st->fetchAll(), 'course_id'));
        }
        return $tokens;
    } catch (Throwable $e) {
        return [];
    }
}

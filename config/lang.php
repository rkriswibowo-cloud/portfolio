<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

/**
 * Mendeteksi dan mengembalikan bahasa aktif ('id' atau 'en').
 */
function current_lang(): string
{
    // 1. Cek query parameter ?lang=
    if (isset($_GET['lang'])) {
        $requested = strtolower(trim((string) $_GET['lang']));
        if (in_array($requested, ['id', 'en'], true)) {
            $_SESSION['app_lang'] = $requested;
            if (!headers_sent()) {
                setcookie('app_lang', $requested, time() + (86400 * 365), '/');
            }
            return $requested;
        }
    }

    // 2. Cek Session
    if (isset($_SESSION['app_lang']) && in_array($_SESSION['app_lang'], ['id', 'en'], true)) {
        return (string) $_SESSION['app_lang'];
    }

    // 3. Cek Cookie
    if (isset($_COOKIE['app_lang']) && in_array($_COOKIE['app_lang'], ['id', 'en'], true)) {
        $_SESSION['app_lang'] = (string) $_COOKIE['app_lang'];
        return (string) $_COOKIE['app_lang'];
    }

    // Default: Indonesia ('id')
    return 'id';
}

/**
 * Set bahasa aplikasi secara manual.
 */
function set_lang(string $lang): void
{
    $lang = in_array($lang, ['id', 'en'], true) ? $lang : 'id';
    $_SESSION['app_lang'] = $lang;
    if (!headers_sent()) {
        setcookie('app_lang', $lang, time() + (86400 * 365), '/');
    }
}

/**
 * Membuat URL untuk switch bahasa dengan mempertahankan parameter query saat ini.
 */
function lang_url(string $targetLang): string
{
    $targetLang = in_array($targetLang, ['id', 'en'], true) ? $targetLang : 'id';
    $params = $_GET;
    $params['lang'] = $targetLang;
    
    $uri = (string) ($_SERVER['PHP_SELF'] ?? 'index.php');
    return $uri . '?' . http_build_query($params);
}

/**
 * Kamus translasi dwibahasa (Indonesian & English).
 */
function lang_dictionary(): array
{
    return [
        // --- NAVBAR & HEADER ---
        'nav_about' => [
            'id' => 'Tentang',
            'en' => 'About',
        ],
        'nav_projects' => [
            'id' => 'Projects',
            'en' => 'Projects',
        ],
        'nav_resume' => [
            'id' => 'Resume',
            'en' => 'Resume',
        ],
        'nav_publications' => [
            'id' => 'Publikasi',
            'en' => 'Publications',
        ],
        'nav_contact' => [
            'id' => 'Kontak',
            'en' => 'Contact',
        ],
        'nav_news' => [
            'id' => 'Berita',
            'en' => 'News',
        ],
        'nav_course' => [
            'id' => 'Course',
            'en' => 'Courses',
        ],
        'nav_free' => [
            'id' => 'Gratis (Free)',
            'en' => 'Free Courses',
        ],
        'nav_premium' => [
            'id' => 'Premium LMS',
            'en' => 'Premium LMS',
        ],
        'nav_cv_generator' => [
            'id' => 'CV Generator',
            'en' => 'CV Generator',
        ],
        'nav_my_courses' => [
            'id' => 'Kelas Saya',
            'en' => 'My Courses',
        ],
        'nav_color_mode' => [
            'id' => 'Mode Warna',
            'en' => 'Color Mode',
        ],
        'nav_login' => [
            'id' => 'Masuk',
            'en' => 'Login',
        ],
        'nav_logout' => [
            'id' => 'Keluar',
            'en' => 'Logout',
        ],

        // --- HERO SECTION ---
        'hero_welcome' => [
            'id' => 'Selamat datang di website portofolio saya',
            'en' => 'Welcome to my portfolio website',
        ],
        'hero_i_am' => [
            'id' => 'Halo, saya',
            'en' => 'Hello, I am',
        ],
        'hero_contact_btn' => [
            'id' => 'Hubungi Saya',
            'en' => 'Contact Me',
        ],
        'hero_download_cv' => [
            'id' => 'Unduh Resume / CV',
            'en' => 'Download Resume / CV',
        ],
        'hero_role_1' => [
            'id' => 'Dosen & Peneliti',
            'en' => 'Lecturer & Researcher',
        ],
        'hero_role_2' => [
            'id' => 'Pengembang Web Fullstack',
            'en' => 'Fullstack Web Developer',
        ],
        'hero_role_3' => [
            'id' => 'Spesialis AI & Sistem Cerdas',
            'en' => 'AI & Intelligent Systems Specialist',
        ],
        'hero_role_4' => [
            'id' => 'Desainer UI/UX & Konsultan IT',
            'en' => 'UI/UX Designer & IT Consultant',
        ],

        // --- PROJECTS SECTION ---
        'projects_badge' => [
            'id' => 'Portofolio Karya',
            'en' => 'Selected Works',
        ],
        'projects_heading' => [
            'id' => 'Karya & Project yang Telah Saya Bangun',
            'en' => 'Things I Have Designed & Built',
        ],
        'projects_subtitle' => [
            'id' => 'Beberapa proyek aplikasi, sistem informasi, dan riset teknologi yang telah diselesaikan.',
            'en' => 'A showcase of web applications, information systems, and technology solutions I have developed.',
        ],
        'projects_view_detail' => [
            'id' => 'Lihat Project',
            'en' => 'View Project',
        ],

        // --- RESUME SECTION ---
        'resume_experiences' => [
            'id' => 'Pengalaman Kerja',
            'en' => 'Experiences',
        ],
        'resume_educations' => [
            'id' => 'Riwayat Pendidikan',
            'en' => 'Educations',
        ],

        // --- ACADEMIC & RESEARCH SECTION ---
        'academic_heading' => [
            'id' => 'Publikasi & Riset',
            'en' => 'Publications & Research',
        ],
        'academic_subtitle' => [
            'id' => 'Kumpulan publikasi jurnal internasional/nasional, riset terapan, program pengabdian masyarakat, HKI/paten, dan buku karya akademik.',
            'en' => 'Collection of international/national journal publications, applied research, community service programs, patents/IPR, and academic books.',
        ],
        'academic_profile_title' => [
            'id' => 'Profil Peneliti',
            'en' => 'Research Profile',
        ],
        'academic_profile_subtitle' => [
            'id' => 'Profil Peneliti & Tautan Publikasi Terindeks',
            'en' => 'Academic Profile & Indexed Publication Links',
        ],
        'academic_email_label' => [
            'id' => 'Email',
            'en' => 'Email',
        ],
        'academic_scholar_meta' => [
            'id' => 'Sitasi & Indeks Karya',
            'en' => 'Citations & Indexed Papers',
        ],
        'academic_scopus_meta' => [
            'id' => 'Author Profile Terindeks',
            'en' => 'Indexed Author Profile',
        ],
        'academic_sinta_meta' => [
            'id' => 'Profil Dosen & Peneliti',
            'en' => 'National Lecturer & Researcher Profile',
        ],
        'academic_linkedin_meta' => [
            'id' => 'Koneksi Profesional',
            'en' => 'Professional Network',
        ],
        
        // Tab names
        'academic_tab_publications' => [
            'id' => 'Publikasi',
            'en' => 'Publications',
        ],
        'academic_tab_research' => [
            'id' => 'Riset',
            'en' => 'Research',
        ],
        'academic_tab_service' => [
            'id' => 'Pengabdian',
            'en' => 'Community Service',
        ],
        'academic_tab_patents' => [
            'id' => 'HKI / Paten',
            'en' => 'Patents / IPR',
        ],
        'academic_tab_books' => [
            'id' => 'Buku',
            'en' => 'Books',
        ],

        // Subcategory headings
        'academic_sub_intl_journals' => [
            'id' => 'Jurnal Internasional',
            'en' => 'International Journals',
        ],
        'academic_sub_intl_proceedings' => [
            'id' => 'Prosiding Internasional',
            'en' => 'International Proceedings',
        ],
        'academic_sub_nat_journals' => [
            'id' => 'Jurnal Nasional',
            'en' => 'National Journals',
        ],
        'academic_open_link' => [
            'id' => 'Buka URL',
            'en' => 'Open Link',
        ],
        'academic_no_data' => [
            'id' => 'Belum ada data pada kategori ini.',
            'en' => 'No records found in this category.',
        ],

        // --- TECH STACK SECTION ---
        'tech_heading' => [
            'id' => 'Tech Stack & Teknologi',
            'en' => 'Tech Stack & Technologies',
        ],
        'tech_subtitle' => [
            'id' => 'Kumpulan teknologi, bahasa pemrograman, framework, dan tools modern yang saya gunakan dalam membangun solusi digital inovatif.',
            'en' => 'A curated set of technologies, programming languages, frameworks, and modern tools I use to build scalable digital solutions.',
        ],

        // --- CONTACT SECTION ---
        'contact_heading' => [
            'id' => 'Tertarik berdiskusi atau berkolaborasi?',
            'en' => 'Interested in talking or collaborating?',
        ],
        'contact_say_hi' => [
            'id' => 'Kirim Pesan',
            'en' => 'Say Hi',
        ],
        'contact_name_placeholder' => [
            'id' => 'Nama Lengkap Anda',
            'en' => 'Your Full Name',
        ],
        'contact_email_placeholder' => [
            'id' => 'Alamat Email Anda',
            'en' => 'Your Email Address',
        ],
        'contact_message_placeholder' => [
            'id' => 'Tuliskan pesan atau pertanyaan Anda di sini...',
            'en' => 'Write your message or inquiry here...',
        ],
        'contact_send_btn' => [
            'id' => 'Kirim Pesan',
            'en' => 'Send Message',
        ],
        'contact_required' => [
            'id' => 'Nama, email, dan pesan wajib diisi.',
            'en' => 'Name, email, and message are required.',
        ],
        'contact_invalid_email' => [
            'id' => 'Format email belum benar.',
            'en' => 'Please provide a valid email address.',
        ],
        'contact_success' => [
            'id' => 'Pesan berhasil dikirim. Terima kasih!',
            'en' => 'Message successfully sent. Thank you!',
        ],

        // --- COURSE PAGES ---
        'course_page_title' => [
            'id' => 'Daftar Kelas Pembelajaran',
            'en' => 'Learning Courses & Classes',
        ],
        'course_page_subtitle' => [
            'id' => 'Pilih kelas, masukkan token akses, lalu mulai belajar secara terstruktur.',
            'en' => 'Choose a course, enter your access token, and start learning effectively.',
        ],
        'course_search_placeholder' => [
            'id' => 'Cari judul course...',
            'en' => 'Search course title...',
        ],
        'course_search_btn' => [
            'id' => 'Cari',
            'en' => 'Search',
        ],
        'course_enroll_btn' => [
            'id' => 'Masuk Kelas',
            'en' => 'Enroll Course',
        ],
        'course_open_btn' => [
            'id' => 'Buka Kelas',
            'en' => 'Open Course',
        ],
        'course_meetings' => [
            'id' => 'Pertemuan',
            'en' => 'Meetings',
        ],

        // --- NEWS PAGES ---
        'news_page_title' => [
            'id' => 'Berita & Artikel Terbaru',
            'en' => 'Latest News & Articles',
        ],
        'news_page_subtitle' => [
            'id' => 'Kumpulan kabar, cerita project, dan update teknologi terbaru.',
            'en' => 'Updates, project highlights, technology insights, and articles.',
        ],
        'news_read_more' => [
            'id' => 'Baca Selengkapnya',
            'en' => 'Read More',
        ],
        'news_back_btn' => [
            'id' => 'Kembali ke Daftar Berita',
            'en' => 'Back to News List',
        ],

        // --- MY COURSES PAGE ---
        'my_courses_heading' => [
            'id' => 'Kelas Saya',
            'en' => 'My Courses',
        ],
        'my_courses_subtitle' => [
            'id' => 'Kelola akses pembelajaran aktif Anda atau masukkan token baru.',
            'en' => 'Manage your active learning access or enter a new course enrollment token.',
        ],
        'my_courses_active_heading' => [
            'id' => 'Kelas Aktif',
            'en' => 'Active Courses',
        ],
        'my_courses_history_heading' => [
            'id' => 'Riwayat & Aktivitas Saya',
            'en' => 'My History & Activities',
        ],
        'my_courses_cv_heading' => [
            'id' => 'Riwayat & Dokumen CV Generator',
            'en' => 'CV Generator History & Cloud Documents',
        ],
        'my_courses_cv_open' => [
            'id' => 'Buka CV Generator',
            'en' => 'Open CV Generator',
        ],

        // --- FOOTER ---
        'footer_rights' => [
            'id' => 'Hak cipta dilindungi undang-undang.',
            'en' => 'All rights reserved.',
        ],
    ];
}

/**
 * Helper translasi: mengembalikan teks sesuai bahasa aktif ('id' atau 'en').
 */
function __t(string $key, ?string $fallback = null): string
{
    $lang = current_lang();
    $dict = lang_dictionary();

    if (isset($dict[$key][$lang])) {
        return (string) $dict[$key][$lang];
    }

    // Jika key tidak ditemukan, gunakan fallback atau key itu sendiri
    if ($fallback !== null) {
        return $fallback;
    }

    if (isset($dict[$key]['id'])) {
        return (string) $dict[$key]['id'];
    }

    return $key;
}

/**
 * Helper terjemahan subkategori publikasi
 */
function translate_subcategory(string $sub): string
{
    $lang = current_lang();
    if ($lang === 'en') {
        $map = [
            'Jurnal Internasional' => 'International Journals',
            'Prosiding Internasional' => 'International Proceedings',
            'Jurnal Nasional' => 'National Journals',
            'Penelitian' => 'Research',
            'Pengabdian Masyarakat' => 'Community Service',
            'Hak Cipta' => 'Copyrights / Patents',
            'Paten Sederhana' => 'Simple Patents',
            'Buku Referensi' => 'Reference Books',
            'Buku Ajar' => 'Textbooks',
        ];
        return $map[$sub] ?? $sub;
    }
    return $sub;
}

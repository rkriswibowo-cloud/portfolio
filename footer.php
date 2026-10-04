<?php
declare(strict_types=1);

if (!isset($settings)) {
    if (function_exists('get_settings')) {
        $settings = get_settings(function_exists('pdo') ? pdo(true) : null);
    } else {
        $settings = [];
    }
}

$siteBrand = $settings['site_brand'] ?? 'Marvel';
$aboutName = $settings['about_name'] ?? 'Rony Kriswibowo';
$aboutRole = $settings['about_role_1'] ?? 'Web Developer & Researcher';
$aboutDesc = $settings['about_description'] ?? 'Portofolio digital, publikasi akademik, riset terapan, dan portal pembelajaran interaktif.';
$companyName = $settings['footer_company'] ?? ($aboutName ?: $siteBrand);
$contactEmail = $settings['contact_email'] ?? '';
$contactPhone = $settings['contact_phone'] ?? '';
$socialInstagram = $settings['social_instagram'] ?? '';
$socialYoutube = $settings['social_youtube'] ?? '';
$socialDribbble = $settings['social_dribbble'] ?? '';

$isLoggedIn = function_exists('user_logged_in') && user_logged_in();
?>
<footer class="site-footer">
    <!-- Aksen garis gradien emas di atas footer -->
    <div class="footer-top-accent"></div>

    <div class="container footer-content py-5">
        <div class="row align-items-start">
            
            <!-- Kolom 1: Profil, Tagline & Status Kerja -->
            <div class="col-lg-5 col-md-12 mb-4 mb-lg-0">
                <div class="footer-brand-wrapper mb-3">
                    <a href="index.php" class="footer-brand-title">
                        <span><?= e($siteBrand) ?></span><span class="footer-brand-dot">.</span>
                    </a>
                    <span class="footer-brand-sub d-block text-warning small font-weight-bold mt-1">
                        <?= e($aboutName) ?> &bull; <?= e($aboutRole) ?>
                    </span>
                </div>
                
                <p class="footer-bio-text text-muted mb-3">
                    <?= e($aboutDesc) ?>
                </p>

                <!-- Status Badge: Open for Collaboration -->
                <div class="footer-badge-status mb-3">
                    <span class="badge-status-dot"></span>
                    <span class="badge-status-label">Open for Research & Collaboration</span>
                </div>

                <!-- Media Sosial Interaktif -->
                <div class="footer-social-icons d-flex flex-wrap align-items-center">
                    <?php if (!empty($contactEmail)): ?>
                        <a href="mailto:<?= e($contactEmail) ?>" class="footer-social-btn" title="Kirim Email" aria-label="Email">
                            <i class="uil uil-envelope"></i>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($contactPhone)): ?>
                        <?php
                            $cleanPhone = preg_replace('/[^0-9]/', '', (string) $contactPhone);
                            if (strpos($cleanPhone, '0') === 0) {
                                $cleanPhone = '62' . substr($cleanPhone, 1);
                            }
                        ?>
                        <a href="https://wa.me/<?= e($cleanPhone) ?>" target="_blank" rel="noopener noreferrer" class="footer-social-btn" title="Hubungi via WhatsApp" aria-label="WhatsApp">
                            <!-- SVG WhatsApp Resmi Tajam -->
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" style="display: inline-block; vertical-align: middle;">
                                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                            </svg>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($socialInstagram) && $socialInstagram !== '#'): ?>
                        <a href="<?= e($socialInstagram) ?>" target="_blank" rel="noopener noreferrer" class="footer-social-btn" title="Instagram" aria-label="Instagram">
                            <i class="uil uil-instagram"></i>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($socialYoutube) && $socialYoutube !== '#'): ?>
                        <a href="<?= e($socialYoutube) ?>" target="_blank" rel="noopener noreferrer" class="footer-social-btn" title="YouTube Channel" aria-label="YouTube">
                            <i class="uil uil-youtube"></i>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($socialDribbble) && $socialDribbble !== '#'): ?>
                        <a href="<?= e($socialDribbble) ?>" target="_blank" rel="noopener noreferrer" class="footer-social-btn" title="Dribbble" aria-label="Dribbble">
                            <i class="uil uil-dribbble"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Kolom 2: Menu Dropdown Collapsible yang Responsif & Tidak Terpotong -->
            <div class="col-lg-3 col-md-6 col-12 mb-4 mb-md-0">
                <h5 class="footer-col-heading">Akses Cepat</h5>
                <p class="small text-muted mb-3">Klik tombol menu di bawah untuk membuka daftar tautan halaman:</p>

                <div class="footer-collapsible-wrapper">
                    <!-- Drawer Collapsible 1: Navigasi Portofolio -->
                    <div class="footer-collapse-card mb-3">
                        <button class="btn btn-footer-collapse" type="button" data-target-drawer="#footerNavCollapse" aria-expanded="false" aria-controls="footerNavCollapse">
                            <span class="d-flex align-items-center">
                                <i class="uil uil-compass text-warning mr-2" style="font-size: 18px;"></i>
                                <span>Navigasi Portofolio</span>
                            </span>
                            <i class="uil uil-angle-down footer-chevron"></i>
                        </button>
                        <div class="footer-collapse-panel" id="footerNavCollapse" role="region" aria-labelledby="footerNavCollapse">
                            <div class="footer-collapse-body">
                                <a class="footer-collapse-link" href="index.php"><i class="uil uil-home mr-2 text-warning"></i> Beranda</a>
                                <a class="footer-collapse-link" href="index.php#about"><i class="uil uil-user mr-2 text-warning"></i> Tentang Saya</a>
                                <a class="footer-collapse-link" href="index.php#project"><i class="uil uil-briefcase-alt mr-2 text-warning"></i> Proyek & Karya</a>
                                <a class="footer-collapse-link" href="index.php#academic"><i class="uil uil-graduation-hat mr-2 text-warning"></i> Publikasi & Riset</a>
                                <a class="footer-collapse-link" href="index.php#tech-stack"><i class="uil uil-layer-group mr-2 text-warning"></i> Tech Stack</a>
                                <div class="footer-collapse-divider"></div>
                                <a class="footer-collapse-link" href="berita.php"><i class="uil uil-newspaper mr-2 text-warning"></i> Berita & Artikel</a>
                            </div>
                        </div>
                    </div>

                    <!-- Drawer Collapsible 2: Portal Belajar & Fitur -->
                    <div class="footer-collapse-card">
                        <button class="btn btn-footer-collapse" type="button" data-target-drawer="#footerCourseCollapse" aria-expanded="false" aria-controls="footerCourseCollapse">
                            <span class="d-flex align-items-center">
                                <i class="uil uil-book-open text-warning mr-2" style="font-size: 18px;"></i>
                                <span>Portal Belajar & Fitur</span>
                            </span>
                            <i class="uil uil-angle-down footer-chevron"></i>
                        </button>
                        <div class="footer-collapse-panel" id="footerCourseCollapse" role="region" aria-labelledby="footerCourseCollapse">
                            <div class="footer-collapse-body">
                                <a class="footer-collapse-link" href="course.php"><i class="uil uil-book-reader mr-2 text-warning"></i> Katalog Course</a>
                                <?php if ($isLoggedIn): ?>
                                    <a class="footer-collapse-link" href="my_courses.php"><i class="uil uil-award mr-2 text-warning"></i> Kelas Saya</a>
                                    <a class="footer-collapse-link" href="my_courses.php#riwayat-section"><i class="uil uil-history mr-2 text-warning"></i> Riwayat & Nilai Tugas</a>
                                    <div class="footer-collapse-divider"></div>
                                    <a class="footer-collapse-link text-danger" href="user_logout.php"><i class="uil uil-sign-out-alt mr-2"></i> Keluar Akun</a>
                                <?php else: ?>
                                    <a class="footer-collapse-link" href="user_login.php"><i class="uil uil-sign-in-alt mr-2 text-warning"></i> Login Mahasiswa</a>
                                    <a class="footer-collapse-link" href="user_register.php"><i class="uil uil-user-plus mr-2 text-warning"></i> Pendaftaran Akun</a>
                                <?php endif; ?>
                                <div class="footer-collapse-divider"></div>
                                <a class="footer-collapse-link" href="cv-generator.php"><i class="uil uil-file-alt mr-2 text-warning"></i> CV Generator</a>
                                <a class="footer-collapse-link" href="index.php#contact"><i class="uil uil-comment-alt-question mr-2 text-warning"></i> Tanya & Diskusi</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kolom 3: Hubungi Saya Langsung -->
            <div class="col-lg-4 col-md-6 col-12">
                <h5 class="footer-col-heading">Hubungi Saya</h5>
                <p class="footer-contact-note small text-muted mb-3">
                    Tertarik berkolaborasi dalam riset akademik, proyek pengembangan web, atau pengajaran? Silakan hubungi saya langsung.
                </p>
                <div class="footer-contact-details mb-3">
                    <?php if (!empty($contactEmail)): ?>
                        <div class="footer-contact-row d-flex align-items-center mb-2">
                            <div class="footer-contact-icon mr-2">
                                <i class="uil uil-envelope text-warning"></i>
                            </div>
                            <a href="mailto:<?= e($contactEmail) ?>" class="footer-contact-link small text-break"><?= e($contactEmail) ?></a>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($contactPhone)): ?>
                        <div class="footer-contact-row d-flex align-items-center mb-2">
                            <div class="footer-contact-icon mr-2">
                                <i class="uil uil-phone text-warning"></i>
                            </div>
                            <span class="footer-contact-link small"><?= e($contactPhone) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <a href="index.php#contact" class="btn btn-sm btn-outline-warning font-weight-bold footer-cta-action px-3 py-2">
                    <i class="uil uil-message mr-1"></i> Hubungi Sekarang
                </a>
            </div>

        </div>
    </div>

    <!-- Bottom Bar: Copyright & Back to Top -->
    <div class="footer-bottom-bar border-top py-3">
        <div class="container d-flex flex-wrap justify-content-between align-items-center">
            <p class="mb-0 small text-muted copyright-text">
                &copy; <?= date('Y') ?> <strong><?= e($companyName) ?></strong>. <?= function_exists('__t') ? e(__t('footer_rights')) : 'Hak cipta dilindungi undang-undang.' ?>
            </p>
            <div class="footer-bottom-actions d-flex align-items-center mt-2 mt-sm-0">
                <span class="footer-tagline-text small text-muted mr-3 d-none d-md-inline">
                    <i class="uil uil-heart text-danger"></i> Designed for Excellence & Knowledge
                </span>
                <button type="button" class="btn btn-sm footer-btn-top" id="btnScrollToTop" title="Kembali ke atas">
                    <i class="uil uil-arrow-up"></i>
                </button>
            </div>
        </div>
    </div>
</footer>

<script>
(function () {
    function setupFooterInteractivity() {
        var buttons = document.querySelectorAll('.btn-footer-collapse');
        buttons.forEach(function (button) {
            if (button.dataset.footerInit) return;
            button.dataset.footerInit = 'true';

            button.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                var targetId = this.getAttribute('data-target-drawer');
                if (!targetId) return;
                var panel = document.querySelector(targetId);
                if (!panel) return;

                var isCurrentlyOpen = panel.classList.contains('is-open');

                // Accordion behavior: close other open drawer in footer
                buttons.forEach(function (otherBtn) {
                    if (otherBtn !== button) {
                        otherBtn.classList.remove('is-active');
                        otherBtn.setAttribute('aria-expanded', 'false');
                        var otherTarget = otherBtn.getAttribute('data-target-drawer');
                        if (otherTarget) {
                            var otherPanel = document.querySelector(otherTarget);
                            if (otherPanel) {
                                otherPanel.classList.remove('is-open');
                                otherPanel.style.maxHeight = '0px';
                            }
                        }
                    }
                });

                if (isCurrentlyOpen) {
                    // Close this panel
                    this.classList.remove('is-active');
                    this.setAttribute('aria-expanded', 'false');
                    panel.style.maxHeight = '0px';
                    panel.classList.remove('is-open');
                } else {
                    // Open this panel
                    this.classList.add('is-active');
                    this.setAttribute('aria-expanded', 'true');
                    panel.classList.add('is-open');
                    var targetHeight = panel.scrollHeight;
                    panel.style.maxHeight = (targetHeight + 20) + 'px';
                }
            });
        });

        // Tombol Back to Top
        var btnTop = document.getElementById('btnScrollToTop');
        if (btnTop && !btnTop.dataset.topInit) {
            btnTop.dataset.topInit = 'true';
            btnTop.addEventListener('click', function (e) {
                e.preventDefault();
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupFooterInteractivity);
    } else {
        setupFooterInteractivity();
    }
})();
</script>

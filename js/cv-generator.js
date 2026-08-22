/**
 * CV Generator Engine
 * Real-time WYSIWYG, Dynamic Repeaters, LocalStorage Auto-save, Multi-Template Rendering & PDF Export
 */

(function () {
    "use strict";

    // --- DEFAULT INITIAL STATE / DEMO DATA ---
    const DEMO_DATA = {
        template: "modern",
        primaryColor: "#4361ee",
        fontFamily: "'Plus Jakarta Sans', sans-serif",
        density: "normal",
        photoShape: "circle",
        personal: {
            fullName: "Marvel Sann",
            jobTitle: "Senior Full-Stack Developer & UI Architect",
            email: "marvel.sann@example.com",
            phone: "+62 812-3456-7890",
            location: "Jakarta, Indonesia",
            website: "https://marvelsann.dev",
            linkedin: "linkedin.com/in/marvelsann",
            github: "github.com/marvelsann",
            photo: "images/undraw/undraw_software_engineer_lvl5.svg"
        },
        summary: "Software Engineer berpengalaman lebih dari 5 tahun dalam membangun aplikasi web skalabel, modern, dan performan tinggi. Ahli dalam ekosistem PHP, Modern JavaScript (React, Vue, Node.js), arsitektur database relasional, serta penerapan best-practice UI/UX yang berorientasi pada kepuasan pengguna dan efisiensi bisnis.",
        experiences: [
            {
                id: "exp_1",
                role: "Lead Full-Stack Engineer",
                company: "Tech Solutions Indonesia",
                location: "Jakarta",
                startDate: "Jan 2023",
                endDate: "Sekarang",
                isCurrent: true,
                description: "• Memimpin tim pengembang beranggotakan 8 engineer dalam membangun platform SaaS e-commerce berskala enterprise.\n• Mengoptimalkan kinerja backend SQL query hingga mempercepat response time aplikasi sebesar 42%.\n• Merancang arsitektur API RESTful dan sistem otentikasi token JWT yang aman dan reliable."
            },
            {
                id: "exp_2",
                role: "Web Application Developer",
                company: "Digital Media Agency",
                location: "Bandung",
                startDate: "Mar 2020",
                endDate: "Des 2022",
                isCurrent: false,
                description: "• Mengembangkan lebih dari 20+ proyek website klien menggunakan PHP, MySQL, and modern JavaScript framework.\n• Menerapkan CI/CD pipeline otomatis dan integrasi gateway pembayaran (Midtrans & Stripe).\n• Berkolaborasi erat dengan tim desainer UI/UX untuk mewujudkan interaksi visual yang responsif."
            }
        ],
        educations: [
            {
                id: "edu_1",
                degree: "S1 Teknik Informatika (Bachelor of Computer Science)",
                school: "Universitas Bina Nusantara",
                location: "Jakarta",
                year: "2016 - 2020",
                gpa: "IPK 3.82 / 4.00 (Cum Laude)"
            }
        ],
        skills: {
            tech: ["PHP 8.x", "JavaScript (ES6+)", "React.js", "Laravel", "Node.js", "MySQL", "REST API", "Tailwind CSS", "Bootstrap", "HTML5/CSS3"],
            soft: ["Problem Solving", "Team Leadership", "Agile / Scrum", "Communication", "Time Management"],
            tools: ["Git & GitHub", "Docker", "VS Code", "Postman", "Figma", "Linux/Ubuntu", "CI/CD Actions"]
        },
        projects: [
            {
                id: "prj_1",
                title: "Learning Management System (LMS) Platform",
                role: "Lead Architect",
                link: "https://lms.rksolusindo.com",
                tech: "PHP, MySQL, Bootstrap 5, REST API",
                description: "Platform kursus online interaktif dengan fitur materi video streaming, kuis otomatis, token enrollment, dan tracking progres belajar peserta."
            },
            {
                id: "prj_2",
                title: "Portfolio & Creative News Hub",
                role: "Full-Stack Creator",
                link: "https://marvelsann.dev",
                tech: "PHP, Modern JavaScript, SCSS, SEO Optimized",
                description: "Website portofolio interaktif dengan sistem manajemen konten (CMS), filter proyek, dark/light mode toggle, dan CV generator instan."
            }
        ],
        certifications: [
            {
                id: "crt_1",
                name: "AWS Certified Solutions Architect – Associate",
                issuer: "Amazon Web Services (AWS)",
                year: "2024",
                link: "https://aws.amazon.com/verification"
            },
            {
                id: "crt_2",
                name: "Certified Full-Stack Web Engineer",
                issuer: "Dicoding Indonesia",
                year: "2023",
                link: ""
            }
        ],
        languages: "Bahasa Indonesia (Native), English (Professional Working Proficiency)",
        awards: "Juara 1 National Tech Hackathon 2024, Best Graduate Award Informatika 2020."
    };

    // --- APPLICATION STATE ---
    let cvData = JSON.parse(JSON.stringify(DEMO_DATA));
    let saveTimeout = null;

    // --- INITIALIZATION ---
    function init() {
        loadSavedData();
        bindFormInputs();
        bindTemplateControls();
        bindRepeaterActions();
        bindTopActions();
        syncFormWithState();
        renderCVPaper();
    }

    // --- LOCAL STORAGE SYNC ---
    function loadSavedData() {
        try {
            const saved = localStorage.getItem("saved_cv_generator_data");
            if (saved) {
                const parsed = JSON.parse(saved);
                if (parsed && typeof parsed === "object" && parsed.personal) {
                    cvData = Object.assign({}, DEMO_DATA, parsed);
                }
            }
        } catch (e) {
            console.error("Error reading localStorage for CV generator:", e);
        }
    }

    function triggerAutoSave() {
        setSaveStatus("Menyimpan...");
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            try {
                localStorage.setItem("saved_cv_generator_data", JSON.stringify(cvData));
                setSaveStatus("Tersimpan otomatis");
            } catch (e) {
                setSaveStatus("Penyimpanan lokal penuh");
            }
        }, 400);
    }

    function setSaveStatus(text) {
        const badge = document.getElementById("saveStatusBadge");
        if (badge) {
            badge.innerHTML = `<i class="uil uil-check-circle"></i> ${escapeHtml(text)}`;
        }
    }

    // --- SYNC STATE TO FORM CONTROLS ---
    function syncFormWithState() {
        // Personal Info
        setValue("inputFullName", cvData.personal.fullName);
        setValue("inputJobTitle", cvData.personal.jobTitle);
        setValue("inputEmail", cvData.personal.email);
        setValue("inputPhone", cvData.personal.phone);
        setValue("inputLocation", cvData.personal.location);
        setValue("inputWebsite", cvData.personal.website);
        setValue("inputLinkedin", cvData.personal.linkedin);
        setValue("inputGithub", cvData.personal.github);
        
        const photoPreview = document.getElementById("photoPreviewImg");
        if (photoPreview) {
            photoPreview.src = cvData.personal.photo || "images/undraw/undraw_software_engineer_lvl5.svg";
        }

        // Summary
        setValue("inputSummary", cvData.summary);

        // Skills
        setValue("inputTechSkills", (cvData.skills.tech || []).join(", "));
        setValue("inputSoftSkills", (cvData.skills.soft || []).join(", "));
        setValue("inputToolSkills", (cvData.skills.tools || []).join(", "));
        renderSkillPills();

        // Languages & Awards
        setValue("inputLanguages", cvData.languages || "");
        setValue("inputAwards", cvData.awards || "");

        // Style controls
        setValue("fontSelector", cvData.fontFamily);
        setValue("densitySelector", cvData.density);
        setValue("photoShapeSelector", cvData.photoShape);
        setValue("customColorPicker", cvData.primaryColor);

        // Active template item
        document.querySelectorAll(".cv-template-item").forEach(item => {
            item.classList.toggle("active", item.getAttribute("data-template") === cvData.template);
        });

        // Active color dot
        document.querySelectorAll(".cv-color-dot").forEach(dot => {
            dot.classList.toggle("active", dot.getAttribute("data-color") === cvData.primaryColor);
        });

        // Repeaters
        renderExperienceRepeater();
        renderEducationRepeater();
        renderProjectRepeater();
        renderCertRepeater();
    }

    function setValue(id, val) {
        const el = document.getElementById(id);
        if (el) el.value = val || "";
    }

    // --- BIND INPUT EVENTS ---
    function bindFormInputs() {
        // Personal
        bindInput("inputFullName", val => { cvData.personal.fullName = val; });
        bindInput("inputJobTitle", val => { cvData.personal.jobTitle = val; });
        bindInput("inputEmail", val => { cvData.personal.email = val; });
        bindInput("inputPhone", val => { cvData.personal.phone = val; });
        bindInput("inputLocation", val => { cvData.personal.location = val; });
        bindInput("inputWebsite", val => { cvData.personal.website = val; });
        bindInput("inputLinkedin", val => { cvData.personal.linkedin = val; });
        bindInput("inputGithub", val => { cvData.personal.github = val; });

        // Summary
        bindInput("inputSummary", val => { cvData.summary = val; });

        // Skills
        bindInput("inputTechSkills", val => {
            cvData.skills.tech = val.split(",").map(s => s.trim()).filter(Boolean);
            renderSkillPills();
        });
        bindInput("inputSoftSkills", val => {
            cvData.skills.soft = val.split(",").map(s => s.trim()).filter(Boolean);
            renderSkillPills();
        });
        bindInput("inputToolSkills", val => {
            cvData.skills.tools = val.split(",").map(s => s.trim()).filter(Boolean);
            renderSkillPills();
        });

        // Languages & Awards
        bindInput("inputLanguages", val => { cvData.languages = val; });
        bindInput("inputAwards", val => { cvData.awards = val; });

        // Photo Upload
        const photoInput = document.getElementById("photoFileInput");
        if (photoInput) {
            photoInput.addEventListener("change", function () {
                const file = this.files && this.files[0];
                if (file) {
                    if (file.size > 3 * 1024 * 1024) {
                        alert("Ukuran foto maksimal 3MB.");
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        cvData.personal.photo = e.target.result;
                        const preview = document.getElementById("photoPreviewImg");
                        if (preview) preview.src = e.target.result;
                        triggerAutoSave();
                        renderCVPaper();
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        const btnRemovePhoto = document.getElementById("btnRemovePhoto");
        if (btnRemovePhoto) {
            btnRemovePhoto.addEventListener("click", function () {
                cvData.personal.photo = "";
                const preview = document.getElementById("photoPreviewImg");
                if (preview) preview.src = "images/undraw/undraw_software_engineer_lvl5.svg";
                triggerAutoSave();
                renderCVPaper();
            });
        }
    }

    function bindInput(id, callback) {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener("input", function () {
                callback(this.value);
                triggerAutoSave();
                renderCVPaper();
            });
        }
    }

    // --- TEMPLATE & STYLING CONTROLS ---
    function bindTemplateControls() {
        // Template Selector Cards
        document.querySelectorAll(".cv-template-item").forEach(item => {
            item.addEventListener("click", function () {
                document.querySelectorAll(".cv-template-item").forEach(i => i.classList.remove("active"));
                this.classList.add("active");
                cvData.template = this.getAttribute("data-template") || "modern";
                triggerAutoSave();
                renderCVPaper();
            });
        });

        // Color Swatches
        document.querySelectorAll(".cv-color-dot").forEach(dot => {
            dot.addEventListener("click", function () {
                document.querySelectorAll(".cv-color-dot").forEach(d => d.classList.remove("active"));
                this.classList.add("active");
                const color = this.getAttribute("data-color");
                cvData.primaryColor = color;
                setValue("customColorPicker", color);
                triggerAutoSave();
                renderCVPaper();
            });
        });

        // Custom Color Picker
        const colorPicker = document.getElementById("customColorPicker");
        if (colorPicker) {
            colorPicker.addEventListener("input", function () {
                cvData.primaryColor = this.value;
                document.querySelectorAll(".cv-color-dot").forEach(d => d.classList.remove("active"));
                triggerAutoSave();
                renderCVPaper();
            });
        }

        // Font Selector
        const fontSelector = document.getElementById("fontSelector");
        if (fontSelector) {
            fontSelector.addEventListener("change", function () {
                cvData.fontFamily = this.value;
                triggerAutoSave();
                renderCVPaper();
            });
        }

        // Density Selector
        const densitySelector = document.getElementById("densitySelector");
        if (densitySelector) {
            densitySelector.addEventListener("change", function () {
                cvData.density = this.value;
                triggerAutoSave();
                renderCVPaper();
            });
        }

        // Photo Shape Selector
        const photoShapeSelector = document.getElementById("photoShapeSelector");
        if (photoShapeSelector) {
            photoShapeSelector.addEventListener("change", function () {
                cvData.photoShape = this.value;
                triggerAutoSave();
                renderCVPaper();
            });
        }
    }

    // --- REPEATERS LOGIC (Experience, Education, Project, Cert) ---
    function bindRepeaterActions() {
        // Add Experience
        const btnAddExp = document.getElementById("btnAddExp");
        if (btnAddExp) {
            btnAddExp.addEventListener("click", function () {
                cvData.experiences.push({
                    id: "exp_" + Date.now(),
                    role: "Posisi Pekerjaan Baru",
                    company: "Nama Perusahaan",
                    location: "Kota",
                    startDate: "2023",
                    endDate: "Sekarang",
                    isCurrent: true,
                    description: "• Tuliskan tanggung jawab dan pencapaian utama di sini."
                });
                renderExperienceRepeater();
                triggerAutoSave();
                renderCVPaper();
            });
        }

        // Add Education
        const btnAddEdu = document.getElementById("btnAddEdu");
        if (btnAddEdu) {
            btnAddEdu.addEventListener("click", function () {
                cvData.educations.push({
                    id: "edu_" + Date.now(),
                    degree: "Gelar / Program Studi",
                    school: "Nama Universitas / Sekolah",
                    location: "Kota",
                    year: "2020 - 2024",
                    gpa: "IPK 3.75"
                });
                renderEducationRepeater();
                triggerAutoSave();
                renderCVPaper();
            });
        }

        // Add Project
        const btnAddProject = document.getElementById("btnAddProject");
        if (btnAddProject) {
            btnAddProject.addEventListener("click", function () {
                cvData.projects.push({
                    id: "prj_" + Date.now(),
                    title: "Judul Proyek Baru",
                    role: "Peran Anda",
                    link: "https://",
                    tech: "Tech Stack",
                    description: "Deskripsi singkat proyek dan dampaknya."
                });
                renderProjectRepeater();
                triggerAutoSave();
                renderCVPaper();
            });
        }

        // Add Certificate
        const btnAddCert = document.getElementById("btnAddCert");
        if (btnAddCert) {
            btnAddCert.addEventListener("click", function () {
                cvData.certifications.push({
                    id: "crt_" + Date.now(),
                    name: "Nama Sertifikasi",
                    issuer: "Lembaga Penerbit",
                    year: "2024",
                    link: ""
                });
                renderCertRepeater();
                triggerAutoSave();
                renderCVPaper();
            });
        }
    }

    // --- REPEATER RENDERERS ---
    function renderExperienceRepeater() {
        const container = document.getElementById("experienceList");
        if (!container) return;
        container.innerHTML = "";

        cvData.experiences.forEach((item, index) => {
            const el = document.createElement("div");
            el.className = "cv-repeater-item";
            el.innerHTML = `
                <div class="cv-repeater-header">
                    <h5 class="cv-repeater-title">#${index + 1} ${escapeHtml(item.role || "Pengalaman")} @ ${escapeHtml(item.company || "")}</h5>
                    <div class="cv-repeater-actions">
                        <button type="button" class="cv-btn-icon-danger" onclick="window.cvApp.removeExperience('${item.id}')" title="Hapus">
                            <i class="uil uil-trash-alt"></i>
                        </button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 col-12 cv-form-group">
                        <label class="cv-form-label">Posisi / Jabatan *</label>
                        <input type="text" class="cv-form-input" value="${escapeHtml(item.role)}" oninput="window.cvApp.updateExperience('${item.id}', 'role', this.value)">
                    </div>
                    <div class="col-md-6 col-12 cv-form-group">
                        <label class="cv-form-label">Perusahaan / Instansi *</label>
                        <input type="text" class="cv-form-input" value="${escapeHtml(item.company)}" oninput="window.cvApp.updateExperience('${item.id}', 'company', this.value)">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 col-12 cv-form-group">
                        <label class="cv-form-label">Lokasi</label>
                        <input type="text" class="cv-form-input" value="${escapeHtml(item.location)}" oninput="window.cvApp.updateExperience('${item.id}', 'location', this.value)">
                    </div>
                    <div class="col-md-4 col-6 cv-form-group">
                        <label class="cv-form-label">Mulai (Bln/Thn)</label>
                        <input type="text" class="cv-form-input" value="${escapeHtml(item.startDate)}" oninput="window.cvApp.updateExperience('${item.id}', 'startDate', this.value)">
                    </div>
                    <div class="col-md-4 col-6 cv-form-group">
                        <label class="cv-form-label">Selesai (Bln/Thn)</label>
                        <input type="text" class="cv-form-input" value="${escapeHtml(item.endDate)}" oninput="window.cvApp.updateExperience('${item.id}', 'endDate', this.value)">
                    </div>
                </div>
                <div class="cv-form-group mb-0">
                    <label class="cv-form-label">Deskripsi Tanggung Jawab & Pencapaian (Gunakan enter/bullet)</label>
                    <textarea class="cv-form-textarea" rows="3" oninput="window.cvApp.updateExperience('${item.id}', 'description', this.value)">${escapeHtml(item.description)}</textarea>
                </div>
            `;
            container.appendChild(el);
        });
    }

    function renderEducationRepeater() {
        const container = document.getElementById("educationList");
        if (!container) return;
        container.innerHTML = "";

        cvData.educations.forEach((item, index) => {
            const el = document.createElement("div");
            el.className = "cv-repeater-item";
            el.innerHTML = `
                <div class="cv-repeater-header">
                    <h5 class="cv-repeater-title">#${index + 1} ${escapeHtml(item.degree || "Pendidikan")}</h5>
                    <div class="cv-repeater-actions">
                        <button type="button" class="cv-btn-icon-danger" onclick="window.cvApp.removeEducation('${item.id}')" title="Hapus">
                            <i class="uil uil-trash-alt"></i>
                        </button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 col-12 cv-form-group">
                        <label class="cv-form-label">Gelar / Jurusan *</label>
                        <input type="text" class="cv-form-input" value="${escapeHtml(item.degree)}" oninput="window.cvApp.updateEducation('${item.id}', 'degree', this.value)">
                    </div>
                    <div class="col-md-6 col-12 cv-form-group">
                        <label class="cv-form-label">Universitas / Sekolah *</label>
                        <input type="text" class="cv-form-input" value="${escapeHtml(item.school)}" oninput="window.cvApp.updateEducation('${item.id}', 'school', this.value)">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 col-12 cv-form-group">
                        <label class="cv-form-label">Lokasi</label>
                        <input type="text" class="cv-form-input" value="${escapeHtml(item.location)}" oninput="window.cvApp.updateEducation('${item.id}', 'location', this.value)">
                    </div>
                    <div class="col-md-4 col-6 cv-form-group">
                        <label class="cv-form-label">Tahun / Periode</label>
                        <input type="text" class="cv-form-input" value="${escapeHtml(item.year)}" oninput="window.cvApp.updateEducation('${item.id}', 'year', this.value)">
                    </div>
                    <div class="col-md-4 col-6 cv-form-group">
                        <label class="cv-form-label">IPK / Predikat</label>
                        <input type="text" class="cv-form-input" value="${escapeHtml(item.gpa)}" oninput="window.cvApp.updateEducation('${item.id}', 'gpa', this.value)">
                    </div>
                </div>
            `;
            container.appendChild(el);
        });
    }

    function renderProjectRepeater() {
        const container = document.getElementById("projectList");
        if (!container) return;
        container.innerHTML = "";

        cvData.projects.forEach((item, index) => {
            const el = document.createElement("div");
            el.className = "cv-repeater-item";
            el.innerHTML = `
                <div class="cv-repeater-header">
                    <h5 class="cv-repeater-title">#${index + 1} ${escapeHtml(item.title || "Proyek")}</h5>
                    <div class="cv-repeater-actions">
                        <button type="button" class="cv-btn-icon-danger" onclick="window.cvApp.removeProject('${item.id}')" title="Hapus">
                            <i class="uil uil-trash-alt"></i>
                        </button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 col-12 cv-form-group">
                        <label class="cv-form-label">Nama Proyek *</label>
                        <input type="text" class="cv-form-input" value="${escapeHtml(item.title)}" oninput="window.cvApp.updateProject('${item.id}', 'title', this.value)">
                    </div>
                    <div class="col-md-6 col-12 cv-form-group">
                        <label class="cv-form-label">Peran / Kategori</label>
                        <input type="text" class="cv-form-input" value="${escapeHtml(item.role)}" oninput="window.cvApp.updateProject('${item.id}', 'role', this.value)">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 col-12 cv-form-group">
                        <label class="cv-form-label">Link Proyek / Demo</label>
                        <input type="text" class="cv-form-input" value="${escapeHtml(item.link)}" oninput="window.cvApp.updateProject('${item.id}', 'link', this.value)">
                    </div>
                    <div class="col-md-6 col-12 cv-form-group">
                        <label class="cv-form-label">Teknologi yang Dipakai</label>
                        <input type="text" class="cv-form-input" value="${escapeHtml(item.tech)}" oninput="window.cvApp.updateProject('${item.id}', 'tech', this.value)">
                    </div>
                </div>
                <div class="cv-form-group mb-0">
                    <label class="cv-form-label">Deskripsi Proyek</label>
                    <textarea class="cv-form-textarea" rows="2" oninput="window.cvApp.updateProject('${item.id}', 'description', this.value)">${escapeHtml(item.description)}</textarea>
                </div>
            `;
            container.appendChild(el);
        });
    }

    function renderCertRepeater() {
        const container = document.getElementById("certList");
        if (!container) return;
        container.innerHTML = "";

        cvData.certifications.forEach((item, index) => {
            const el = document.createElement("div");
            el.className = "cv-repeater-item";
            el.innerHTML = `
                <div class="cv-repeater-header">
                    <h5 class="cv-repeater-title">#${index + 1} ${escapeHtml(item.name || "Sertifikat")}</h5>
                    <div class="cv-repeater-actions">
                        <button type="button" class="cv-btn-icon-danger" onclick="window.cvApp.removeCert('${item.id}')" title="Hapus">
                            <i class="uil uil-trash-alt"></i>
                        </button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 col-12 cv-form-group">
                        <label class="cv-form-label">Nama Sertifikasi *</label>
                        <input type="text" class="cv-form-input" value="${escapeHtml(item.name)}" oninput="window.cvApp.updateCert('${item.id}', 'name', this.value)">
                    </div>
                    <div class="col-md-6 col-12 cv-form-group">
                        <label class="cv-form-label">Lembaga Penerbit *</label>
                        <input type="text" class="cv-form-input" value="${escapeHtml(item.issuer)}" oninput="window.cvApp.updateCert('${item.id}', 'issuer', this.value)">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 col-12 cv-form-group">
                        <label class="cv-form-label">Tahun</label>
                        <input type="text" class="cv-form-input" value="${escapeHtml(item.year)}" oninput="window.cvApp.updateCert('${item.id}', 'year', this.value)">
                    </div>
                    <div class="col-md-8 col-12 cv-form-group">
                        <label class="cv-form-label">Link Kredensial (Opsional)</label>
                        <input type="text" class="cv-form-input" value="${escapeHtml(item.link)}" oninput="window.cvApp.updateCert('${item.id}', 'link', this.value)">
                    </div>
                </div>
            `;
            container.appendChild(el);
        });
    }

    function renderSkillPills() {
        const renderTags = (arr, containerId) => {
            const container = document.getElementById(containerId);
            if (!container) return;
            container.innerHTML = (arr || []).map(tag => `
                <span class="cv-skill-tag">
                    ${escapeHtml(tag)}
                </span>
            `).join("");
        };

        renderTags(cvData.skills.tech, "techSkillsPills");
        renderTags(cvData.skills.soft, "softSkillsPills");
        renderTags(cvData.skills.tools, "toolSkillsPills");
    }

    // --- TOP ACTION BUTTONS ---
    function bindTopActions() {
        // Cloud Save (Logged-in Member)
        const btnSaveCloud = document.getElementById("btnSaveCloud");
        if (btnSaveCloud) {
            btnSaveCloud.addEventListener("click", function () {
                const originalHtml = this.innerHTML;
                this.innerHTML = `<span class="spinner-border spinner-border-sm" role="status"></span> Menyimpan...`;
                this.disabled = true;

                $.ajax({
                    url: "cv-generator.php",
                    type: "POST",
                    dataType: "json",
                    data: {
                        ajax_action: "save_cloud_cv",
                        cv_data: JSON.stringify(cvData)
                    },
                    success: function (res) {
                        btnSaveCloud.innerHTML = originalHtml;
                        btnSaveCloud.disabled = false;
                        if (res && res.success) {
                            alert("✨ " + res.message);
                            setSaveStatus("Tersimpan di Cloud & Lokal");
                        } else {
                            alert("Gagal: " + (res.message || "Terjadi kesalahan."));
                        }
                    },
                    error: function () {
                        btnSaveCloud.innerHTML = originalHtml;
                        btnSaveCloud.disabled = false;
                        alert("Gagal menghubungi server untuk menyimpan data.");
                    }
                });
            });
        }

        // Cloud Load (Logged-in Member)
        const btnLoadCloud = document.getElementById("btnLoadCloud");
        if (btnLoadCloud) {
            btnLoadCloud.addEventListener("click", function () {
                if (!confirm("Buka data CV yang tersimpan di akun cloud Anda? Data yang belum tersimpan akan tertimpa.")) {
                    return;
                }

                const originalHtml = this.innerHTML;
                this.innerHTML = `<span class="spinner-border spinner-border-sm" role="status"></span> Memuat...`;
                this.disabled = true;

                $.ajax({
                    url: "cv-generator.php",
                    type: "POST",
                    dataType: "json",
                    data: {
                        ajax_action: "load_cloud_cv"
                    },
                    success: function (res) {
                        btnLoadCloud.innerHTML = originalHtml;
                        btnLoadCloud.disabled = false;
                        if (res && res.success && res.cv_data) {
                            cvData = Object.assign({}, DEMO_DATA, res.cv_data);
                            syncFormWithState();
                            triggerAutoSave();
                            renderCVPaper();
                            alert("✅ Data CV berhasil dimuat dari akun cloud Anda!");
                        } else {
                            alert("Info: " + (res.message || "Tidak ada data."));
                        }
                    },
                    error: function () {
                        btnLoadCloud.innerHTML = originalHtml;
                        btnLoadCloud.disabled = false;
                        alert("Gagal menghubungi server untuk memuat data.");
                    }
                });
            });
        }

        // Auto-fill from User Profile (Logged-in Member)
        const autofillUserHandler = function () {
            if (window.cvUserData && window.cvUserData.isLoggedIn) {
                if (window.cvUserData.name) {
                    cvData.personal.fullName = window.cvUserData.name;
                    setValue("inputFullName", window.cvUserData.name);
                }
                if (window.cvUserData.email) {
                    cvData.personal.email = window.cvUserData.email;
                    setValue("inputEmail", window.cvUserData.email);
                }
                triggerAutoSave();
                renderCVPaper();
                alert(`Data profil berhasil diisi otomatis:\nNama: ${window.cvUserData.name}\nEmail: ${window.cvUserData.email}`);
            }
        };

        const btnSectionAutofill = document.getElementById("btnSectionAutofill");
        if (btnSectionAutofill) {
            btnSectionAutofill.addEventListener("click", autofillUserHandler);
        }

        const btnBannerAutofill = document.getElementById("btnBannerAutofill");
        if (btnBannerAutofill) {
            btnBannerAutofill.addEventListener("click", autofillUserHandler);
        }

        // Load Demo
        const btnLoadDemo = document.getElementById("btnLoadDemo");
        if (btnLoadDemo) {
            btnLoadDemo.addEventListener("click", function () {
                if (confirm("Muat contoh data lengkap? Data saat ini akan diganti dengan template demo.")) {
                    cvData = JSON.parse(JSON.stringify(DEMO_DATA));
                    syncFormWithState();
                    triggerAutoSave();
                    renderCVPaper();
                }
            });
        }

        // Reset Form
        const btnResetForm = document.getElementById("btnResetForm");
        if (btnResetForm) {
            btnResetForm.addEventListener("click", function () {
                if (confirm("Apakah Anda yakin ingin mengosongkan seluruh formulir CV?")) {
                    cvData = {
                        template: "modern",
                        primaryColor: "#4361ee",
                        fontFamily: "'Plus Jakarta Sans', sans-serif",
                        density: "normal",
                        photoShape: "circle",
                        personal: { fullName: "", jobTitle: "", email: "", phone: "", location: "", website: "", linkedin: "", github: "", photo: "" },
                        summary: "",
                        experiences: [],
                        educations: [],
                        skills: { tech: [], soft: [], tools: [] },
                        projects: [],
                        certifications: [],
                        languages: "",
                        awards: ""
                    };
                    syncFormWithState();
                    triggerAutoSave();
                    renderCVPaper();
                }
            });
        }

        // Export JSON
        const btnExportJson = document.getElementById("btnExportJson");
        if (btnExportJson) {
            btnExportJson.addEventListener("click", function () {
                const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(cvData, null, 2));
                const downloadAnchor = document.createElement("a");
                const safeName = (cvData.personal.fullName || "my_cv").replace(/[^a-z0-9]/gi, "_").toLowerCase();
                downloadAnchor.setAttribute("href", dataStr);
                downloadAnchor.setAttribute("download", `CV_Data_${safeName}.json`);
                document.body.appendChild(downloadAnchor);
                downloadAnchor.click();
                downloadAnchor.remove();
            });
        }

        // Import JSON
        const importJsonInput = document.getElementById("importJsonInput");
        if (importJsonInput) {
            importJsonInput.addEventListener("change", function () {
                const file = this.files && this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        try {
                            const parsed = JSON.parse(e.target.result);
                            if (parsed && typeof parsed === "object") {
                                cvData = Object.assign({}, DEMO_DATA, parsed);
                                syncFormWithState();
                                triggerAutoSave();
                                renderCVPaper();
                                alert("Data CV berhasil diimpor!");
                            }
                        } catch (err) {
                            alert("File JSON tidak valid atau rusak.");
                        }
                    };
                    reader.readAsText(file);
                }
            });
        }

        // Print CV
        const btnPrintCv = document.getElementById("btnPrintCv");
        if (btnPrintCv) {
            btnPrintCv.addEventListener("click", function () {
                window.print();
            });
        }

        // Download PDF
        const btnDownloadPdf = document.getElementById("btnDownloadPdf");
        if (btnDownloadPdf) {
            btnDownloadPdf.addEventListener("click", function () {
                generatePdfDownload();
            });
        }
    }

    // --- PDF GENERATION ENGINE ---
    function generatePdfDownload() {
        const btn = document.getElementById("btnDownloadPdf");
        const element = document.getElementById("cvPaper");
        if (!element) return;

        const originalText = btn.innerHTML;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status"></span> Membuat PDF...`;
        btn.disabled = true;

        // Reset any zoom transform temporarily during PDF rendering for perfect crisp geometry
        const originalClass = element.className;
        element.className = `cv-paper zoom-100 density-${cvData.density}`;

        const safeName = (cvData.personal.fullName || "Resume").replace(/[^a-z0-9]/gi, "_");
        const opt = {
            margin: [6, 6, 6, 6],
            filename: `CV_${safeName}.pdf`,
            image: { type: "jpeg", quality: 0.98 },
            html2canvas: { 
                scale: 2, 
                useCORS: true, 
                letterRendering: true,
                scrollY: 0
            },
            jsPDF: { unit: "mm", format: "a4", orientation: "portrait" },
            pagebreak: { 
                mode: ['avoid-all', 'css', 'legacy'], 
                avoid: [
                    '.cv-entry', 
                    '.cv-paper-section-title', 
                    '.cv-section-title', 
                    '.cv-sidebar-section', 
                    '.cv-sidebar-heading', 
                    '.cv-header', 
                    '.cv-header-grid', 
                    '.cv-contact-item', 
                    '.cv-paper-skills',
                    '.cv-entry-bullets',
                    '.cv-entry-bullets li',
                    'h1', 'h2', 'h3', 'h4'
                ] 
            }
        };

        if (window.html2pdf) {
            window.html2pdf().set(opt).from(element).save().then(() => {
                element.className = originalClass;
                btn.innerHTML = originalText;
                btn.disabled = false;
            }).catch(err => {
                console.error("PDF generation error:", err);
                element.className = originalClass;
                btn.innerHTML = originalText;
                btn.disabled = false;
                alert("Gagal membuat PDF otomatis. Silakan gunakan tombol Print -> Save as PDF sebagai alternatif.");
            });
        } else {
            element.className = originalClass;
            btn.innerHTML = originalText;
            btn.disabled = false;
            window.print();
        }
    }

    // --- WYSIWYG LIVE PREVIEW RENDERER ---
    function renderCVPaper() {
        const paper = document.getElementById("cvPaper");
        if (!paper) return;

        // Apply CSS Variables to paper
        paper.style.setProperty("--cv-primary", cvData.primaryColor);
        paper.style.setProperty("--cv-font", cvData.fontFamily);

        // Density class
        paper.className = paper.className.replace(/density-\w+/g, "").trim() + " density-" + cvData.density;

        // Select template renderer
        switch (cvData.template) {
            case "executive":
                paper.innerHTML = renderExecutiveTemplate();
                break;
            case "creative":
                paper.innerHTML = renderCreativeTemplate();
                break;
            case "minimalist":
                paper.innerHTML = renderMinimalistTemplate();
                break;
            case "modern":
            default:
                paper.innerHTML = renderModernTemplate();
                break;
        }
    }

    // Helper: Render Profile Photo
    function getPhotoHtml(extraClass = "") {
        if (cvData.photoShape === "hide" || !cvData.personal.photo) return "";
        let shapeClass = "border-radius:50%;";
        if (cvData.photoShape === "rounded") shapeClass = "border-radius:12px;";
        if (cvData.photoShape === "square") shapeClass = "border-radius:0;";

        return `<img src="${escapeHtml(cvData.personal.photo)}" class="cv-photo ${extraClass}" style="${shapeClass}" alt="Photo">`;
    }

    // Helper: Contact Badges
    function getContactIcons() {
        const p = cvData.personal;
        const items = [];
        if (p.email) items.push(`<div class="cv-contact-item"><i class="uil uil-envelope"></i> <span>${escapeHtml(p.email)}</span></div>`);
        if (p.phone) items.push(`<div class="cv-contact-item"><i class="uil uil-phone"></i> <span>${escapeHtml(p.phone)}</span></div>`);
        if (p.location) items.push(`<div class="cv-contact-item"><i class="uil uil-map-marker"></i> <span>${escapeHtml(p.location)}</span></div>`);
        if (p.website) items.push(`<div class="cv-contact-item"><i class="uil uil-globe"></i> <span>${escapeHtml(p.website.replace(/^https?:\/\//, ''))}</span></div>`);
        if (p.linkedin) items.push(`<div class="cv-contact-item"><i class="uil uil-linkedin"></i> <span>${escapeHtml(p.linkedin)}</span></div>`);
        if (p.github) items.push(`<div class="cv-contact-item"><i class="uil uil-github"></i> <span>${escapeHtml(p.github)}</span></div>`);
        return items.join("");
    }

    // --- TEMPLATE 1: MODERN PRO (2 Columns) ---
    function renderModernTemplate() {
        const p = cvData.personal;
        const techSkills = (cvData.skills.tech || []).map(s => `<span class="cv-paper-skill-badge">${escapeHtml(s)}</span>`).join("");
        const softSkills = (cvData.skills.soft || []).map(s => `<span class="cv-paper-skill-badge">${escapeHtml(s)}</span>`).join("");
        const tools = (cvData.skills.tools || []).map(s => `<span class="cv-paper-skill-badge">${escapeHtml(s)}</span>`).join("");

        return `
            <div class="cv-tpl-modern">
                <!-- SIDEBAR -->
                <div class="cv-sidebar">
                    ${getPhotoHtml()}
                    
                    <div class="cv-sidebar-section">
                        <div class="cv-sidebar-heading">Kontak</div>
                        ${getContactIcons()}
                    </div>

                    ${(cvData.skills.tech && cvData.skills.tech.length) ? `
                        <div class="cv-sidebar-section">
                            <div class="cv-sidebar-heading">Keahlian Teknis</div>
                            <div class="cv-paper-skills">${techSkills}</div>
                        </div>
                    ` : ''}

                    ${(cvData.skills.tools && cvData.skills.tools.length) ? `
                        <div class="cv-sidebar-section">
                            <div class="cv-sidebar-heading">Tools & Software</div>
                            <div class="cv-paper-skills">${tools}</div>
                        </div>
                    ` : ''}

                    ${(cvData.skills.soft && cvData.skills.soft.length) ? `
                        <div class="cv-sidebar-section">
                            <div class="cv-sidebar-heading">Soft Skills</div>
                            <div class="cv-paper-skills">${softSkills}</div>
                        </div>
                    ` : ''}

                    ${cvData.languages ? `
                        <div class="cv-sidebar-section">
                            <div class="cv-sidebar-heading">Bahasa</div>
                            <div style="font-size:11.5px; color:#475569;">${escapeHtml(cvData.languages)}</div>
                        </div>
                    ` : ''}

                    ${(cvData.educations && cvData.educations.length) ? `
                        <div class="cv-sidebar-section">
                            <div class="cv-sidebar-heading">Pendidikan</div>
                            ${cvData.educations.map(edu => `
                                <div style="margin-bottom: 10px;">
                                    <div style="font-size: 12px; font-weight:700; color:#0f172a;">${escapeHtml(edu.degree)}</div>
                                    <div style="font-size: 11px; color:#475569;">${escapeHtml(edu.school)}</div>
                                    <div style="font-size: 10.5px; color:#64748b;">${escapeHtml(edu.year)} ${edu.gpa ? `• ${escapeHtml(edu.gpa)}` : ''}</div>
                                </div>
                            `).join('')}
                        </div>
                    ` : ''}
                </div>

                <!-- MAIN COLUMN -->
                <div class="cv-main">
                    <div style="margin-bottom: 16px;">
                        <h1 class="cv-name">${escapeHtml(p.fullName || 'Nama Lengkap')}</h1>
                        <div class="cv-title">${escapeHtml(p.jobTitle || 'Profesi / Keahlian')}</div>
                    </div>

                    ${cvData.summary ? `
                        <div style="margin-bottom: 18px;">
                            <div class="cv-paper-section-title"><i class="uil uil-user"></i> Ringkasan Profil</div>
                            <div style="font-size: 12px; color: #334155; line-height: 1.5;">${escapeHtml(cvData.summary)}</div>
                        </div>
                    ` : ''}

                    ${(cvData.experiences && cvData.experiences.length) ? `
                        <div style="margin-bottom: 18px;">
                            <div class="cv-paper-section-title"><i class="uil uil-briefcase"></i> Pengalaman Kerja</div>
                            ${cvData.experiences.map(exp => `
                                <div class="cv-entry">
                                    <div class="cv-entry-header">
                                        <span class="cv-entry-title">${escapeHtml(exp.role)}</span>
                                        <span class="cv-entry-date">${escapeHtml(exp.startDate)} - ${escapeHtml(exp.endDate)}</span>
                                    </div>
                                    <div class="cv-entry-subtitle">${escapeHtml(exp.company)} ${exp.location ? `• ${escapeHtml(exp.location)}` : ''}</div>
                                    <div class="cv-entry-desc">${renderBulletPoints(exp.description)}</div>
                                </div>
                            `).join('')}
                        </div>
                    ` : ''}

                    ${(cvData.projects && cvData.projects.length) ? `
                        <div style="margin-bottom: 18px;">
                            <div class="cv-paper-section-title"><i class="uil uil-folder"></i> Proyek & Portofolio</div>
                            ${cvData.projects.map(prj => `
                                <div class="cv-entry">
                                    <div class="cv-entry-header">
                                        <span class="cv-entry-title">${escapeHtml(prj.title)}</span>
                                        ${prj.role ? `<span class="cv-entry-date">${escapeHtml(prj.role)}</span>` : ''}
                                    </div>
                                    ${prj.tech ? `<div style="font-size:11px; color:var(--cv-primary); font-weight:600;">Stack: ${escapeHtml(prj.tech)}</div>` : ''}
                                    <div class="cv-entry-desc">${escapeHtml(prj.description)}</div>
                                </div>
                            `).join('')}
                        </div>
                    ` : ''}

                    ${(cvData.certifications && cvData.certifications.length) ? `
                        <div style="margin-bottom: 18px;">
                            <div class="cv-paper-section-title"><i class="uil uil-award"></i> Sertifikasi & Lisensi</div>
                            ${cvData.certifications.map(crt => `
                                <div class="cv-entry">
                                    <div class="cv-entry-header">
                                        <span class="cv-entry-title">${escapeHtml(crt.name)}</span>
                                        <span class="cv-entry-date">${escapeHtml(crt.year)}</span>
                                    </div>
                                    <div class="cv-entry-subtitle">${escapeHtml(crt.issuer)}</div>
                                </div>
                            `).join('')}
                        </div>
                    ` : ''}

                    ${cvData.awards ? `
                        <div class="cv-entry">
                            <div class="cv-paper-section-title"><i class="uil uil-star"></i> Penghargaan & Lainnya</div>
                            <div style="font-size:12px; color:#334155; line-height:1.45;">${escapeHtml(cvData.awards)}</div>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    // --- TEMPLATE 2: EXECUTIVE ATS (Single Column) ---
    function renderExecutiveTemplate() {
        const p = cvData.personal;
        const contacts = [];
        if (p.email) contacts.push(`<span><i class="uil uil-envelope"></i> ${escapeHtml(p.email)}</span>`);
        if (p.phone) contacts.push(`<span><i class="uil uil-phone"></i> ${escapeHtml(p.phone)}</span>`);
        if (p.location) contacts.push(`<span><i class="uil uil-map-marker"></i> ${escapeHtml(p.location)}</span>`);
        if (p.linkedin) contacts.push(`<span><i class="uil uil-linkedin"></i> ${escapeHtml(p.linkedin)}</span>`);
        if (p.github) contacts.push(`<span><i class="uil uil-github"></i> ${escapeHtml(p.github)}</span>`);

        const allSkills = [
            ...(cvData.skills.tech || []),
            ...(cvData.skills.tools || []),
            ...(cvData.skills.soft || [])
        ].join(" • ");

        return `
            <div class="cv-tpl-executive">
                <div class="cv-header">
                    <h1 class="cv-name">${escapeHtml(p.fullName || 'Nama Lengkap')}</h1>
                    <div class="cv-title">${escapeHtml(p.jobTitle || 'Profesi / Keahlian')}</div>
                    <div class="cv-contact-bar">${contacts.join("")}</div>
                </div>

                ${cvData.summary ? `
                    <div class="cv-entry">
                        <div class="cv-section-title">Ringkasan Profesional</div>
                        <div style="font-size: 12px; color: #334155; line-height: 1.5;">${escapeHtml(cvData.summary)}</div>
                    </div>
                ` : ''}

                ${(cvData.experiences && cvData.experiences.length) ? `
                    <div>
                        <div class="cv-section-title">Pengalaman Kerja</div>
                        ${cvData.experiences.map(exp => `
                            <div class="cv-entry">
                                <div class="cv-entry-header">
                                    <span class="cv-entry-title">${escapeHtml(exp.role)} — <span style="font-weight:600; color:#475569;">${escapeHtml(exp.company)}</span></span>
                                    <span class="cv-entry-date">${escapeHtml(exp.startDate)} - ${escapeHtml(exp.endDate)} | ${escapeHtml(exp.location || '')}</span>
                                </div>
                                <div class="cv-entry-desc">${renderBulletPoints(exp.description)}</div>
                            </div>
                        `).join('')}
                    </div>
                ` : ''}

                ${(cvData.educations && cvData.educations.length) ? `
                    <div>
                        <div class="cv-section-title">Pendidikan</div>
                        ${cvData.educations.map(edu => `
                            <div class="cv-entry">
                                <div class="cv-entry-header">
                                    <span class="cv-entry-title">${escapeHtml(edu.degree)} — <span style="font-weight:600; color:#475569;">${escapeHtml(edu.school)}</span></span>
                                    <span class="cv-entry-date">${escapeHtml(edu.year)} ${edu.gpa ? `| ${escapeHtml(edu.gpa)}` : ''}</span>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                ` : ''}

                ${(cvData.projects && cvData.projects.length) ? `
                    <div>
                        <div class="cv-section-title">Proyek & Portofolio</div>
                        ${cvData.projects.map(prj => `
                            <div class="cv-entry">
                                <div class="cv-entry-header">
                                    <span class="cv-entry-title">${escapeHtml(prj.title)} ${prj.role ? `(${escapeHtml(prj.role)})` : ''}</span>
                                    ${prj.link ? `<span class="cv-entry-date">${escapeHtml(prj.link)}</span>` : ''}
                                </div>
                                ${prj.tech ? `<div style="font-size:11px; color:#475569; font-weight:600;">Stack: ${escapeHtml(prj.tech)}</div>` : ''}
                                <div class="cv-entry-desc">${escapeHtml(prj.description)}</div>
                            </div>
                        `).join('')}
                    </div>
                ` : ''}

                ${allSkills ? `
                    <div class="cv-entry">
                        <div class="cv-section-title">Keahlian & Kemampuan</div>
                        <div style="font-size: 12px; color: #334155; line-height: 1.5;">${escapeHtml(allSkills)}</div>
                    </div>
                ` : ''}

                ${(cvData.certifications && cvData.certifications.length) ? `
                    <div class="cv-entry">
                        <div class="cv-section-title">Sertifikasi & Lisensi</div>
                        ${cvData.certifications.map(crt => `
                            <div style="font-size:12px; margin-bottom:4px;">
                                <strong>${escapeHtml(crt.name)}</strong> — ${escapeHtml(crt.issuer)} (${escapeHtml(crt.year)})
                            </div>
                        `).join('')}
                    </div>
                ` : ''}

                ${cvData.languages ? `
                    <div class="cv-entry">
                        <div class="cv-section-title">Bahasa</div>
                        <div style="font-size:12px; color:#334155;">${escapeHtml(cvData.languages)}</div>
                    </div>
                ` : ''}

                ${cvData.awards ? `
                    <div class="cv-entry">
                        <div class="cv-section-title">Penghargaan & Lainnya</div>
                        <div style="font-size:12px; color:#334155;">${escapeHtml(cvData.awards)}</div>
                    </div>
                ` : ''}
            </div>
        `;
    }

    // --- TEMPLATE 3: CREATIVE STUDIO (Banner & Accent) ---
    function renderCreativeTemplate() {
        const p = cvData.personal;
        const contacts = [];
        if (p.email) contacts.push(`<span><i class="uil uil-envelope"></i> ${escapeHtml(p.email)}</span>`);
        if (p.phone) contacts.push(`<span><i class="uil uil-phone"></i> ${escapeHtml(p.phone)}</span>`);
        if (p.location) contacts.push(`<span><i class="uil uil-map-marker"></i> ${escapeHtml(p.location)}</span>`);
        if (p.website) contacts.push(`<span><i class="uil uil-globe"></i> ${escapeHtml(p.website.replace(/^https?:\/\//, ''))}</span>`);

        return `
            <div class="cv-tpl-creative">
                <div class="cv-banner">
                    ${getPhotoHtml()}
                    <div>
                        <h1 class="cv-name">${escapeHtml(p.fullName || 'Nama Lengkap')}</h1>
                        <div class="cv-title">${escapeHtml(p.jobTitle || 'Profesi / Keahlian')}</div>
                        <div class="cv-contact-row">${contacts.join("")}</div>
                    </div>
                </div>

                <div class="cv-body">
                    ${cvData.summary ? `
                        <div class="cv-entry" style="margin-bottom: 16px;">
                            <div class="cv-paper-section-title"><i class="uil uil-user"></i> Tentang Saya</div>
                            <div style="font-size: 12px; color: #334155; line-height: 1.5;">${escapeHtml(cvData.summary)}</div>
                        </div>
                    ` : ''}

                    ${(cvData.experiences && cvData.experiences.length) ? `
                        <div style="margin-bottom: 16px;">
                            <div class="cv-paper-section-title"><i class="uil uil-briefcase"></i> Pengalaman Profesional</div>
                            ${cvData.experiences.map(exp => `
                                <div class="cv-entry">
                                    <div class="cv-entry-header">
                                        <span class="cv-entry-title">${escapeHtml(exp.role)}</span>
                                        <span class="cv-entry-date">${escapeHtml(exp.startDate)} - ${escapeHtml(exp.endDate)}</span>
                                    </div>
                                    <div class="cv-entry-subtitle">${escapeHtml(exp.company)} • ${escapeHtml(exp.location || '')}</div>
                                    <div class="cv-entry-desc">${renderBulletPoints(exp.description)}</div>
                                </div>
                            `).join('')}
                        </div>
                    ` : ''}

                    <div class="row">
                        <div class="col-6">
                            ${(cvData.educations && cvData.educations.length) ? `
                                <div class="cv-entry" style="margin-bottom: 16px;">
                                    <div class="cv-paper-section-title"><i class="uil uil-graduation-cap"></i> Pendidikan</div>
                                    ${cvData.educations.map(edu => `
                                        <div style="margin-bottom:8px;">
                                            <div style="font-size:12px; font-weight:700;">${escapeHtml(edu.degree)}</div>
                                            <div style="font-size:11px; color:#475569;">${escapeHtml(edu.school)}</div>
                                            <div style="font-size:10.5px; color:#64748b;">${escapeHtml(edu.year)}</div>
                                        </div>
                                    `).join('')}
                                </div>
                            ` : ''}
                        </div>
                        <div class="col-6">
                            ${(cvData.skills.tech && cvData.skills.tech.length) ? `
                                <div class="cv-entry" style="margin-bottom: 16px;">
                                    <div class="cv-paper-section-title"><i class="uil uil-wrench"></i> Keahlian Utama</div>
                                    <div class="cv-paper-skills">
                                        ${cvData.skills.tech.map(s => `<span class="cv-paper-skill-badge">${escapeHtml(s)}</span>`).join('')}
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                    </div>

                    ${(cvData.projects && cvData.projects.length) ? `
                        <div style="margin-bottom: 16px;">
                            <div class="cv-paper-section-title"><i class="uil uil-folder"></i> Proyek Pilihan</div>
                            ${cvData.projects.map(prj => `
                                <div class="cv-entry">
                                    <div class="cv-entry-header">
                                        <span class="cv-entry-title">${escapeHtml(prj.title)}</span>
                                        ${prj.role ? `<span class="cv-entry-date">${escapeHtml(prj.role)}</span>` : ''}
                                    </div>
                                    <div class="cv-entry-desc">${escapeHtml(prj.description)}</div>
                                </div>
                            `).join('')}
                        </div>
                    ` : ''}

                    ${cvData.awards ? `
                        <div class="cv-entry">
                            <div class="cv-paper-section-title"><i class="uil uil-star"></i> Penghargaan & Lainnya</div>
                            <div style="font-size:12px; color:#334155;">${escapeHtml(cvData.awards)}</div>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    // --- TEMPLATE 4: MINIMALIST SWISS (Modern Grid) ---
    function renderMinimalistTemplate() {
        const p = cvData.personal;
        return `
            <div class="cv-tpl-minimalist">
                <div class="cv-header-grid">
                    <div>
                        <h1 class="cv-name">${escapeHtml(p.fullName || 'Nama Lengkap')}</h1>
                        <div class="cv-title">${escapeHtml(p.jobTitle || 'Profesi / Keahlian')}</div>
                    </div>
                    <div class="cv-contact-column">
                        ${p.email ? `<div>${escapeHtml(p.email)}</div>` : ''}
                        ${p.phone ? `<div>${escapeHtml(p.phone)}</div>` : ''}
                        ${p.location ? `<div>${escapeHtml(p.location)}</div>` : ''}
                        ${p.website ? `<div>${escapeHtml(p.website.replace(/^https?:\/\//, ''))}</div>` : ''}
                    </div>
                </div>

                ${cvData.summary ? `
                    <div class="cv-entry" style="margin-bottom: 18px;">
                        <div style="font-size: 12px; color: #1e293b; line-height: 1.5; font-weight:500;">${escapeHtml(cvData.summary)}</div>
                    </div>
                ` : ''}

                ${(cvData.experiences && cvData.experiences.length) ? `
                    <div style="margin-bottom: 18px;">
                        <div class="cv-paper-section-title">Pengalaman Kerja</div>
                        ${cvData.experiences.map(exp => `
                            <div class="cv-entry">
                                <div class="cv-entry-header">
                                    <span class="cv-entry-title">${escapeHtml(exp.role)}, ${escapeHtml(exp.company)}</span>
                                    <span class="cv-entry-date">${escapeHtml(exp.startDate)} — ${escapeHtml(exp.endDate)}</span>
                                </div>
                                <div class="cv-entry-desc">${renderBulletPoints(exp.description)}</div>
                            </div>
                        `).join('')}
                    </div>
                ` : ''}

                ${(cvData.educations && cvData.educations.length) ? `
                    <div style="margin-bottom: 18px;">
                        <div class="cv-paper-section-title">Pendidikan</div>
                        ${cvData.educations.map(edu => `
                            <div class="cv-entry">
                                <div class="cv-entry-header">
                                    <span class="cv-entry-title">${escapeHtml(edu.degree)}, ${escapeHtml(edu.school)}</span>
                                    <span class="cv-entry-date">${escapeHtml(edu.year)}</span>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                ` : ''}

                ${(cvData.skills.tech && cvData.skills.tech.length) ? `
                    <div class="cv-entry" style="margin-bottom: 18px;">
                        <div class="cv-paper-section-title">Keahlian</div>
                        <div style="font-size:12px; color:#334155;">${escapeHtml(cvData.skills.tech.join(" • "))}</div>
                    </div>
                ` : ''}

                ${(cvData.projects && cvData.projects.length) ? `
                    <div>
                        <div class="cv-paper-section-title">Proyek</div>
                        ${cvData.projects.map(prj => `
                            <div class="cv-entry">
                                <div class="cv-entry-header">
                                    <span class="cv-entry-title">${escapeHtml(prj.title)}</span>
                                    <span class="cv-entry-date">${escapeHtml(prj.tech || '')}</span>
                                </div>
                                <div class="cv-entry-desc">${escapeHtml(prj.description)}</div>
                            </div>
                        `).join('')}
                    </div>
                ` : ''}

                ${cvData.awards ? `
                    <div class="cv-entry">
                        <div class="cv-paper-section-title">Penghargaan & Lainnya</div>
                        <div style="font-size:12px; color:#334155;">${escapeHtml(cvData.awards)}</div>
                    </div>
                ` : ''}
            </div>
        `;
    }

    // --- HELPER UTILITIES ---
    function renderBulletPoints(text) {
        if (!text) return "";
        const lines = text.split("\n").map(l => l.trim()).filter(Boolean);
        if (lines.length > 1 || lines[0].startsWith("•") || lines[0].startsWith("-")) {
            return `<ul class="cv-entry-bullets">${lines.map(line => `<li>${escapeHtml(line.replace(/^[•\-\*]\s*/, ''))}</li>`).join('')}</ul>`;
        }
        return `<p style="margin:0;">${escapeHtml(text)}</p>`;
    }

    function escapeHtml(str) {
        if (!str) return "";
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // --- GLOBAL SCOPE ACTIONS FOR HTML ONCLICK / INLINE HANDLERS ---
    window.cvApp = {
        updateExperience: function (id, field, value) {
            const item = cvData.experiences.find(x => x.id === id);
            if (item) {
                item[field] = value;
                triggerAutoSave();
                renderCVPaper();
            }
        },
        removeExperience: function (id) {
            cvData.experiences = cvData.experiences.filter(x => x.id !== id);
            renderExperienceRepeater();
            triggerAutoSave();
            renderCVPaper();
        },
        updateEducation: function (id, field, value) {
            const item = cvData.educations.find(x => x.id === id);
            if (item) {
                item[field] = value;
                triggerAutoSave();
                renderCVPaper();
            }
        },
        removeEducation: function (id) {
            cvData.educations = cvData.educations.filter(x => x.id !== id);
            renderEducationRepeater();
            triggerAutoSave();
            renderCVPaper();
        },
        updateProject: function (id, field, value) {
            const item = cvData.projects.find(x => x.id === id);
            if (item) {
                item[field] = value;
                triggerAutoSave();
                renderCVPaper();
            }
        },
        removeProject: function (id) {
            cvData.projects = cvData.projects.filter(x => x.id !== id);
            renderProjectRepeater();
            triggerAutoSave();
            renderCVPaper();
        },
        updateCert: function (id, field, value) {
            const item = cvData.certifications.find(x => x.id === id);
            if (item) {
                item[field] = value;
                triggerAutoSave();
                renderCVPaper();
            }
        },
        removeCert: function (id) {
            cvData.certifications = cvData.certifications.filter(x => x.id !== id);
            renderCertRepeater();
            triggerAutoSave();
            renderCVPaper();
        }
    };

    // Global Mobile Switcher
    window.switchMobileView = function (mode) {
        const editorCol = document.getElementById("editorColumn");
        const previewCol = document.getElementById("previewColumn");
        const tabEditor = document.getElementById("tabMobileEditor");
        const tabPreview = document.getElementById("tabMobilePreview");

        if (mode === "editor") {
            editorCol.classList.remove("mobile-hide");
            previewCol.classList.add("mobile-hide");
            tabEditor.classList.add("active");
            tabPreview.classList.remove("active");
        } else {
            editorCol.classList.add("mobile-hide");
            previewCol.classList.remove("mobile-hide");
            tabEditor.classList.remove("active");
            tabPreview.classList.add("active");
        }
    };

    // Global Zoom Setter
    window.setZoom = function (zoomClass) {
        const paper = document.getElementById("cvPaper");
        if (paper) {
            paper.className = paper.className.replace(/zoom-\w+/g, "").trim() + " " + zoomClass;
        }
    };

    // Global Summary Suggestions
    window.insertSummaryPrompt = function (type) {
        let text = "";
        if (type === "dev") {
            text = "Software Engineer berdedikasi dengan fokus pada pengembangan web modern dan skalabel. Memiliki keahlian kuat dalam arsitektur backend, RESTful API, dan interaktivitas frontend.";
        } else if (type === "designer") {
            text = "UI/UX Designer yang berfokus pada pengalaman pengguna yang estetis dan intuitif. Berpengalaman dalam user research, wireframing, design system di Figma, dan kolaborasi agile.";
        } else if (type === "graduate") {
            text = "Lulusan baru berprestasi dengan latar belakang pendidikan Teknik Informatika. Memiliki pemahaman kuat mengenai algoritma, pemrograman web, dan antusiasme tinggi untuk terus belajar teknologi baru.";
        }
        setValue("inputSummary", text);
        cvData.summary = text;
        triggerAutoSave();
        renderCVPaper();
    };

    // Run on DOM Ready
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();

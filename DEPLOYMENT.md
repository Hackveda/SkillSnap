# SkillSnap Deployment Checklist

Target directory: `public_html/skillsnap`

1. Upload/clone the repository contents into `public_html/skillsnap`.
2. Copy `config.local.example.php` to `config.local.php`.
3. Configure a dedicated SkillSnap DB/user, or use the same MySQL database with the isolated `skillsnap_*` table names.
4. Run `database/skillsnap_schema.sql`.
5. If Candidate Compass already has `job_details` and you want identical market data without sharing the live table, run `database/copy_job_details.sql` once.
6. Make `uploads/` writable by PHP but not publicly readable.
7. Confirm PHP extensions: PDO MySQL, mbstring, cURL and ZipArchive. PDF extraction benefits from `pdftotext` or `smalot/pdfparser`; visual review benefits from `pdftoppm`, Imagick, LibreOffice or GD; ATS PDF export benefits from mPDF.
8. Set a new SkillSnap admin password. Do not reuse the Candidate Compass password.
9. Use a separate/restricted OpenAI key if AI testing is enabled.
10. Test candidate CRUD, resume upload, role analysis, manual overrides, share link, company matching and ATS output.

## URL

If the domain document root is `public_html`, the application is available at:

`https://YOUR-DOMAIN/skillsnap/`

All AJAX/API links are relative (`?action=...`), so the application does not need hard-coded `/skillsnap` paths.

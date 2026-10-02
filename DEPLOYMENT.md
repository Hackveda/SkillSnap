# SkillSnap Deployment Checklist

Target directory: `public_html/skillsnap`

1. Upload repository files.
2. Copy `config.local.example.php` → `config.local.php`.
3. Configure an isolated DB/user or the same DB with the isolated `skillsnap_*` table names.
4. Run `database/skillsnap_schema.sql`.
5. Optional but recommended when the Candidate Compass `job_details` table exists: run `database/copy_job_details.sql`. This creates an exact `skillsnap_job_details` copy so interns cannot alter the original table.
6. Make `uploads/` writable by PHP but not publicly readable.
7. Confirm PHP extensions: PDO MySQL, mbstring, cURL, ZipArchive. PDF extraction additionally benefits from `pdftotext` or `smalot/pdfparser`; visual review benefits from `pdftoppm`, Imagick, LibreOffice or GD. PDF ATS export benefits from mPDF.
8. Set a new SkillSnap admin password; do not reuse the Candidate Compass password.
9. Use a separate/restricted OpenAI key if AI testing is enabled.
10. Test candidate CRUD, resume upload, role analysis, manual override, share link, company matching and ATS output.

## URL
If your domain points to `public_html`, the app will be available at `https://YOUR-DOMAIN/skillsnap/`.

# Web Developer Intern — PHP Application, AJAX & Sharing

## Presentation question
How does SkillSnap securely support private admin work and a limited public candidate view?

## Own these concepts
- PHP session authentication for admin actions.
- PDO prepared statements for candidate CRUD.
- Resume upload validation and isolated `/skillsnap/uploads` storage.
- Relative AJAX/API URLs so deployment under `public_html/skillsnap` needs no route rewrite.
- Random share tokens for candidate-facing links.
- Public payloads expose only the fields required for review.
- Public requirement updates verify both requirement ID and share token.
- HTML output escaping with `htmlspecialchars`.

## Demo flow
1. Login.
2. Create/save a candidate.
3. Upload a resume.
4. Run role analysis.
5. Create a share link.
6. Open the public profile in another tab.
7. Explain why SkillSnap uses separate table names, session name and upload folder.

Use `presentation.php` during the code walkthrough. The working source remains in `/working/skillsnap.php`.

# Web Developer Intern — PHP Application, AJAX & Sharing

## Presentation question
How does one PHP application securely support admin work and a limited public candidate view?

## Explain
1. Session authentication protects admin actions.
2. CRUD and analysis actions use prepared PDO statements and JSON responses.
3. Resume uploads are stored under `/skillsnap/uploads`; direct web access is blocked by `.htaccess`.
4. A random share token enables a limited public payload instead of exposing the full candidate row.
5. Public requirement updates validate both requirement ID and share token.
6. Relative AJAX URLs let the app work under `public_html/skillsnap` without hard-coded routes.

## Demo
Create candidate → upload resume → share profile → update one requirement in the public view.

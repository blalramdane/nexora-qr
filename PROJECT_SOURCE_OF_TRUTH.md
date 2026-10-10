# NEXORA QR — Source of Truth

- Canonical local project path: `D:\nexora`
- `D:\nexora` is a Windows directory junction to the existing Git working tree, not a second copy.
- Git repository root: `D:\project me\New folder\qr nexora`
- Laravel application root: `D:\nexora\ai-core`
- Remote: `https://github.com/blalramdane/nexora-qr.git`
- Active branch at designation time: `feat/menu-visual-overhaul`

## Working rule

Make project changes in `D:\nexora\ai-core` (inside the canonical working tree). Do not develop in the nested `D:\nexora\nexora-qr` clone. That folder has the same remote/starting commit but is a separate clone missing parts of the current application. It is being retained, not deleted, until its local changes are compared and reconciled deliberately.

Do not reset, discard, merge, push, or deploy the existing uncommitted work without an explicit review.
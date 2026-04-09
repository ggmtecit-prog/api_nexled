# Continuation Prompt

Copy-paste this to start a new chat:

---

I'm building a REST API for the Nexled/Tecit LED product system. The project is at `C:\xampp\htdocs\api_nexled\`.

**What exists:**
- `appdatasheets/` — the original working app (PHP form that generates PDF datasheets for LED products). Don't touch it.
- `api/` — new API folder with router, auth, and endpoint stubs. Not functional yet.

**Read these files first:**
- `api/README.md` — full API documentation (endpoints, data sources, reference code structure)
- `api/PLAN.md` — step-by-step build plan with phases

**Where we left off:**
- Phase 1 hasn't started yet. The endpoint files exist but need to be rewritten with real database queries.
- Start with Step 1.1 (families endpoint) and work through the plan.

**Important context:**
- Stack: PHP + MySQL on XAMPP (localhost)
- 3 databases: `tecit_referencias`, `tecit_lampadas`, `info_nexled_2024`
- DB credentials are in `appdatasheets/config.php` (gitignored)
- The existing `funcoes/` PHP files have the working queries — port them to the API endpoints
- GitHub repo: `ggmtecit-prog/api_nexled` (public)

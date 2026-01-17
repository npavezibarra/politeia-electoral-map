# Database Access for Agents

To access the LocalWP database via command line, use the following socket:

**Socket Path:**
`/Users/nicolasibarra/Library/Application Support/Local/run/XqvmEtSc4/mysql/mysqld.sock`

**Command to connect:**
```bash
mysql -u root -proot -S "/Users/nicolasibarra/Library/Application Support/Local/run/XqvmEtSc4/mysql/mysqld.sock" local
```
(Note: Default LocalWP credentials are usually user: `root`, pass: `root`, database: `local`)

# Database Schema Management Standards

Whenever a new table for the plugin database will be created or modified it must be done based on the paradigm of the **Modern DataBase**, as detailed below:

## 🔧 What You Had Before: Procedural/Inline Table Creation (Legacy Style)

### 👇 How it worked:
*   Tables were defined inside `Politeia_Reading_Activator::create_or_update_tables()`
*   That method was only called via `register_activation_hook()`
*   SQL CREATE TABLE strings were written inline, mixed with plugin logic
*   `dbDelta()` was used, but only when the plugin was activated

### To change or add a table:
*   You had to manually update that method
*   Possibly bump a version number
*   Then deactivate and reactivate the plugin to trigger it

### 🐌 Why it's problematic:
*   ❌ `dbDelta()` is fragile — small formatting mistakes (inline primary key, wrong spacing) silently prevent table creation
*   ❌ Activation-only — schema updates don't run unless you manually reactivate
*   ❌ Hard to scale — adding tables or migrations requires editing one big method
*   ❌ No versioned history — no record of what changed over time
*   ❌ No modularity — can't drop in new changes without modifying core logic

## 🚀 What You Have Now: Modular Installer + Upgrader System (Modern Architecture)

### ✅ How it works now:
*   All tables are centrally defined in `Installer::get_schema_sql()` (clean, discoverable)
*   `Installer::install()` calls `dbDelta()` on every table in that list
*   A constant (like `POLITEIA_READING_DB_VERSION`) tracks the schema version
*   `Upgrader::maybe_upgrade()` compares that version with the stored one in the DB
*   If the version changes or tables are missing, it triggers the installer
*   Every page load in admin runs a lightweight schema check (not just on activation)
*   Optional: drop-in migration files for more advanced, versioned upgrades

### ⚡ Why this is better:

| Problem Solved | How This Architecture Fixes It |
| :--- | :--- |
| **dbDelta fails silently** | Schema defined in one place, easy to debug/log |
| **Requires reactivation** | Schema updates run automatically via `maybe_upgrade()` |
| **Difficult to scale** | Add new tables just by appending to `get_schema_sql()` |
| **Fragile upgrade logic** | Upgrades are versioned, repeatable, idempotent |
| **No modularity** | You can now ship drop-in migration scripts in `includes/migrations/` |
| **Developer error-prone** | Clear separation of concerns between activation, upgrade, install |

## 🏷️ Architecture Name
This is the **Schema Versioning + Modular Installer Pattern**, or informally:
*   “Declarative schema management”
*   “Upgradable plugin schema”
*   “Centralized dbDelta installer with version tracking”

This pattern is commonly used in professional plugin development (WooCommerce, LearnDash, etc.) to ensure schema evolves safely and backward compatibility is preserved.

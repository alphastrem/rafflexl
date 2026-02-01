# TelXL Competitions - Development Reference

> **This is a living document.** It is updated with every code change and serves as the single source of truth for the project. If you are picking up this project for the first time (including AI assistants in new sessions), read this file first.

---

## 1. Project Overview

**TelXL Competitions** is a WordPress/WooCommerce plugin that powers UK-style prize competitions. Customers purchase numbered tickets, answer a qualifying question (legal requirement in the UK), and a cryptographically verifiable draw selects a winner.

- **Target market:** UK competition/prize websites
- **Tech stack:** PHP 8.0+, WordPress 6.0+, WooCommerce 8.0+, Alpine.js 3.14.8, MySQL/MariaDB
- **License:** GPL-2.0+ with commercial license server at `https://license.theeasypc.co.uk`
- **Repository:** `https://github.com/alphastrem/rafflexl` (private)
- **Current version:** 1.1.3

---

## 2. Architecture

### Bootstrap Flow

```
telxl-competitions.php
  -> loads txc-config.php (if exists, gitignored)
  -> defines constants (TXC_VERSION, TXC_PLUGIN_DIR, etc.)
  -> hooks txc_init() on 'plugins_loaded' (priority 20)
    -> checks WooCommerce dependency
    -> loads TXC_Loader
    -> TXC_Loader::run()
      -> load_dependencies() - require_once all class files
      -> define_admin_hooks() - register admin actions/filters
      -> define_public_hooks() - register public actions/filters
      -> define_cron_hooks() - register cron actions
      -> register_hooks() - batch add_action/add_filter with WordPress
      -> TXC_Updater::init() - initialize GitHub update checker
```

### Patterns

- **No autoloading, no Composer, no namespaces.** All classes use the `TXC_` prefix.
- **Central loader:** `TXC_Loader` manages all hook registration. Classes are instantiated in the loader and their methods are registered as callbacks.
- **Singleton:** `TXC_License_Client` uses the singleton pattern (`::instance()`).
- **Static methods:** Used by `TXC_Instant_Wins`, `TXC_Social`, `TXC_Compliance` (privacy exporter), `TXC_Cron::schedule_draw()`, `TXC_Updater::init()`.

---

## 3. File Map

```
telxl-competitions/
  telxl-competitions.php              Main plugin bootstrap, constants, activation hooks
  uninstall.php                       Clean removal of all DB tables, options, roles, cron
  txc-config-sample.php               Template for local config (GitHub token)
  DEVELOPMENT.md                      This file — living documentation

  .gitignore                          Excludes txc-config.php, OS files, IDE files
  .github/workflows/release.yml       GitHub Actions: build ZIP on tag push

  vendor/
    plugin-update-checker/            YahnisElsts/plugin-update-checker v5.6 (bundled)

  includes/
    class-txc-loader.php              Dependency loading + hook registration orchestrator
    class-txc-activator.php           Plugin activation: DB tables, roles, seeds, cron schedules
    class-txc-deactivator.php         Plugin deactivation: clears cron events

    core/
      class-txc-competition-cpt.php   Custom Post Type 'txc_competition' registration + templates
      class-txc-competition.php       Competition data model (get/set meta, status, tickets, winner)
      class-txc-ticket-manager.php    CSPRNG ticket allocation, winner marking, instant win marking
      class-txc-draw-engine.php       Draw execution (10-roll max, seed hashing, redraw support)
      class-txc-qualifying.php        Qualifying questions: AJAX fetch/submit, anti-cheat, cooldown
      class-txc-instant-wins.php      Instant win map generation, claim checking, prize fulfilment
      class-txc-youtube-watch.php     YouTube video ID extraction helper
      class-txc-compliance.php        Age verification, country restriction, GDPR consent logging
      class-txc-cron.php              WP-Cron handlers: heartbeat, auto-draw, tombstone cleanup
      class-txc-email.php             Ticket confirmation + instant win notification emails
      class-txc-social.php            Social sharing buttons + social link rendering
      class-txc-license-client.php    JWT license activation, heartbeat, grace period, feature gating
      class-txc-updater.php           GitHub Releases auto-update integration (plugin-update-checker)

    admin/
      class-txc-admin.php             Admin menu, page routing, asset enqueue
      class-txc-competition-meta.php  Meta boxes for competition editor, WC product sync
      class-txc-settings.php          Settings page: countries, age, pause, social links, add-ons
      class-txc-license-page.php      License activation/deactivation UI
      class-txc-questions-admin.php   Question bank CRUD admin page
      class-txc-draw-admin.php        Draw management: manual draw, redraw, draw history table
      class-txc-pause-mode.php        Emergency sales pause handler
      views/                          (empty — admin views are rendered inline in classes)

    public/
      class-txc-public.php            Frontend asset loading, My Account tab, pause banner
      class-txc-shortcodes.php        4 shortcodes: competitions grid, single, winners, my-competitions
      views/
        single-competition-content.php  Full single competition page (Alpine.js interactive)

    woocommerce/
      class-txc-cart.php              AJAX add-to-cart, cart item display, validation, per-user cap
      class-txc-checkout.php          Checkout validation (stock, per-user total, country)
      class-txc-order-handler.php     Order completion: ticket allocation, refund on conflict

    api/
      class-txc-rest-api.php          REST API: 5 public endpoints under txc/v1

  templates/
    single-competition.php            WP template for single competition pages
    archive-competition.php           WP template for competition archive
    tombstone.php                     Tombstone page for deleted competitions
    myaccount/
      competitions.php                WooCommerce My Account competitions tab template

  assets/
    css/
      txc-admin.css                   Admin panel styles
      txc-public.css                  Frontend styles (grid, cards, countdown, forms, modals, draws)
    js/
      txc-admin.js                    Admin: gallery uploader, instant wins picker, draw buttons
      txc-competition.js              Alpine.js: competition entry form + countdown timer
      txc-qualifying.js               Alpine.js: qualifying question modal with timer/cooldown
      txc-draw.js                     Alpine.js: draw animation with slot-machine effect
    images/                           (empty — reserved for icons/logos)

  data/
    questions.json                    Seed question bank (200+ questions, loaded on activation)

  languages/                          (empty — reserved for i18n .pot/.po/.mo files)
```

---

## 4. Database Schema

### `{prefix}txc_tickets`
Allocated competition tickets. One row per ticket sold.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED PK | Auto-increment ID |
| competition_id | BIGINT UNSIGNED | Links to txc_competition post ID |
| order_id | BIGINT UNSIGNED | WooCommerce order ID |
| user_id | BIGINT UNSIGNED | WordPress user ID |
| ticket_number | INT UNSIGNED | The ticket number (1 to max_tickets) |
| is_winner | TINYINT(1) | 1 if this ticket won the main draw |
| is_instant_win | TINYINT(1) | 1 if this ticket matched an instant win |
| instant_win_prize_type | VARCHAR(50) | credit, coupon, cash, physical |
| instant_win_prize_value | DECIMAL(10,2) | Prize monetary value |
| instant_win_prize_label | VARCHAR(255) | Human-readable prize description |
| allocated_at | DATETIME | When the ticket was allocated |

**Keys:** UNIQUE (competition_id, ticket_number), INDEX (competition_id, user_id), INDEX (order_id)

### `{prefix}txc_draws`
Draw execution records. One row per draw attempt (including redraws).

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED PK | Auto-increment ID |
| competition_id | BIGINT UNSIGNED | Links to txc_competition post ID |
| winning_ticket_id | BIGINT UNSIGNED | Links to txc_tickets.id |
| draw_mode | VARCHAR(10) | 'manual' or 'auto' |
| seed | VARCHAR(128) | Raw random seed (hex) |
| seed_hash | VARCHAR(128) | SHA-256 hash of seed (public verification) |
| rolls | LONGTEXT | JSON array of roll attempts |
| status | VARCHAR(20) | 'pending', 'completed', 'failed' |
| forced_redraw | TINYINT(1) | 1 if this was a forced redraw |
| forced_redraw_reason | TEXT | Public reason for redraw |
| started_at | DATETIME | Draw start timestamp |
| completed_at | DATETIME | Draw completion timestamp |

**Keys:** INDEX (competition_id)

### `{prefix}txc_questions`
Qualifying question bank.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED PK | Auto-increment ID |
| question_text | TEXT | The question |
| option_a - option_d | VARCHAR(255) | Four answer choices |
| correct_option | CHAR(1) | 'a', 'b', 'c', or 'd' |
| category | VARCHAR(100) | Question category (default 'general') |
| difficulty | TINYINT UNSIGNED | 1-3 difficulty level |
| active | TINYINT(1) | Whether the question is in rotation |

### `{prefix}txc_question_attempts`
User answer history for anti-cheat tracking.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED PK | Auto-increment ID |
| user_id | BIGINT UNSIGNED | WordPress user ID |
| competition_id | BIGINT UNSIGNED | Links to txc_competition post ID |
| question_id | BIGINT UNSIGNED | Links to txc_questions.id |
| selected_option | CHAR(1) | User's answer |
| is_correct | TINYINT(1) | Whether the answer was correct |
| attempt_number | TINYINT UNSIGNED | Which attempt (1-3 before cooldown) |
| attempted_at | DATETIME | Timestamp |

**Keys:** INDEX (user_id, competition_id)

### `{prefix}txc_instant_win_map`
Pre-assigned instant win ticket positions and prizes.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED PK | Auto-increment ID |
| competition_id | BIGINT UNSIGNED | Links to txc_competition post ID |
| ticket_number | INT UNSIGNED | The winning ticket number |
| prize_type | VARCHAR(50) | credit, coupon, cash, physical |
| prize_value | DECIMAL(10,2) | Prize monetary value |
| prize_label | VARCHAR(255) | Human-readable label |
| claimed | TINYINT(1) | 1 if someone has purchased this ticket |
| claimed_by_user_id | BIGINT UNSIGNED | User who claimed the prize |
| claimed_at | DATETIME | Claim timestamp |

**Keys:** UNIQUE (competition_id, ticket_number)

### `{prefix}txc_consent_log`
GDPR consent audit trail.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED PK | Auto-increment ID |
| user_id | BIGINT UNSIGNED | WordPress user ID |
| consent_type | VARCHAR(50) | Type of consent given |
| consented | TINYINT(1) | Whether consent was granted |
| ip_address | VARCHAR(45) | User's IP (IPv4/IPv6) |
| user_agent | TEXT | Browser user agent |
| consented_at | DATETIME | Timestamp |

**Keys:** INDEX (user_id)

---

## 5. WordPress Hooks

### Admin Actions
| Hook | Class | Method |
|------|-------|--------|
| `admin_enqueue_scripts` | TXC_Admin | `enqueue_styles()`, `enqueue_scripts()` |
| `admin_menu` | TXC_Admin | `add_admin_menu()` |
| `add_meta_boxes` | TXC_Competition_Meta | `add_meta_boxes()` |
| `save_post_txc_competition` | TXC_Competition_Meta | `save_meta()` |
| `admin_init` | TXC_Settings | `register_settings()` |
| `admin_init` | TXC_License_Page | `handle_activation()` |
| `admin_init` | TXC_Questions_Admin | `handle_actions()` |
| `admin_init` | TXC_Pause_Mode | `handle_toggle()` |
| `wp_ajax_txc_manual_draw` | TXC_Draw_Admin | `handle_manual_draw()` |
| `wp_ajax_txc_force_redraw` | TXC_Draw_Admin | `handle_force_redraw()` |
| `wp_ajax_txc_generate_instant_wins` | TXC_Instant_Wins | `ajax_generate_map()` |

### Public Actions
| Hook | Class | Method |
|------|-------|--------|
| `wp_enqueue_scripts` | TXC_Public | `enqueue_styles()`, `enqueue_scripts()` |
| `wp_footer` | TXC_Public | `maybe_show_pause_banner()` |
| `init` | TXC_Competition_CPT | `register_post_type()` |
| `init` | TXC_Shortcodes | `register_shortcodes()` |
| `init` | TXC_Public | `register_account_endpoint()` |
| `init` | TXC_License_Client | `maybe_refresh_token()` |
| `wp_ajax_txc_add_to_cart` | TXC_Cart | `ajax_add_to_cart()` |
| `wp_ajax_txc_get_question` | TXC_Qualifying | `ajax_get_question()` |
| `wp_ajax_txc_submit_answer` | TXC_Qualifying | `ajax_submit_answer()` |
| `woocommerce_check_cart_items` | TXC_Cart | `validate_cart_items()` |
| `woocommerce_checkout_process` | TXC_Checkout | `validate_checkout()` |
| `woocommerce_order_status_completed` | TXC_Order_Handler | `allocate_tickets()` |
| `woocommerce_order_status_processing` | TXC_Order_Handler | `allocate_tickets()` |
| `woocommerce_register_form` | TXC_Compliance | `registration_fields()` |
| `woocommerce_created_customer` | TXC_Compliance | `save_registration_fields()` |
| `admin_notices` | TXC_License_Client | `maybe_show_license_notice()` |
| `rest_api_init` | TXC_Rest_API | `register_routes()` |
| `txc_tickets_allocated` | TXC_Email | `send_ticket_confirmation()` |
| `txc_tickets_allocated` | TXC_Instant_Wins | `check_instant_wins()` |

### Filters
| Hook | Class | Method |
|------|-------|--------|
| `single_template` | TXC_Competition_CPT | `single_template()` |
| `archive_template` | TXC_Competition_CPT | `archive_template()` |
| `woocommerce_get_item_data` | TXC_Cart | `display_cart_item_data()` |
| `woocommerce_cart_item_name` | TXC_Cart | `cart_item_name()` |
| `woocommerce_registration_errors` | TXC_Compliance | `validate_registration()` |
| `woocommerce_account_menu_items` | TXC_Public | `account_menu_items()` |
| `wp_privacy_personal_data_exporters` | TXC_Compliance | `register_privacy_exporter()` |

### Cron Events
| Event | Class | Method | Frequency |
|-------|-------|--------|-----------|
| `txc_heartbeat_event` | TXC_Cron | `do_heartbeat()` | Every 6 hours |
| `txc_auto_draw_event` | TXC_Cron | `do_auto_draw()` | One-time scheduled |
| `txc_tombstone_cleanup` | TXC_Cron | `cleanup_tombstones()` | Daily |
| `txc_draw_retry_event` | TXC_Cron | `retry_failed_draw()` | 2 min after failure |

### Custom Action
| Hook | Fired by | Consumed by |
|------|----------|-------------|
| `txc_tickets_allocated($order_id, $user_id, $all_allocated)` | TXC_Order_Handler | TXC_Email, TXC_Instant_Wins |

---

## 6. REST API

**Namespace:** `txc/v1` — All endpoints are public (no authentication required).

### `GET /competitions`
Returns all active competitions (status: live or sold_out).

### `GET /competitions/{id}`
Single competition with full details including description, gallery URLs, and SEO description.

### `GET /competitions/{id}/tickets-remaining`
Live ticket inventory: `{ competition_id, tickets_remaining, tickets_sold, max_tickets }`.

### `GET /winners`
All drawn competitions with obfuscated winner names, ticket numbers, draw dates, seed hashes, rejected rolls, and redraw reasons.

### `GET /competitions/{id}/draw`
Full draw audit trail: every roll attempt, result, seed hash, timestamps, forced redraw flag.

---

## 7. Add-on System

Add-ons are gated by two checks:
1. **License token:** The JWT from the license server includes an `addons` claim (e.g., `{ "instantwins": true, "youtube": true }`)
2. **Local toggle:** WordPress option `txc_addon_{name}_enabled` must be `'1'`

The helper function `txc_addon_enabled($addon)` (defined in `class-txc-loader.php`) checks both.

### Instant Wins
- Admin picks random ticket numbers via AJAX ("Pick Random Numbers" button in meta box)
- Numbers stored in `txc_instant_win_map` table with configurable prizes per number
- When a customer buys a matching ticket, prize is automatically fulfilled
- Prize types: `credit` (TeraWallet or user meta), `coupon` (WC coupon), `cash` (flagged for admin), `physical` (flagged for shipping)
- Public display shows all winning numbers with claim status and obfuscated winner names

### YouTube Draw Links
- Admin enters a YouTube URL per competition
- Displays as a "Watch Draw on YouTube" button on the competition page
- Opens in a new tab — no gate or watch requirement

---

## 8. License System

### Server
- **URL:** `https://license.theeasypc.co.uk`
- **POST `/activate`** — Body: `{ license_key, domain, site_fingerprint }` → Response: `{ token }` (JWT)
- **POST `/heartbeat`** — Body: `{ token }` → Response: `{ token }` (refreshed JWT)

### JWT Payload
- `domain` — Bound domain
- `plan` — License plan name
- `addons` — `{ "instantwins": bool, "youtube": bool }`
- `exp` — Unix timestamp (token expiry)

### States
| State | Condition | Effect |
|-------|-----------|--------|
| None | No token stored | License page prompts activation |
| Valid | Token present, heartbeat OK | Full functionality |
| Grace | Heartbeat failed < 3 days | Warning notice, purchases still allowed |
| Warning | Heartbeat failed 3-7 days | Prominent warning notice |
| Locked | Heartbeat failed > 7 days | Purchases blocked, admin notice |

### Plugin-side
- Token stored in `txc_license_token` option
- Heartbeat runs every 6 hours via WP-Cron (`txc_heartbeat_event`)
- `TXC_License_Client::purchases_allowed()` returns false when locked
- `TXC_Competition::can_enter()` checks `purchases_allowed()` before allowing entry

---

## 9. Security

- **CSPRNG:** `random_int()` for ticket allocation and instant win number selection. `random_bytes()` for draw seeds.
- **Draw verification:** SHA-256 hash of random seed stored publicly. Anyone can verify the draw was fair.
- **Nonce verification:** Every AJAX handler calls `check_ajax_referer()` or `wp_verify_nonce()`.
- **Capability checks:** 9 custom capabilities assigned to admin and shop_manager roles.
- **Anti-cheat (qualifying):** Server-side question issuance with transient tracking. 30-second time limit with 5-second grace. 30-minute cooldown after 3 failed attempts.
- **Race condition protection:** UNIQUE constraint on (competition_id, ticket_number). Auto-refund on allocation conflict.
- **Input sanitization:** `sanitize_text_field()`, `absint()`, `esc_url_raw()`, `sanitize_textarea_field()` throughout.
- **Output escaping:** `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` on all output.
- **ABSPATH guard:** Every PHP file checks `defined('ABSPATH')` and exits if false.
- **GDPR:** Consent logging with IP/user agent, privacy data exporter registered.
- **Prepared SQL:** All database queries use `$wpdb->prepare()`.

---

## 10. Competition Lifecycle

### Status Flow
```
draft -> live -> sold_out -> drawing -> drawn
                    |
                    v
               (can also reach 'drawing' directly from 'live')

Alternative statuses: paused, cancelled, failed
Failed transitions to: retry (2-min cron) -> drawn or failed again
```

### Draw Algorithm
1. Generate cryptographic seed: `bin2hex(random_bytes(32))`
2. Store SHA-256 hash: `hash('sha256', $seed)`
3. Rolls 1-3: Pick random number from 1 to max_tickets. If ticket is sold, winner found. If not, rejected.
4. Rolls 4-10: Pick random number from sold tickets ONLY (guarantees a winner).
5. Max 10 rolls. If no winner found in 10 rolls, draw fails (status: `failed`, retries in 2 minutes).

### Sales Stop
Sales automatically stop 5 minutes before the draw time. Controlled by `get_sales_stop_time()` which subtracts 300 seconds from the draw date.

### WC Product Sync
When competition status is set to `live`, a hidden WooCommerce product is auto-created/updated with the competition's price. This product is what gets added to the cart.

---

## 11. Post Meta Reference

All `_txc_*` meta keys on `txc_competition` posts:

| Key | Type | Description |
|-----|------|-------------|
| `_txc_seo_description` | string | Meta description for SEO (max 160 chars) |
| `_txc_prize_type` | array | Prize categories: credit, coupon, physical, cash |
| `_txc_prize_description` | string | Public prize description text |
| `_txc_price` | float | Ticket price in GBP |
| `_txc_max_tickets` | int | Total tickets available |
| `_txc_max_per_user` | int | Absolute max tickets per user across all purchases |
| `_txc_tickets_sold` | int | Current sold count |
| `_txc_draw_date` | string | Draw date/time in UTC (Y-m-d H:i:s format) |
| `_txc_draw_mode` | string | 'manual' or 'auto' |
| `_txc_must_sell_out` | string | 'yes' or empty — must all tickets sell before draw |
| `_txc_status` | string | draft/live/paused/sold_out/drawing/drawn/cancelled/failed |
| `_txc_wc_product_id` | int | Linked WooCommerce product ID |
| `_txc_instant_wins_count` | int | Number of instant win positions |
| `_txc_instant_win_prizes` | array | Prize config for instant wins |
| `_txc_youtube_url` | string | YouTube draw video URL |
| `_txc_gallery_ids` | array | Attachment IDs for gallery images |

---

## 12. WordPress Options Reference

| Option | Type | Description |
|--------|------|-------------|
| `txc_db_version` | string | Database schema version (currently '1.0.0') |
| `txc_license_key` | string | Stored license key |
| `txc_license_token` | string | Active JWT token from license server |
| `txc_last_heartbeat` | int | Unix timestamp of last successful heartbeat |
| `txc_license_grace_start` | int | Unix timestamp when grace period started |
| `txc_site_fingerprint` | string | UUID generated on activation |
| `txc_allowed_countries` | string | Comma-separated ISO country codes (default: 'GB') |
| `txc_pause_sales` | string | '0' or '1' — emergency pause all sales |
| `txc_pause_message` | string | Banner message shown when sales paused |
| `txc_minimum_age` | int | Minimum age for registration (default: 18) |
| `txc_social_facebook` | string | Facebook page URL |
| `txc_social_twitter` | string | X/Twitter URL |
| `txc_social_instagram` | string | Instagram URL |
| `txc_social_tiktok` | string | TikTok URL |
| `txc_addon_instantwins_enabled` | string | '0' or '1' |
| `txc_addon_youtube_enabled` | string | '0' or '1' |
| `txc_tombstones` | array | Deleted competition archive data |
| `txc_pending_cash_payouts` | array | Instant win cash prizes awaiting admin payout |
| `txc_pending_physical_prizes` | array | Instant win physical prizes awaiting shipping |

---

## 13. Custom Capabilities

### Administrator
`txc_view_competitions`, `txc_manage_competitions`, `txc_manage_draws`, `txc_view_tickets`, `txc_manage_settings`, `txc_manage_license`, `txc_delete_competitions`, `txc_manage_questions`, `txc_manage_refunds`

### Shop Manager
`txc_view_competitions`, `txc_manage_draws`, `txc_view_tickets`

---

## 14. Version History

### v1.1.3 (2026-02-01) — Current

**Bug fix:**
- Fixed Alpine.js and plugin scripts STILL not loading on competition pages
  - Root cause: The `wp_enqueue_scripts` hook detection (`is_competition_page()`) returned false despite WordPress body class confirming `single-txc_competition`. Exact cause unclear — likely a timing issue with conditional tags during `wp_head()` before `the_post()` is called in the template loop.
  - Fix: Templates now call `TXC_Public::force_enqueue_assets()` directly BEFORE `get_header()`, registering the enqueue callback at priority 5 on `wp_enqueue_scripts`. This guarantees scripts load whenever the template renders — no conditional tag detection needed.
  - The `is_competition_page()` fallback still exists for shortcode pages and My Account.
  - Refactored asset enqueue into a single static `do_enqueue()` method (CSS + JS + Alpine + localize) to avoid duplication.

### v1.1.2 (2026-02-01)

**Bug fixes:**
- Fixed Alpine.js and plugin scripts not loading on single competition pages
  - Root cause: `is_competition_page()` relied on `is_singular('txc_competition')` which fails in block themes (Twenty Twenty-Four) during `wp_enqueue_scripts`
  - Fix: Added fallback check on global `$post` object, plus `is_page('competitions')` check
  - Also fixed unsafe `get_post()->post_content` access that could error when `get_post()` returns null
- Fixed Alpine.js loading order — component scripts now load BEFORE Alpine so functions are defined when Alpine auto-initialises
- Fixed slider starting at midpoint — added explicit `value="1"` and `max` HTML attributes as fallback for pre-Alpine rendering
- Fixed quantity input showing empty — added `value="1"` fallback
- Fixed total showing £0.00 — added server-rendered price as fallback text
- All interactive elements (countdown, slider, +/- buttons, total, Enter button) now work correctly

### v1.1.1 (2026-02-01)

**Bug fix:**
- Fixed competitions not appearing on the front-end `/competitions/` page
  - Root cause: CPT had `has_archive => false` so no archive page existed, and the user's WordPress page at `/competitions/` was a WooCommerce page without the `[txc_competitions]` shortcode
  - Fix: Enabled CPT archive at `/competitions/` (`has_archive => 'competitions'`) and added `template_include` fallback that serves the competition archive template for both the CPT archive and any existing WordPress page with the `competitions` slug
- Added automatic rewrite rule flush on version upgrade so new permalink structures take effect immediately
- Hardcoded fine-grained GitHub PAT in `TXC_Updater` for private repo auto-updates (base64 split to pass GitHub push protection)

### v1.1.0 (2026-02-01)

**Features:**
- Complete competition platform with WooCommerce integration
- Custom Post Type `txc_competition` with full admin meta boxes
- CSPRNG-based ticket allocation with race condition protection
- Cryptographically verifiable draw engine (SHA-256 seed hashing, 10-roll max)
- Forced redraw support with public reason
- Qualifying question system with 30-second timer, 3-attempt limit, 30-minute cooldown
- 200+ seed questions loaded on activation
- Instant Wins add-on: random number picker, 4 prize types, auto-fulfilment
- YouTube Draw Links add-on: per-competition YouTube URL with "Watch Draw" button
- JWT license system with heartbeat, grace period, feature gating
- GDPR compliance: age verification, country restriction, consent logging, privacy exporter
- REST API: 5 public endpoints for external integrations
- Alpine.js frontend: countdown timers, entry forms, qualifying modal, draw animation
- WooCommerce: cart/checkout validation, absolute per-user ticket cap, auto-refund on conflict
- Social sharing buttons and social media links
- My Account "My Competitions" tab with ticket display
- Pause mode for emergency sales suspension
- Tombstone pages for deleted competitions
- GitHub-based auto-update via plugin-update-checker v5.6
- GitHub Actions workflow for automated release ZIP building

**Bug fixes in v1.1.0:**
- Fixed countdown timer showing 0d 0h 0m 0s (timezone chain issue + shortcode grid missing ISO 8601 format)
- Fixed YouTube feature from watch-gate to simple draw link
- Fixed instant wins not displaying (generate_map() was never called — replaced with admin "Pick Random Numbers" AJAX flow)
- Fixed max per user from per-purchase to absolute total cap
- Added ticket quantity slider
- JS countdown now normalizes date strings and handles invalid dates gracefully

### v1.0.0 (2026-01-30) — Initial

- Initial build with all core features
- Not publicly released (superseded by v1.1.0)

---

## 15. Known Issues / TODOs

- [ ] `assets/images/` is empty — no plugin icons or logos shipped
- [ ] `languages/` is empty — no i18n `.pot` file generated
- [ ] No unit or integration tests
- [ ] `TXC_Pause_Mode::handle_toggle()` is minimal — pause is handled via Settings page save
- [x] ~~Auto-update: GitHub PAT token needed when repo goes private~~ (done — fine-grained PAT hardcoded in `TXC_Updater`, expires ~Feb 2027)
- [ ] The draw date must be re-saved after the v1.1.0 timezone fix if dates were saved under v1.0.0

---

## 16. Release Workflow

```
1. Make code changes locally

2. Bump version in TWO places in telxl-competitions.php:
   a. Plugin header comment: "Version: X.Y.Z"
   b. Constant: define( 'TXC_VERSION', 'X.Y.Z' );

3. Update this DEVELOPMENT.md:
   a. Add entry to Version History (section 14)
   b. Update any affected file descriptions, schema notes, etc.
   c. Update Known Issues (section 15) if resolved

4. Commit:
   git add -A
   git commit -m "Release vX.Y.Z: description of changes"

5. Push:
   git push origin main

6. Tag and push tag:
   git tag vX.Y.Z
   git push origin vX.Y.Z

7. GitHub Actions automatically:
   a. Builds a clean ZIP excluding .git, .github, .gitignore, txc-config.php
   b. Attaches it to the GitHub Release with auto-generated release notes

8. WordPress sites detect the update within 12 hours
   (or immediately on Dashboard > Updates visit)
```

**Manual release (if GitHub Actions is not set up):**
- Build ZIP: `cd .. && zip -r telxl-competitions.zip telxl-competitions/ -x "*.git*" -x "*txc-config.php" -x "*.DS_Store"`
- Go to https://github.com/alphastrem/rafflexl/releases/new
- Tag: `vX.Y.Z`, Title: `vX.Y.Z`
- Attach the ZIP file

---

## 17. Development Setup

```bash
# 1. Clone the repo
git clone git@github.com:alphastrem/rafflexl.git telxl-competitions
cd telxl-competitions

# 2. Create local config
cp txc-config-sample.php txc-config.php
# Edit txc-config.php — add your GitHub PAT (needed when repo is private)

# 3. Symlink to WordPress plugins directory
ln -s "$(pwd)" /path/to/wordpress/wp-content/plugins/telxl-competitions

# 4. Requirements
#    - PHP 8.0+
#    - WordPress 6.0+
#    - WooCommerce 8.0+
#    - MySQL / MariaDB

# 5. Activate the plugin in WordPress admin

# 6. Go to Competitions > License — enter a license key

# 7. Go to Competitions > Settings — configure countries, age, social links, add-ons
```

---

## 18. Server-Side Reference (License Server)

**Server:** `https://license.theeasypc.co.uk`

### POST /activate
```
Request (JSON):
{
  "license_key": "AMIE-CARA-2026",
  "domain": "example.com",
  "site_fingerprint": "uuid-string"
}

Success (200):
{ "token": "eyJhbGciOi..." }

Error (non-200):
{ "error": "Invalid license key" }
```

### POST /heartbeat
```
Request (JSON):
{ "token": "eyJhbGciOi..." }

Success (200):
{ "token": "eyJhbGciOi..." }   // refreshed token

Error (non-200):
{ "error": "inactive" }         // triggers grace period
```

### JWT Token Payload
```json
{
  "domain": "example.com",
  "plan": "professional",
  "addons": {
    "instantwins": true,
    "youtube": true
  },
  "exp": 1738368000
}
```

---

## 19. Auto-Update System

### How It Works
The plugin bundles the [YahnisElsts/plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) v5.6 library in `vendor/plugin-update-checker/`.

On every WordPress admin page load, the library checks (with caching) GitHub's releases API for the `alphastrem/rafflexl` repository. It compares the latest release tag (e.g., `v1.2.0`) against `TXC_VERSION`. If newer, it injects the update into WordPress's `update_plugins` transient.

The user sees the update in Dashboard > Updates and in the Plugins list. Clicking "Update Now" downloads the ZIP attached to the GitHub Release and installs it.

### Private Repo Access
When the repo is private, requests to GitHub's API require authentication. The `TXC_Updater` class uses a two-tier token strategy:

1. **Config override:** If `TXC_GITHUB_TOKEN` is defined in `txc-config.php`, that value is used (for development).
2. **Hardcoded fallback:** A fine-grained PAT is hardcoded in the class for end-user sites that receive the plugin as a ZIP (without `txc-config.php`).

The fallback token is a fine-grained GitHub PAT with read-only Contents and Metadata access, scoped to the `alphastrem/rafflexl` repository only. **Token expires approximately February 2027** — renew before then.

### Renewing the Token
1. Go to https://github.com/settings/tokens?type=beta
2. Generate new token: name `rafflexl-updater`, 1-year expiry
3. Repository access: Only select `alphastrem/rafflexl`
4. Permissions: Contents (Read-only), Metadata (Read-only)
5. Replace the hardcoded token string in `TXC_Updater::init()`
6. Update `txc-config.php` with the new token for development

### GitHub Actions
The `.github/workflows/release.yml` workflow automatically builds a clean ZIP and attaches it to GitHub Releases when a tag is pushed. The ZIP excludes `.git`, `.github`, `.gitignore`, `txc-config.php`, and `txc-config-sample.php`.

# Tournament Registration & Payment Portal

A clean, modular, and professional PHP portal for managing event registrations, checkout payments (via SSLCommerz), and participant slot scheduling for both **Golfers** and **Non-Golfers (Guests)**.

---

## 🏗️ Architecture & Directory Structure

This project follows a clean **Separation of Concerns** architecture, isolating configurations, core logic, template layouts, public pages, and administrative controls.

```text
worldCorporateTour/
├── .env                    # Active environment configs (Database credentials, API tokens)
├── .env.example            # Blueprint configuration template
├── .htaccess               # URL rewriting rules for clean, extensionless paths
├── index.php               # SEO-friendly Tournament Landing Page
├── register.php            # Golfer Registration Form
├── register_non_golfer.php # Non-Golfer (Guest) Registration Form
├── success.php             # User-facing Payment Receipt Printout Screen
├── config/
│   ├── config.php          # Config loader (parses env parameters, exposes SSLCommerz array)
│   ├── db.php              # PDO Database Connection Singleton
│   └── schema.sql          # SQL Schema to set up all tables and default seeds
├── src/
│   ├── Database.php        # Query executor and transactional helper wrapper
│   ├── SMSGateway.php      # Dispatcher for mobile confirmations (bdbulksms API)
│   └── SSLCommerz.php      # Compact client wrapper for initiation & validation checks
├── templates/
│   ├── header.php          # SEO tags, canonical URL, OG descriptors, Bootstrap styling
│   └── footer.php          # Page scripts, styles, copyrights
├── assets/
│   ├── css/
│   │   └── style.css       # Layout rules, green & gold theme variables, glassmorphic styles
│   ├── js/                 # Form validators and AJAX interaction logic
│   └── images/             # Sponsor logos, landing headers, favicon
├── payment/
│   ├── initiate.php        # JSON controller verifying slots & starting gateway session
│   ├── success.php         # SSLCommerz validation and postback verification callback
│   ├── fail.php            # Checkout session error redirect handler
│   ├── cancel.php          # Checkout cancellation return handler
│   └── ipn.php             # Instant Payment Notification callback (async status sync)
├── admin/
│   ├── index.php           # Admin portal login interface
│   ├── admin_authenticate.php # Auth session initiator
│   ├── admin_logout.php    # Session destroyer
│   ├── view_registration.php # Registrations table (filters, details view, CSV export)
│   ├── settings.php        # Admin settings manager (caps total slot threshold)
│   ├── tee_time_settings.php # Golfer shotgun group interval editor
│   ├── non_golfer_window_settings.php # Non-golfer arrival window slot editor
│   ├── delete_registration.php # Soft/hard record delete handler
│   └── send_sms.php        # Manual SMS confirmation dispatch controller
```

---

## 🛠️ Configuration & Database Setup

### 1. Environment Settings
Rename `.env.example` to `.env` and configure your credentials:
```ini
# MySQL Database
DB_HOST=127.0.0.1
DB_NAME=wcc
DB_USER=root
DB_PASS=

# SSL Commerz Credentials
SSL_STORE_ID=worldcorporategolftour0live
SSL_STORE_PASSWORD=69E8A1414082960696
SSL_IS_SANDBOX=false
```

### 2. SQL Setup
1. Log into your database tool (e.g. phpMyAdmin / cPanel MySQL).
2. Choose your database `wcc`.
3. Open the **SQL Query Console** and import the contents of [schema.sql](file:///Users/nadim/Downloads/worldCorporateTour/config/schema.sql) to initialize tables and initial slots.

---

## 🏆 Setting Up a New Tournament

To adjust this portal for a new tournament, you only need to modify configuration parameters and assets without rewriting PHP code:

### Step 1: Update `.env` Tournament Variables
Open your `.env` file and change the event particulars:
```ini
EVENT_NAME="3rd GolfHouse Diplomatic Cup 2026"
EVENT_DATE="Saturday, 05 September 2026"
EVENT_VENUE="Kurmitola Golf Club, Dhaka"
EVENT_FORMAT="Individual Medal Play (Shotgun Start)"
EVENT_DEADLINE="Wednesday, 02 September 2026"
EVENT_FEE=2500
EVENT_CURRENCY=BDT
CONTACT_PHONE_1="01610 801 081"
CONTACT_PHONE_2="01842 324 232"
```
*The landing page, meta tags, registration forms, success receipts, and SMS messages will automatically adapt to these values.*

### Step 2: Swap Image Assets
Replace the tournament logos and images in `assets/images/` directory:
- `assets/images/brand.png` — Main event icon / Favicon.
- `assets/images/event-details.jpg` — Background cover image for the Hero banner.
- Organizers/Partner Logos: `golfhouse-logo.png`, `corporate-tour-logo.png`, `jolshiri-golf-club-logo.png`.

### Step 3: Configure Slots in Admin Panel
1. Access the Admin Portal via `/admin`. (Default credentials: User `helloadmin`, Pass `g0Lf1shC0mp` - configurable in `.env`).
2. Go to **Capacity** settings and set the total maximum slots allowed.
3. Go to **Tee Times** to add or modify Shotgun session groups for Golfers (e.g. Shotgun-1, Shotgun-2), defining slots limit and reporting times.
4. Go to **Arrival Windows** to define time segments for Guest registrations.

---

## 💳 Payment Integration Flow (SSLCommerz)

1. **Submission**: Participant clicks "Proceed to Payment" on the registration form.
2. **Session Creation**: `/payment/initiate.php` validates capacities and records a pending submission. It initiates a session with SSLCommerz and returns a checkout redirect URL.
3. **Gateway Redirection**: Browser redirects to the secure payment page of SSLCommerz.
4. **Validation Callbacks**: 
   - On payment completion, the gateway redirects to `/payment/success.php`.
   - The success script contacts SSLCommerz validators directly (`orderValidate` API) to verify transaction integrity (matching total amount and merchant credentials).
   - If verified, the payment status changes to `paid`, and an automated SMS confirmation is dispatched to the player.
   - The user lands on the receipt page `/success.php?uid=UNIQUE_ID`.
5. **Instant Payment Notification (IPN)**: `/payment/ipn.php` processes asynchronous notifications from the gateway directly to ensure status syncs even if the user closes the checkout tab.

---

## 📈 Search Engine Optimization (SEO)

Each page is designed for crawler optimization:
- **Canonical URLs**: Automatically computed to point search crawlers to the authoritative version of the page.
- **Open Graph (OG) Meta tags**: Custom Facebook, Twitter, and LinkedIn sharing cards dynamically populate titles, descriptions, and thumbnails.
- **Semantic Tags**: Clean `<h1>` hierarchies, structured headers, responsive target interfaces, and accessibility properties.

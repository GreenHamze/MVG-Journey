<img width="2235" height="1317" alt="BeforeMovieOptions" src="https://github.com/user-attachments/assets/02119aef-57ef-4d5e-ae09-2cf1be30edf3" />[README.md](https://github.com/user-attachments/files/27057051/README.md)
# MVG Cinema Journey Planner — CPEE-Orchestrated Activity Planning

A QR-code-driven journey planner where a [CPEE](https://cpee.org) process orchestrates the decision flow, PHP backend scripts proxy Google Maps APIs and relay QR callbacks, and static HTML pages provide the visualization. Users interact entirely through QR codes scanned with their phone — the UI displays on a TV or large monitor, and every choice is a QR scan.

The system picks up after a colleague's (Alex / ge82bob) cinema and movie selection flow. Alex's system handles movie browsing and cinema selection; this system takes over from there, guiding the user through optional pre-movie activities (supermarkets, restaurants), post-movie activities (bars), and finally rendering a complete MVG transit route with real-time directions.

---

## Screenshots

### 1. Select showtime — scan to pick your time

<img width="2235" height="1317" alt="SelectShowtime" src="https://github.com/user-attachments/assets/702df767-3126-41c1-839a-90480d305e54" />


The system reads Alex's `selection.json` and queries his `movies.db` to pull the movie title, cinema, and available showtimes. Each showtime gets its own QR code. Scanning one sends the time string (e.g., `"16:45"`) back to CPEE, which stores it and advances.

### 2. Before the movie — snack, meal, or skip

<img width="2235" height="1317" alt="BeforeMovieOptions" src="https://github.com/user-attachments/assets/23cdee2e-73d8-4061-ba8c-9a3329e5fe6b" />


Three QR codes. "Grab a Snack" sends `"supermarket"`, "Grab a Meal" sends `"restaurant"`, "Skip" sends `"skip"`. CPEE stores the choice in `data.before_choice` and branches accordingly.

### 3. Pick a supermarket

<img width="2543" height="1313" alt="SupermarketOptions" src="https://github.com/user-attachments/assets/8dd8942e-96f7-4cfc-9fbc-2b8a5078a5e8" />


The Google Places API (New) is queried for supermarkets within 500m of the cinema. Each result is filtered by opening hours — only places open 1.5 hours before showtime are marked "Open." Results are sorted by open/closed status, then by rating. Each place's QR code encodes a JSON payload with name, coordinates, and address so the final route page can plot directions without a second geocoding call.

### 4. Also grab a meal?

<img width="2543" height="1313" alt="MealBeforeMovie" src="https://github.com/user-attachments/assets/4d59a357-0aa4-4563-898b-0d97f5575a66" />


After picking a supermarket, the system offers the complementary activity. The selected place name is shown as confirmation (parsed from the JSON payload). Two QR codes: one for "Grab a Meal" (sends `"restaurant"`), one for "Skip" (sends `"skip"`). If the user had picked a restaurant first, this screen would offer supermarkets instead.

### 5. Pick a restaurant

<img width="2543" height="1313" alt="RestaurantOptions" src="https://github.com/user-attachments/assets/d14765d5-f6f2-4289-88db-030c0ff4e54e" />


Same `places-list.html` page, parameterized with `type=restaurant`. The same filtering and sorting logic applies. Closed restaurants are greyed out but still visible.

### 6. After the movie — drinks or home

<img width="2543" height="1313" alt="AfterMovieOptions" src="https://github.com/user-attachments/assets/9a1f401d-73d2-4bc4-b53b-94b40dd524a8" />


The estimated movie end time is calculated from showtime + duration. Two QR codes: "Grab a Drink" sends `"bar"`, "Head Home" sends `"home"`.

### 7. Pick a bar

<img width="2543" height="1313" alt="NearbyBars" src="https://github.com/user-attachments/assets/2d79c394-1723-41f2-9a1b-09ade72d5ced" />


Bars are filtered by whether they're open *after* the movie ends (showtime + duration), not before. The same `places-list.html` page handles this by checking the `type=bar` parameter and adjusting the time check.

### 8. Your complete journey

<img width="2543" height="1313" alt="FinalJourney" src="https://github.com/user-attachments/assets/11b3861a-0ccb-4202-9dd3-8612f2d60b59" />


The final screen renders a timeline of every stop with real-time MVG transit data from the Google Directions API. Each leg shows the U-Bahn/S-Bahn line, direction, travel time, and number of stops. Walking segments are shown where transit isn't available. A summary box shows total stops, transit legs, and estimated total time.

### 9. Timeout — inactivity protection

If the user stops scanning for 120 seconds, the session ends gracefully with a timeout screen. This is implemented entirely in CPEE via a parallel heartbeat branch (see The CPEE Process below).

---

## Architecture

```
┌─────────────────┐      ┌─────────────────┐      ┌─────────────────┐
│  HTML frontend  │◄────►│   CPEE engine   │◄────►│  PHP backend    │
│  (TV display)   │      │ (orchestration) │      │ (API proxy +    │
│                 │      │                 │      │  QR relay)      │
└────────┬────────┘      └─────────────────┘      └────────┬────────┘
         │ QR scans                                        │
         ▼                                                 ▼
    ┌─────────┐                                   ┌─────────────────┐
    │  Phone  │                                   │  Google Maps    │
    └─────────┘                                   │  + Alex's data  │
                                                  └─────────────────┘
```

Three layers with clear responsibilities:

- **CPEE process** — controls the sequence of screens, decides which page to show based on user choices, manages branching (supermarket vs. restaurant vs. skip), handles the timeout. No business logic of its own.
- **PHP backend** — `send.php` bridges QR scans to CPEE callbacks. `google-api.php` proxies all Google Maps API calls (Directions, Places, Geocoding) to avoid CORS. `get-selection.php` reads Alex's movie data. No state management — CPEE owns all state.
- **HTML frontend** — thin presentation layer. Reads parameters from the URL, fetches data from the backend, renders UI, generates QR codes. Never decides what comes next.

Player input flows: **phone scans QR → `send.php` PUTs value to CPEE callback → CPEE advances → next page loads in frame with new parameters.**

### QR callback flow

1. CPEE displays an HTML page in its frame, injecting a callback URL via `window.name`
2. The page generates QR codes encoding: `send.php?info=VALUE&cb=CALLBACK_URL`
3. User scans a QR code with their phone
4. Phone opens the URL, which hits `send.php`
5. `send.php` performs an HTTP PUT to the CPEE callback URL with the scanned value
6. CPEE receives the value, the waiting task completes, and the flow continues
7. User's phone shows a green "Got it!" confirmation page

---

## The CPEE Process

The process handles five responsibilities:

1. **Movie data retrieval** — auto-fetch Alex's selection (cinema, movie, showtimes, duration)
2. **Showtime selection** — let the user pick a showtime via QR
3. **Before-movie activities** — branching logic for supermarket, restaurant, both, or skip
4. **After-movie activities** — bar or home
5. **Inactivity timeout** — end the session if the user goes idle for 120 seconds

### Process structure

```
Start
  ↓
Init → Select Showtime & Wait           (displays showtimes, waits for QR scan)
  ↓
Fetch Movie Data & Wait                  (auto-fetches JSON, no user interaction)
  ↓
Parse Movie Data (script)                (populates cinema_name, cinema_coords, etc.)
  ↓
Before Options & Wait                    (snack / meal / skip)
  ↓
Parallel (Wait=1)
│
├── Branch 1: MAIN FLOW
│     Exclusive: data.before_choice
│     ├── "supermarket":
│     │     Pick Supermarket & Wait
│     │     Also Options After Supermarket & Wait
│     │     Exclusive: data.also_choice
│     │     ├── "restaurant": Pick Restaurant Second & Wait
│     │     └── default: (skip)
│     ├── "restaurant":
│     │     Pick Restaurant & Wait
│     │     Also Options After Restaurant & Wait
│     │     Exclusive: data.also_choice
│     │     ├── "supermarket": Pick Supermarket Second & Wait
│     │     └── default: (skip)
│     └── default: (skip)
│     After Options & Wait               (bar / home)
│     Exclusive: data.after_choice
│     ├── "bar": Pick Bar & Wait
│     └── default: (skip)
│     Final Route                         (display-only, shows complete journey)
│
└── Branch 2: INACTIVITY HEARTBEAT
      Loop [Time.now.to_i - data.update < 120]
        Powernap service call              (1-second heartbeat)
      Script: data.timed_out = true
      Timeout Screen                       (display-only)
  ↓
End
```

### Why two branches

The Parallel gateway with `Wait=1` means whichever branch finishes first wins, and the other gets cancelled. Two things can end the session:

- **Natural completion** — Branch 1 reaches Final Route and the user sees their journey
- **Inactivity** — Branch 2 detects no user action for 120 seconds

Either outcome renders a final screen. Clean, single exit path.

### The inactivity heartbeat pattern

Branch 2 doesn't use a single 120-second timer. Instead, it loops through 1-second heartbeat calls (to `cpee.org/services/powernap.php`), re-checking `Time.now.to_i - data.update < 120` each iteration. The `data.update` timestamp is refreshed to `Time.now.to_i` in the Finalize of every user-interaction block (Select Showtime, Before Options, Pick Supermarket, Also Options, After Options, Pick Bar, etc.).

This polling-based design avoids two problems:

1. **PHP proxy timeouts** — a single HTTP call longer than ~30 seconds would get killed at the proxy layer
2. **In-flight request cancellation** — CPEE can cleanly cancel a branch between heartbeats, but can't abort a long HTTP call already in flight

### Integration with Alex's flow

Alex's system (ge82bob) handles cinema browsing and movie selection. When the user finishes Alex's flow, it writes to `selection.json` on the shared Lehre server. Our `get-selection.php` reads this file plus Alex's `movies.db` (SQLite) to pull the movie title, cinema name, available showtimes, and duration. This happens automatically via `fetch-movie-data.html` — a page with no QR codes that fetches the data and immediately relays it back to CPEE via the callback. No user interaction needed.

### Key data objects

| Variable | Purpose |
|----------|---------|
| `movie_time` | Selected showtime (e.g., `"16:45"`). Set by Select Showtime scan. |
| `movie_data` | Raw JSON from `get-selection.php`. Parsed by script block. |
| `movie_title` | Movie name from Alex's database. |
| `cinema_name` | Cinema display name (e.g., `"CinemaxX"`). |
| `cinema_coords` | `"lat,lng"` string for routing. |
| `movie_duration` | Duration in minutes. |
| `before_choice` | `"supermarket"`, `"restaurant"`, or `"skip"`. |
| `before_place` | JSON string with name, lat, lng, address of selected place. |
| `also_choice` | Second before-movie choice. |
| `also_place` | Second selected place (JSON). |
| `after_choice` | `"bar"` or `"home"`. |
| `after_place` | Selected bar (JSON). |
| `update` | Unix timestamp of last user activity. Heartbeat polls against this. |
| `timed_out` | Whether the session timed out. |

### Process graph from the CPEE cockpit

<img width="546" height="1140" alt="ProcessGraph" src="https://github.com/user-attachments/assets/81bc46c8-2689-4867-baab-6eaf56fcbbc1" />


The left column is the main flow — Init, Select Showtime, Fetch Movie Data, Before Options, then the exclusive gateways branching on supermarket/restaurant/skip. The middle section handles the "also" options (offering the complementary activity). After the branches merge, After Options and the bar/home decision follow. Final Route sits at the bottom of Branch 1. The right column (with the clock icon and loop) is Branch 2, the inactivity heartbeat.

### CPEE process file

The full process definition (BPMN export) is in [`cpee/Hamze_Prak_Flow_Final.bpmn`](cpee/).

---

## Project Structure

```
mvg-journey/
├── README.md                          ← this file
├── cpee/
│   └── Hamze_Prak_Flow_Final.bpmn     # exported CPEE process
├── frontend/                          ← static HTML pages
│   ├── select-time.html               # showtime selection
│   ├── before-options.html            # pre-movie choices (snack/meal/skip)
│   ├── places-list.html               # dynamic nearby places grid (reused for all types)
│   ├── also-options.html              # secondary activity prompt
│   ├── after-options.html             # post-movie choices (bar/home)
│   ├── final-route.html               # complete journey with MVG transit
│   ├── fetch-movie-data.html          # auto-fetch from Alex's system (no UI)
│   └── timeout.html                   # session timeout display
├── backend/                           ← PHP scripts
│   ├── send.php                       # QR → CPEE callback bridge
│   ├── google-api.php                 # Google Maps API proxy (Directions, Places, Geocoding)
│   └── get-selection.php              # reads Alex's selection.json + movies.db
└── screenshots/
    └── (screenshots shown above)
```

---

## Tech Stack

**Backend**
- PHP 7.4+
- No database — CPEE owns all state; Alex's SQLite DB is read-only
- Three scripts: callback relay, API proxy, data integration

**Frontend**
- Plain HTML, CSS, vanilla JavaScript — no frameworks
- QR code generation via [qrcodejs](https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js) (CDN)
- Single `places-list.html` page reused for supermarkets, restaurants, and bars via URL parameters

**APIs**
- Google Maps Directions API — real-time transit routing
- Google Places API (New) — nearby place search with opening hours
- Google Geocoding API — address-to-coordinate fallback

**Orchestration**
- CPEE (Cloud Process Execution Engine), hosted at [cpee.org](https://cpee.org)
- Uses `cpee.org/out/frames/` frame-display endpoints for rendering HTML in the TV iframe
- Uses `cpee.org/services/powernap.php` for heartbeat polling

---

## Local Setup & Run

### Prerequisites

- PHP 7.4+ with SQLite3 extension
- Google Maps API key with Directions, Places, and Geocoding APIs enabled

### Configuration

1. Set API key in `google-api.php`:
   ```php
   $API_KEY = 'YOUR_GOOGLE_MAPS_API_KEY';
   ```

2. Update file paths in `get-selection.php` for Alex's data:
   ```php
   $selectionPath = '/path/to/selection.json';
   $dbPath = '/path/to/movies.db';
   ```

3. Update base URLs in all frontend files:
   ```javascript
   const API_BASE = "https://your-server.com/qr/google-api.php";
   const SEND_URL = "https://your-server.com/qr/send.php";
   ```

### Running locally

```bash
cd mvg-journey
php -S localhost:8000
```

Note: QR callback functionality requires a running CPEE instance. Pages can be previewed directly but QR codes will show "ERROR: missing callback" without CPEE injecting the callback URL via `window.name`.

---

## REST API Reference

### send.php — QR callback relay

Receives QR scans and relays data to CPEE. The user's phone opens this URL; it PUTs the value to the CPEE callback and shows a confirmation page.

| Method | Endpoint | Params | Response |
|--------|----------|--------|----------|
| GET/POST | `/send.php` | `info` (string), `cb` (URL) | HTML: green "Got it!" or red error |

### google-api.php — Google Maps proxy

Centralized proxy. Handles CORS headers and API key injection.

| Action | Params | Returns |
|--------|--------|---------|
| `route` | `origin`, `destination` | Google Directions API response (transit mode) |
| `places` | `location` (lat,lng), `type`, `radius` | Google Places API response with names, coords, hours, ratings |
| `geocode` | `address` | Google Geocoding API response |

### get-selection.php — Alex integration

Reads Alex's movie selection and returns combined data. No parameters.

```json
{
  "movie_title": "Der Astronaut - Project Hail Mary",
  "cinema": "CinemaxX",
  "cinema_key": "cinemaxx",
  "cinema_coords": "48.1351,11.5623",
  "showtimes": ["16:45", "19:45"],
  "duration": 157
}
```

Data sources:
- `/srv/gruppe/students/ge82bob/public_html/selection.json` — current movie selection
- `/srv/gruppe/students/ge82bob/public_html/db/movies.db` — SQLite movie database

Cinema coordinates are hardcoded in the script (17 Munich cinemas mapped).

---

## Infrastructure Notes

### CPEE frame rendering

The CPEE frame dimensions (170×200) are internal scaling units, not pixels. The actual rendering on the TV is ~2560×1352 pixels. Pages are designed for large-screen viewing with oversized QR codes and fonts readable from 2–3 meters.

### CORS

All Google API calls are proxied through `google-api.php` with `Access-Control-Allow-Origin: *`. Direct browser-to-Google requests would fail due to CORS restrictions.

### Cache busting

CPEE may cache iframe content aggressively. Append `?v=N` or `&v=N` to page URLs in CPEE task configurations when deploying updates. Use incognito mode for testing.

### Opening hours logic

- **Before-movie places** (supermarket, restaurant): checked at `showtime - 1.5 hours`
- **After-movie places** (bar): checked at `showtime + duration`

Places that are closed at the relevant time are greyed out but still displayed (the user might know something the API doesn't).

### URL expression gotchas

CPEE URL expressions (the `!"..."` syntax) do not support `encodeURIComponent()`. Use fallback expressions like `(data.variable || 'none')` instead to handle empty values.

---

## Deployment

**Backend** — Copy `backend/` contents to the web server's public directory (e.g., Apache's `public_html/qr/`).

**Frontend** — Copy `frontend/` contents to the same directory. All files live side by side.

**CPEE** — Import the BPMN file from `cpee/` into the CPEE cockpit. Configure the `frames_display` endpoint to point to `https-put://cpee.org/out/frames/`. Configure `powernap` to point to `https-post://cpee.org/services/powernap.php`.


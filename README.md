# MVG Cinema Journey Planner

A QR-code-only journey planning system for Munich movie-goers, built as part of the TUM CPEE Practical Course (Winter Semester 2025). The application is displayed on a TV screen and users interact exclusively by scanning QR codes with their phones — no tapping, no typing.

Starting from Garching Forschungszentrum, the system helps users plan their entire evening: pick a showtime, optionally stop for snacks or a meal before the movie, watch the film, optionally grab drinks afterward, and see their complete MVG transit route with real-time directions.

This project integrates with a colleague's (Alex / ge82bob) cinema and movie selection flow. Alex's system handles movie browsing and cinema selection; this system picks up from there and handles activity planning and route generation.

---

## User Journey

The complete flow walks the user through their evening, screen by screen. Each screen is displayed on a TV and presents QR codes for the user to scan.

### Step 1 — Select Showtime

The system reads Alex's movie selection and presents available showtimes. The user scans the QR code for their preferred time.

![Select Showtime](screenshots/01_select_showtime.png)

*CPEE Block: `Select Showtime` — reads from Alex's `selection.json` and `movies.db`, displays available times.*

### Step 2 — Before the Movie

The user chooses what to do before the movie: grab snacks from a supermarket, eat at a restaurant, or skip straight to the cinema.

![Before Movie Options](screenshots/02_before_movie.png)

*CPEE Block: `Before Options` — waits for QR scan, stores choice in `data.before_choice`.*

### Step 3 — Pick a Place (Supermarkets)

If the user chose "Grab a Snack," the system queries the Google Places API for nearby supermarkets, filters by opening hours (must be open 1.5 hours before the movie), and displays them sorted by rating. Each place gets its own QR code.

![Nearby Supermarkets](screenshots/03_nearby_supermarkets.png)

*CPEE Block: `Pick Supermarket First` — sends JSON with place name, coordinates, and address back to CPEE.*

### Step 4 — Also Grab a Meal?

After picking a supermarket, the system asks if the user also wants to eat. The selected place name is shown as confirmation.

![Also Grab a Meal](screenshots/04_also_grab_meal.png)

*CPEE Block: `Also Options After Supermarket` — offers the complementary activity (restaurant if they picked supermarket, or vice versa).*

### Step 5 — Pick a Restaurant

If the user wants a meal too, nearby restaurants are shown with the same filtering and rating logic.

![Nearby Restaurants](screenshots/05_nearby_restaurants.png)

*CPEE Block: `Pick Restaurant Second` — same places-list page, parameterized with `type=restaurant`.*

### Step 6 — After the Movie

The user decides what to do after the film: grab drinks at a bar, or head home.

![After Movie Options](screenshots/06_after_movie.png)

*CPEE Block: `After Options` — waits for QR scan, stores choice in `data.after_choice`.*

### Step 7 — Pick a Bar

If the user chose drinks, nearby bars are shown. Opening hours are checked against the estimated movie end time (showtime + duration).

![Nearby Bars](screenshots/07_nearby_bars.png)

*CPEE Block: `Pick Bar` — filters bars open after the movie ends.*

### Step 8 — Your Complete Journey

The final screen shows the complete evening plan as a timeline with real-time MVG transit information from the Google Directions API: U-Bahn lines, travel times, number of stops, and walking segments.

![Final Journey](screenshots/08_final_route.png)

*CPEE Block: `Final Route` — display-only page, calls Google Directions API for each leg of the journey.*

---

## CPEE Process

The workflow is orchestrated by the CPEE (Cloud Process Execution Engine). Below is the process graph from the CPEE cockpit.

![CPEE Process Graph](screenshots/09_cpee_process.png)

### Process Structure

```
START
  │
  ▼
Init → Select Showtime → Fetch Movie Data → Parse Movie Data (script)
  │
  ▼
Before Options ──────────────────────────────────────────────┐
  │                                                          │
  ├─[supermarket]─→ Pick Supermarket → Also Options          │
  │                                      ├─[restaurant]─→ Pick Restaurant
  │                                      └─[skip]────────────┤
  │                                                          │
  ├─[restaurant]──→ Pick Restaurant → Also Options           │
  │                                      ├─[supermarket]─→ Pick Supermarket
  │                                      └─[skip]────────────┤
  │                                                          │
  └─[skip]───────────────────────────────────────────────────┤
                                                             │
                              ┌───────────── PARALLEL (Wait=1) ─────────────┐
                              │                                             │
                              │  Branch 1 (main flow):                      │  Branch 2 (watchdog):
                              │  After Options                              │  Loop while active:
                              │    ├─[bar]─→ Pick Bar ──┐                   │    Powernap (1s)
                              │    └─[home]─────────────┤                   │  On timeout:
                              │  Final Route             │                   │    Set timed_out = true
                              │                          │                   │    Show Timeout Screen
                              └──────────────────────────┘                   └──────────────────────┘
                                                             │
                                                            END
```

### Timeout Pattern

The entire main flow runs inside a parallel gateway (`Wait=1`) alongside an inactivity watchdog. Every user-facing block refreshes `data.update = Time.now.to_i` in its Finalize script. Branch 2 loops every second via CPEE's `powernap.php` service, checking if `Time.now.to_i - data.update < 120`. If the user is inactive for 2 minutes, the loop exits, sets `data.timed_out = true`, and shows a timeout screen. Since `Wait=1`, whichever branch finishes first cancels the other.

### Key Data Objects

| Variable | Type | Description |
|----------|------|-------------|
| `movie_time` | String | Selected showtime (e.g., "16:45") |
| `movie_title` | String | Movie name from Alex's system |
| `cinema_name` | String | Cinema display name |
| `cinema_coords` | String | "lat,lng" for routing |
| `movie_duration` | Integer | Duration in minutes |
| `movie_data` | String | Raw JSON from get-selection.php |
| `before_choice` | String | "supermarket", "restaurant", or "skip" |
| `before_place` | String | JSON with name, lat, lng, address |
| `also_choice` | String | Second before-movie choice |
| `also_place` | String | Second selected place (JSON) |
| `after_choice` | String | "bar" or "home" |
| `after_place` | String | Selected bar (JSON) |
| `update` | Integer | Timestamp of last user interaction |
| `timed_out` | Boolean | Whether session timed out |

### Integration with Alex's Flow

Alex's system (ge82bob) handles cinema browsing and movie selection. When the user finishes Alex's flow, it writes to `selection.json` on the shared Lehre server. Our system reads this file plus Alex's `movies.db` (SQLite) to get the movie title, cinema, showtimes, and duration. This happens automatically via `fetch-movie-data.html` which calls `get-selection.php` and sends the data back to CPEE without any user interaction.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        TV SCREEN                                │
│   ┌─────────────────────────────────────────────────────────┐   │
│   │              CPEE Frame (iframe)                        │   │
│   │   Displays HTML pages with QR codes                     │   │
│   └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
         │ displays                              ▲ scans QR
         ▼                                       │
┌─────────────────┐    callback (PUT)    ┌───────────────┐
│   CPEE Engine    │◄────────────────────│  User's Phone  │
│                  │                     │  (QR Scanner)   │
│  - Orchestrates  │    QR contains:     └───────────────┘
│    workflow       │    send.php?info=X         │
│  - Manages state │    &cb=CALLBACK_URL         │
│  - Decision      │                             │
│    gateways      │                             ▼
└────────┬─────────┘                     ┌───────────────┐
         │                               │   send.php     │
         │ loads pages                   │   (relay)      │
         ▼                               └───────┬───────┘
┌─────────────────────────────────────────────────┘
│              Lehre Server
│   ┌──────────────┐  ┌───────────────────┐  ┌─────────────────┐
│   │  HTML Pages   │  │  google-api.php   │  │ get-selection   │
│   │  (frontend)   │  │  (API proxy)      │  │ .php            │
│   └──────────────┘  └───────┬───────────┘  └────────┬────────┘
│                             │                       │
│                             ▼                       ▼
│                     ┌───────────────┐    ┌─────────────────────┐
│                     │ Google Maps   │    │ Alex's data         │
│                     │ - Directions  │    │ - selection.json    │
│                     │ - Places      │    │ - movies.db         │
│                     │ - Geocoding   │    └─────────────────────┘
│                     └───────────────┘
└─────────────────────────────────────────────────────────────────
```

### QR Code Communication Flow

1. CPEE displays an HTML page in its frame and injects a callback URL into `window.name`
2. The page generates QR codes encoding: `send.php?info=VALUE&cb=CALLBACK_URL`
3. User scans a QR code with their phone
4. Phone opens the URL, which hits `send.php`
5. `send.php` performs an HTTP PUT to the CPEE callback URL with the scanned value
6. CPEE receives the value, the waiting task completes, and the flow continues
7. User's phone shows a green "Got it!" confirmation page

---

## Project Structure

```
mvg-journey/
├── README.md
├── cpee/
│   └── Hamze_Prak_Flow_Final.bpmn       # Exported CPEE process
├── frontend/
│   ├── before-options.html               # Pre-movie choices (snack/meal/skip)
│   ├── after-options.html                # Post-movie choices (bar/home)
│   ├── also-options.html                 # Secondary activity prompt
│   ├── places-list.html                  # Dynamic nearby places grid
│   ├── select-time.html                  # Showtime selection
│   ├── final-route.html                  # Complete journey with transit info
│   ├── fetch-movie-data.html             # Auto-fetch from Alex's system
│   └── timeout.html                      # Session timeout display
├── backend/
│   ├── send.php                          # QR callback relay to CPEE
│   ├── google-api.php                    # Google Maps API proxy
│   └── get-selection.php                 # Alex integration (reads selection.json + movies.db)
└── screenshots/
    ├── 01_select_showtime.png
    ├── 02_before_movie.png
    ├── 03_nearby_supermarkets.png
    ├── 04_also_grab_meal.png
    ├── 05_nearby_restaurants.png
    ├── 06_after_movie.png
    ├── 07_nearby_bars.png
    ├── 08_final_route.png
    └── 09_cpee_process.png
```

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | Vanilla HTML5, CSS3, JavaScript (ES6+) |
| Backend | PHP 7.4+ |
| APIs | Google Maps Directions API, Google Places API (New), Google Geocoding API |
| Orchestration | CPEE (Cloud Process Execution Engine) |
| QR Generation | qrcodejs library (CDN) |
| Database | SQLite (read-only access to partner's movies.db) |
| Hosting | TUM Lehre server |

---

## API Reference

### send.php — QR Callback Relay

Receives QR scans and relays data to CPEE.

| Method | Parameters | Behavior |
|--------|-----------|----------|
| GET/POST | `info` (string), `cb` (callback URL) | PUTs `info` to `cb`, returns styled confirmation page |

### google-api.php — Google Maps Proxy

Centralized proxy handling CORS and API key management.

| Action | Parameters | Returns |
|--------|-----------|---------|
| `route` | `origin`, `destination` | Transit directions (lines, times, stops) |
| `places` | `location` (lat,lng), `type`, `radius` | Nearby places with hours, ratings, coordinates |
| `geocode` | `address` | Coordinates for an address |

### get-selection.php — Alex Integration

Reads movie selection and returns combined data. No parameters needed.

Returns: `{ movie_title, cinema, cinema_key, cinema_coords, showtimes[], duration }`

---

## Local Setup

### Prerequisites

- PHP 7.4+ with SQLite3 extension
- Google Maps API key (Directions, Places, Geocoding enabled)

### Configuration

1. Set API key in `google-api.php`
2. Update file paths in `get-selection.php` for `selection.json` and `movies.db`
3. Update base URLs in all frontend files (`API_BASE`, `SEND_URL`)

### Running

```bash
cd mvg-journey
php -S localhost:8000
```

Note: QR callback functionality requires a running CPEE instance. Pages can be previewed directly but QR codes will show "ERROR: missing callback" without CPEE.

---

## Infrastructure Notes

- **CPEE Frame**: Frame units (170×200) are internal CPEE scaling units, not pixels. Actual rendering on TV is ~2560×1352px.
- **CORS**: All Google API calls are proxied through `google-api.php` with `Access-Control-Allow-Origin: *`.
- **Cache Busting**: Append `?v=N` or `&v=N` to page URLs in CPEE when deploying updates.
- **Opening Hours**: Before-movie places are checked at `movie_time - 1.5h`; after-movie bars at `movie_time + duration`.

---

## Authors

- **Hamze Alzamkan** (ga53muj) — Activity planning, route generation, CPEE orchestration
- **Alex** (ge82bob) — Cinema browsing, movie selection, showtime database

TUM CPEE Practical Course, Winter Semester 2025

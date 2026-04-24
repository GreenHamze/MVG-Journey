MVG Cinema Journey Planner — CPEE-Orchestrated Activity Planning
A QR-code-driven journey planner where a CPEE process orchestrates the decision flow, PHP backend scripts proxy Google Maps APIs and relay QR callbacks, and static HTML pages provide the visualization. Users interact entirely through QR codes scanned with their phone — the UI displays on a TV or large monitor, and every choice is a QR scan.
The system picks up after a colleague's (Alex / ge82bob) cinema and movie selection flow. Alex's system handles movie browsing and cinema selection; this system takes over from there, guiding the user through optional pre-movie activities (supermarkets, restaurants), post-movie activities (bars), and finally rendering a complete MVG transit route with real-time directions.

<img width="2235" height="1317" alt="SelectShowtime" src="https://github.com/user-attachments/assets/ffa1e69d-c868-43c1-a9f4-27afed4d121f" /># MVG Cinema Journey Planner

A QR-code-only journey planning system for Munich movie-goers, built as part of the TUM CPEE Practical Course (Winter Semester 2025). The application is displayed on a TV screen and users interact exclusively by scanning QR codes with their phones — no tapping, no typing.

Starting from Garching Forschungszentrum, the system helps users plan their entire evening: pick a showtime, optionally stop for snacks or a meal before the movie, watch the film, optionally grab drinks afterward, and see their complete MVG transit route with real-time directions.

This project integrates with a colleague's (Alex / ge82bob) cinema and movie selection flow. Alex's system handles movie browsing and cinema selection; this system picks up from there and handles activity planning and route generation.

---

## User Journey

The complete flow walks the user through their evening, screen by screen. Each screen is displayed on a TV and presents QR codes for the user to scan.

### Step 1 — Select Showtime

The system reads Alex's movie selection and presents available showtimes. The user scans the QR code for their preferred time.

<img width="2235" height="1317" alt="SelectShowtime" src="https://github.com/user-attachments/assets/f5bbb87b-0a17-4262-b9f7-b785eef76dd7" />


*CPEE Block: `Select Showtime` — reads from Alex's `selection.json` and `movies.db`, displays available times.*

### Step 2 — Before the Movie

The user chooses what to do before the movie: grab snacks from a supermarket, eat at a restaurant, or skip straight to the cinema.

<img width="2235" height="1317" alt="BeforeMovieOptions" src="https://github.com/user-attachments/assets/f829d75e-f549-43fe-8f7c-575ec964402b" />


*CPEE Block: `Before Options` — waits for QR scan, stores choice in `data.before_choice`.*

### Step 3 — Pick a Place (Supermarkets)

If the user chose "Grab a Snack," the system queries the Google Places API for nearby supermarkets, filters by opening hours (must be open 1.5 hours before the movie), and displays them sorted by rating. Each place gets its own QR code.

<img width="2543" height="1313" alt="SupermarketOptions" src="https://github.com/user-attachments/assets/8607d739-de3a-4524-9eaa-15781fe880bb" />


*CPEE Block: `Pick Supermarket First` — sends JSON with place name, coordinates, and address back to CPEE.*

### Step 4 — Also Grab a Meal?

After picking a supermarket, the system asks if the user also wants to eat. The selected place name is shown as confirmation.

<img width="2543" height="1313" alt="MealBeforeMovie" src="https://github.com/user-attachments/assets/13948864-73f0-4dd6-89fd-c9b291e17426" />


*CPEE Block: `Also Options After Supermarket` — offers the complementary activity (restaurant if they picked supermarket, or vice versa).*

### Step 5 — Pick a Restaurant

If the user wants a meal too, nearby restaurants are shown with the same filtering and rating logic.

<img width="2543" height="1313" alt="RestaurantOptions" src="https://github.com/user-attachments/assets/84407b4e-ce2e-465a-a564-d797c408f0f1" />


*CPEE Block: `Pick Restaurant Second` — same places-list page, parameterized with `type=restaurant`.*

### Step 6 — After the Movie

The user decides what to do after the film: grab drinks at a bar, or head home.

<img width="2543" height="1313" alt="AfterMovieOptions" src="https://github.com/user-attachments/assets/51d11389-c28c-451f-ad2a-09938b5950d8" />


*CPEE Block: `After Options` — waits for QR scan, stores choice in `data.after_choice`.*

### Step 7 — Pick a Bar

If the user chose drinks, nearby bars are shown. Opening hours are checked against the estimated movie end time (showtime + duration).

<img width="2543" height="1313" alt="NearbyBars" src="https://github.com/user-attachments/assets/b583fcce-6e39-4253-a066-5eba00aee15e" />


*CPEE Block: `Pick Bar` — filters bars open after the movie ends.*

### Step 8 — Your Complete Journey

The final screen shows the complete evening plan as a timeline with real-time MVG transit information from the Google Directions API: U-Bahn lines, travel times, number of stops, and walking segments.

<img width="2543" height="1313" alt="FinalJourney" src="https://github.com/user-attachments/assets/3578bd70-a181-4baf-8893-475f307aa456" />


*CPEE Block: `Final Route` — display-only page, calls Google Directions API for each leg of the journey.*

---

## CPEE Process

The workflow is orchestrated by the CPEE (Cloud Process Execution Engine). Below is the process graph from the CPEE cockpit.

<img width="546" height="1140" alt="ProcessGraph" src="https://github.com/user-attachments/assets/44c4ad3c-1682-4e08-ab1e-c68bd59d1e3e" />


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


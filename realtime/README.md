# Pickleball realtime service

Live court-slot updates and chat delivery via Socket.io.

## Run

```bash
cd realtime
npm install
npm start
```

Default: `http://127.0.0.1:3001`

Optional env:

- `PB_REALTIME_PORT` (default `3001`)
- `PB_REALTIME_SECRET` (must match `admin/payment_config.php`)

PHP publishes to `POST /publish` after bookings/chat. Venue pages and chat UIs connect with Socket.io client; if the service is down, slot grids fall back to polling.

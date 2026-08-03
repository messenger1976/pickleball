const express = require('express');
const http = require('http');
const cors = require('cors');
const { Server } = require('socket.io');

const PORT = process.env.PB_REALTIME_PORT || 3001;
const SECRET = process.env.PB_REALTIME_SECRET || 'pickleball-realtime-dev';

const app = express();
app.use(cors());
app.use(express.json({ limit: '256kb' }));

const server = http.createServer(app);
const io = new Server(server, {
  cors: { origin: '*', methods: ['GET', 'POST'] },
});

io.on('connection', (socket) => {
  socket.on('join', (rooms) => {
    const list = Array.isArray(rooms) ? rooms : [rooms];
    list.filter(Boolean).forEach((room) => socket.join(String(room)));
  });
});

app.get('/health', (_req, res) => {
  res.json({ ok: true, service: 'pickleball-realtime' });
});

app.post('/publish', (req, res) => {
  if (!req.body || req.body.secret !== SECRET) {
    return res.status(401).json({ ok: false });
  }
  const event = req.body.event || 'message';
  const payload = req.body.payload || {};
  const rooms = [];
  if (payload.business_id) rooms.push('biz:' + payload.business_id);
  if (payload.court_id) rooms.push('court:' + payload.court_id);
  if (payload.thread_id) rooms.push('thread:' + payload.thread_id);
  if (payload.customers_id) rooms.push('customer:' + payload.customers_id);
  if (rooms.length === 0) rooms.push('global');
  rooms.forEach((room) => io.to(room).emit(event, payload));
  res.json({ ok: true, rooms });
});

server.listen(PORT, () => {
  console.log('Pickleball realtime listening on ' + PORT);
});

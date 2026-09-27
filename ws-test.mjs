/**
 * Realtime smoke test — verifies the exact message flow the Flutter app uses:
 *
 *   1. POST /api/mobile/login                → token + user.id
 *   2. Open ws://127.0.0.1:8080/app/{key}    → pusher:connection_established
 *   3. POST /api/broadcasting/auth           → auth signature
 *   4. pusher:subscribe private-user.{id}    → subscription_succeeded
 *   5. Trigger a notification (admin action) → notification.created arrives
 *
 * Uses Node 22+ built-in fetch and WebSocket. Run: node ws-test.mjs
 */
const API = 'http://127.0.0.1:8000/api';
const REVERB_URL = 'ws://127.0.0.1:8080/app/tmc-equipment-local';

const post = (url, body, token) =>
  fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify(body),
  }).then((r) => r.json());

// ---- 1. Get a student + token ---------------------------------------------
const login = await post(`${API}/mobile/login`, { login: 'smoketest1', password: 'newpassword123' });
if (!login.token) {
  console.error('❌ login failed:', login.message);
  process.exit(1);
}
const userId = login.user?.id;
console.log(`✅ logged in as user ${userId}`);

// ---- 2. Open the WebSocket --------------------------------------------------
const ws = new WebSocket(REVERB_URL);
let socketId = null;
let authed = false;

const timeout = setTimeout(() => {
  console.error('❌ TIMEOUT — no notification.created within 15s');
  console.error('   Check: BROADCAST_CONNECTION=reverb in .env, Reverb running, observers registered.');
  process.exit(1);
}, 15000);

ws.addEventListener('open', () => console.log('✅ websocket open'));
ws.addEventListener('error', (e) => console.error('❌ ws error:', e.message || 'socket error'));

ws.addEventListener('message', async (ev) => {
  const msg = JSON.parse(typeof ev.data === 'string' ? ev.data : await ev.data.text());
  const { event, data, channel } = msg;

  if (event === 'pusher:connection_established') {
    socketId = JSON.parse(data).socket_id;
    console.log(`✅ connection established (socket ${socketId})`);

    // ---- 3. Authorize the private channel like the app does ----------------
    const auth = await post(`${API}/broadcasting/auth`, {
      socket_id: socketId,
      channel_name: `private-user.${userId}`,
    }, login.token);

    if (!auth.auth) {
      console.error('❌ broadcasting/auth failed:', auth);
      process.exit(1);
    }
    console.log('✅ broadcasting/auth returned a signature');

    // ---- 4. Subscribe -------------------------------------------------------
    ws.send(JSON.stringify({
      event: 'pusher:subscribe',
      data: { auth: auth.auth, channel: `private-user.${userId}` },
    }));
    return;
  }

  if (event === 'pusher_internal:subscription_succeeded') {
    authed = true;
    console.log(`✅ subscribed to ${channel}`);

    // ---- 5. Trigger a real notification through the admin API --------------
    const adminLogin = await post(`${API}/login`, { login: 'admin', password: 'password123' });
    const res = await post(`${API}/admin/notifications`, {
      user_id: userId,
      title: 'Realtime Test',
      message: 'If you can read this on the socket, mobile realtime works.',
      type: 'System',
    }, adminLogin.token);
    console.log(res.id ? '✅ notification created via admin API' : `❌ failed to create notification: ${JSON.stringify(res)}`);
    return;
  }

  if (event?.endsWith('notification.created')) {
    console.log(`🎉 RECEIVED notification.created → ${JSON.stringify(data)}`);
    clearTimeout(timeout);
    console.log('\n✅ REALTIME LOOP VERIFIED END-TO-END');
    ws.close();
    process.exit(0);
  }
});

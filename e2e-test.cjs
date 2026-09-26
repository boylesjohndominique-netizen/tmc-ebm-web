// Temporary end-to-end realtime test: connect as a real client, authorize
// the private-staff channel with a Sanctum token, and wait for a broadcast.
const Pusher = require('./frontend/node_modules/pusher-js/dist/node/pusher.js');

const token = process.argv[2];
if (!token) { console.log('NO_TOKEN_ARG'); process.exit(1); }

const pusher = new Pusher('tmc-equipment-local', {
  cluster: 'mt1', // required by the node build; unused with custom wsHost
  wsHost: '127.0.0.1',
  wsPort: 8080,
  forceTLS: false,
  enabledTransports: ['ws'],
  disableStats: true,
  authEndpoint: 'http://127.0.0.1:8000/api/broadcasting/auth',
  auth: { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } },
});

const channel = pusher.subscribe('private-staff');

channel.bind('pusher:subscription_error', (err) => {
  console.log('SUBSCRIPTION_ERROR:', JSON.stringify(err).slice(0, 200));
  process.exit(1);
});
channel.bind('pusher:subscription_succeeded', () => console.log('SUBSCRIBED_OK'));
channel.bind('changed', (data) => { // raw wire name (Echo's ".changed" → "changed")
  console.log('EVENT_RECEIVED:', JSON.stringify(data));
  process.exit(0);
});

setTimeout(() => { console.log('TIMEOUT_NO_EVENT'); process.exit(1); }, 25000);

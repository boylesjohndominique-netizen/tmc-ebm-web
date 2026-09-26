// Temporary smoke test: verify Reverb accepts a Pusher-protocol handshake.
const ws = new WebSocket('ws://127.0.0.1:8080/app/tmc-equipment-local');

ws.onmessage = (e) => {
  console.log('WS_MSG:', String(e.data).slice(0, 120));
  ws.close();
  process.exit(0);
};
ws.onerror = () => {
  console.log('WS_ERROR');
  process.exit(1);
};
setTimeout(() => {
  console.log('WS_TIMEOUT');
  process.exit(1);
}, 6000);

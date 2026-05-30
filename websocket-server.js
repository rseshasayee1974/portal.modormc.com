const { WebSocketServer, WebSocket } = require('ws');
const http = require('http');

const PORT = process.env.WS_PORT || 6001;

const server = http.createServer((req, res) => {
    // Set CORS headers
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'POST, GET, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

    if (req.method === 'OPTIONS') {
        res.writeHead(204);
        res.end();
        return;
    }

    if (req.method === 'POST' && req.url === '/broadcast') {
        let body = '';
        req.on('data', chunk => {
            body += chunk.toString();
        });
        req.on('end', () => {
            try {
                const payload = JSON.parse(body);
                const count = broadcast(payload.channel, payload.data);
                res.writeHead(200, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ success: true, clients_notified: count }));
            } catch (err) {
                res.writeHead(400, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ success: false, error: 'Invalid JSON payload' }));
            }
        });
    } else {
        res.writeHead(404);
        res.end();
    }
});

const wss = new WebSocketServer({ server });

wss.on('connection', (ws) => {
    console.log(`[WebSocket] Client connected. Total clients: ${wss.clients.size}`);
    
    ws.on('close', () => {
        console.log(`[WebSocket] Client disconnected. Total clients: ${wss.clients.size}`);
    });
});

function broadcast(channel, data) {
    let count = 0;
    wss.clients.forEach(client => {
        if (client.readyState === WebSocket.OPEN) {
            client.send(JSON.stringify({ channel, data }));
            count++;
        }
    });
    console.log(`[WebSocket] Broadcasted to ${count} client(s) on channel: ${channel}`);
    return count;
}

server.listen(PORT, () => {
    console.log(`[WebSocket] Server is running on port ${PORT}`);
});

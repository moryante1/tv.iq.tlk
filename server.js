const WebSocket = require('ws');
const server = new WebSocket.Server({ port: 8080 });

let clients = 0;

server.on('connection', (ws) => {
    clients++;
    broadcastClientCount();

    ws.on('close', () => {
        clients--;
        broadcastClientCount();
    });
});

function broadcastClientCount() {
    server.clients.forEach((client) => {
        if (client.readyState === WebSocket.OPEN) {
            client.send(JSON.stringify({ clients }));
        }
    });
}

console.log("WebSocket server running on ws://localhost:8080");

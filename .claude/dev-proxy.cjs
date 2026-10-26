// Dev-only proxy: accepts browser keep-alive/idle sockets so PHP's single-threaded
// built-in server only ever sees complete requests.
const http = require('http');
const agent = new http.Agent({ keepAlive: false, maxSockets: 1 });
http.createServer((req, res) => {
  const up = http.request({ host: '127.0.0.1', port: 8020, path: req.url, method: req.method, headers: { ...req.headers, connection: 'close' }, agent }, (r) => {
    res.writeHead(r.statusCode, r.headers);
    r.pipe(res);
  });
  up.on('error', (e) => { res.writeHead(502); res.end(String(e)); });
  req.pipe(up);
}).listen(8010, '127.0.0.1');

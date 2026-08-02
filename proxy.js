const fs = require('node:fs');
const http = require('node:http');
const https = require('node:https');
const os = require('node:os');
const path = require('node:path');

const php_port = Number(process.env.PORT || 4000);
const tls_port = Number(process.env.TLS_PORT || 4443);
const tls_data = path.join(process.env.XDG_STATE_HOME || path.join(os.homedir(), ".local/state"), 'everything/tls');

const tls_options = {
  key: fs.readFileSync(path.join(tls_data, 'localhost.key')),
  cert: fs.readFileSync(path.join(tls_data, 'localhost.crt')),
};

const handler = (request, response) => {
  if(request.url == '/.well-known/caldav') {
    response.writeHead(301, {location: `https://${request.headers.host}/caldav/`});
    response.end();
    return;
  }

  if(request.url == '/.well-known/carddav') {
    response.writeHead(301, {location: `https://${request.headers.host}/carddav/`});
    response.end();
    return;
  }

  // Apple's port-less CardDAV auto-discovery probes /principals/ on the
  // legacy port 8843; the CardDAV root answers the same questions.
  if(request.url == '/principals/' || request.url == '/principals') {
    request.url = '/carddav/';
  }

  const upstream = http.request({
    hostname: '127.0.0.1',
    port: php_port,
    method: request.method,
    path: request.url,
    headers: {
      ...request.headers,
      host: `localhost:${php_port}`,
      'x-forwarded-proto': 'https',
    },
  }, (upstream_response) => {
    response.writeHead(upstream_response.statusCode, upstream_response.headers)
    upstream_response.pipe(response)
  })

  upstream.on('error', (error) => {
    response.writeHead(502, {'content-type': 'text/plain'});
    response.end(`Bad gateway: ${error.message}\n`);
  });
  
  request.pipe(upstream);
};

const server = https.createServer(tls_options, handler);
const legacy = https.createServer(tls_options, handler);

// No host: binds dual-stack, so localhost resolving to ::1 (as macOS
// system services do) reaches the proxy too. 8843 is the legacy CardDAV
// TLS port that Apple's auto-discovery probes when it ignores the
// configured port (macOS 26 does).
server.listen(tls_port, () => {
  console.log(`Node ${process.version.slice(1)} Proxy Server (https://[::]:${tls_port}) started`)
});
legacy.listen(8843, () => {
  console.log(`Node Discovery Server (https://[::]:8843) started`)
});

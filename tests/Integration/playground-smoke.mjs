import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createServer } from 'node:net';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { runCLI } from '@wp-playground/cli';

const testDirectory = dirname(fileURLToPath(import.meta.url));
const projectRoot = resolve(testDirectory, '../..');
const fixtureDirectory = join(testDirectory, 'fixtures/mu-plugins');
const withElementor = process.argv.includes('--elementor');
const port = await getFreePort();
const blueprint = withElementor
  ? JSON.parse(
      await readFile(join(testDirectory, 'blueprint-elementor.json'), 'utf8')
    )
  : undefined;

const playground = await runCLI({
  command: 'server',
  autoMount: projectRoot,
  mount: [
    {
      hostPath: fixtureDirectory,
      vfsPath: '/wordpress/wp-content/mu-plugins',
    },
  ],
  port,
  php: '8.3',
  wp: 'latest',
  verbosity: 'quiet',
  blueprint,
});
const baseUrl = playground.serverUrl.replace('127.0.0.1', 'localhost');

try {
  const probe = await requestJson('/wp-json/wpdsac-test/v1/probe');
  assert.equal(probe.response.status, 200, 'The runtime probe must respond');

  const expectedProbe = {
    plugin_active: true,
    plugin_loaded: true,
    plugin_version: '0.5.4',
    db_version: '3',
    rate_limit_table: true,
    request_lock_table: true,
    knowledge_table: true,
    settings_non_autoloaded: true,
    shortcode_registered: true,
    shortcode_rendered: true,
    shortcode_escaped: true,
    global_widget_rendered: true,
    appearance_rendered: true,
    appearance_positioned: true,
    appearance_sanitized: true,
    admin_preview_assets: true,
    knowledge_indexed: true,
    knowledge_retrieved: true,
    knowledge_augmented: true,
    faq_registered: true,
    faq_indexed: true,
    elementor_loaded: withElementor,
    elementor_widget_registered: withElementor,
  };

  for (const [key, expected] of Object.entries(expectedProbe)) {
    assert.equal(
      probe.body[key],
      expected,
      `Unexpected probe value for ${key}: ${JSON.stringify(probe.body)}`
    );
  }

  if (withElementor) {
    assert.equal(
      typeof probe.body.elementor_frontend_url,
      'string',
      'The Elementor probe must publish a frontend fixture page'
    );

    const frontendUrl = probe.body.elementor_frontend_url.replace(
      '127.0.0.1',
      'localhost'
    );
    const frontend = await fetch(frontendUrl, {
      signal: AbortSignal.timeout(15000),
    });
    const frontendHtml = await frontend.text();

    assert.equal(frontend.status, 200, 'The Elementor page must be public');
    assert.match(frontendHtml, /data-wpdsac-chat/);
    assert.match(frontendHtml, /Elementor Smoke &amp; Test/);
    assert.doesNotMatch(frontendHtml, /<script>alert\(1\)<\/script>/i);
    assert.match(frontendHtml, /assets\/build\/chat\.css\?ver=/);
    assert.match(frontendHtml, /assets\/build\/chat\.js\?ver=/);
    assert.match(frontendHtml, /wpdsacChatConfig/);
  }

  const session = await requestJson('/wp-json/wp-ds-aichatbot/v1/session', {
    method: 'POST',
  });
  assert.equal(session.response.status, 201, 'Session endpoint must create a session');
  assert.match(session.body.token, /^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/);
  assert.equal(session.body.expires_in, 86400);

  const malformed = await requestJson('/wp-json/wp-ds-aichatbot/v1/chat', {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify({ session: 'invalid', message: 'Smoke test' }),
  });
  assert.equal(malformed.response.status, 400, 'Malformed sessions must be rejected');

  const unavailable = await requestJson('/wp-json/wp-ds-aichatbot/v1/chat', {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify({ session: session.body.token, message: 'Smoke test' }),
  });
  assert.equal(unavailable.response.status, 503, 'Missing credentials must fail safely');
  assert.equal(unavailable.body.code, 'wpdsac_provider_not_configured');

  console.log(
    `WordPress smoke test passed (${withElementor ? 'Elementor' : 'core'} mode).`
  );
} catch (error) {
  throw error;
} finally {
  await playground[Symbol.asyncDispose]();
}

async function getFreePort() {
  return new Promise((resolvePort, reject) => {
    const server = createServer();
    server.unref();
    server.once('error', reject);
    server.listen(0, '127.0.0.1', () => {
      const address = server.address();
      server.close(() => resolvePort(address.port));
    });
  });
}

async function requestJson(path, options = {}) {
  const response = await fetch(`${baseUrl}${path}`, {
    ...options,
    signal: AbortSignal.timeout(15000),
  });
  const body = await response.json();

  return { response, body };
}

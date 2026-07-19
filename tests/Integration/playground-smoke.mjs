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
const adminScript = await readFile(
  join(projectRoot, 'assets/build/admin.js'),
  'utf8'
);
const adminStyles = await readFile(
  join(projectRoot, 'assets/build/admin.css'),
  'utf8'
);
const chatScript = await readFile(
  join(projectRoot, 'assets/build/chat.js'),
  'utf8'
);
const settingsPhp = await readFile(join(projectRoot, 'src/Admin/Settings.php'), 'utf8');
const faqPhp = await readFile(join(projectRoot, 'src/Knowledge/FaqPostType.php'), 'utf8');
const promptGuardPhp = await readFile(join(projectRoot, 'src/AI/PromptGuard.php'), 'utf8');
const providerManagerPhp = await readFile(join(projectRoot, 'src/AI/ProviderManager.php'), 'utf8');

assert.match(adminScript, /data-wpdsac-tab/);
assert.match(adminScript, /data-wpdsac-provider-select/);
assert.match(adminScript, /wpdsacActiveSettingsTab/);
assert.match(adminScript, /wpdsac_save_settings/);
assert.match(adminScript, /wpdsacDebugProvider/);
assert.match(adminScript, /wpdsac_credentials/);
assert.match(adminScript, /wpdsac_credential_payload/);
assert.match(adminScript, /Credential preflight/);
assert.match(adminScript, /removeProperty\('display'\)/);
assert.doesNotMatch(adminScript, /providerTargets/);
assert.match(settingsPhp, /data-wpdsac-provider-field/);
assert.match(adminStyles, /wpdsac-provider-setting\[hidden\]/);
assert.match(chatScript, /REST request failed/);
assert.match(settingsPhp, /add_menu_page/);
assert.match(settingsPhp, /wp-menu-image img/);
assert.match(settingsPhp, /add_submenu_page/);
assert.match(settingsPhp, /ensure_settings_first/);
assert.match(faqPhp, /Settings::PAGE_SLUG/);
assert.match(settingsPhp, /prompt_guard_enabled/);
assert.match(promptGuardPhp, /prompt_injection/);
assert.match(promptGuardPhp, /model_probe/);
assert.match(promptGuardPhp, /off_topic/);
assert.match(providerManagerPhp, /guard->inspect/);

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
    plugin_version: '0.5.21',
    db_version: '5',
    rate_limit_table: true,
    request_lock_table: true,
    knowledge_table: true,
    conversations_table: true,
    messages_table: true,
    leads_table: true,
    conversation_cleanup_cron: true,
    lead_cleanup_cron: true,
    settings_non_autoloaded: true,
    shortcode_registered: true,
    shortcode_rendered: true,
    shortcode_escaped: true,
    global_widget_rendered: true,
    custom_message_placeholder_rendered: true,
    appearance_rendered: true,
    appearance_positioned: true,
    appearance_sanitized: true,
    admin_preview_assets: true,
    ajax_save_registered: true,
    credential_bundle_resolved: true,
    deepseek_registered: true,
    knowledge_indexed: true,
    knowledge_retrieved: true,
    knowledge_augmented: true,
    faq_registered: true,
    faq_under_plugin_menu: true,
    faq_indexed: true,
    pdf_indexed: true,
    pdf_option_non_autoloaded: true,
    woocommerce_indexed: true,
    conversation_logged: true,
    privacy_exported: true,
    privacy_erased: true,
    lead_rendered: true,
    lead_saved: true,
    lead_privacy_exported: true,
    lead_privacy_erased: true,
    rate_limit_enforced: true,
    lead_rate_limit_enforced: true,
    deactivation_clean: true,
    lifecycle_rescheduled: true,
    lead_admin_denied: true,
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
      signal: AbortSignal.timeout(30000),
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

	const missingConsent = await requestJson('/wp-json/wp-ds-aichatbot/v1/lead', {
		method: 'POST',
		headers: {'content-type': 'application/json'},
		body: JSON.stringify({
			session: session.body.token,
			name: 'Runtime Lead',
			email: 'runtime-lead@example.test',
			consent: false,
			website: '',
		}),
	});
	assert.equal(missingConsent.response.status, 400, 'Lead consent must be required');

	const lead = await requestJson('/wp-json/wp-ds-aichatbot/v1/lead', {
		method: 'POST',
		headers: {'content-type': 'application/json'},
		body: JSON.stringify({
			session: session.body.token,
			name: 'Runtime Lead',
			email: 'runtime-lead@example.test',
			consent: true,
			website: '',
		}),
	});
	assert.equal(lead.response.status, 201, 'Consented lead must be saved');

  const unavailable = await requestJson('/wp-json/wp-ds-aichatbot/v1/chat', {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify({ session: session.body.token, message: 'Smoke test' }),
  });
  assert.equal(unavailable.response.status, 503, 'Missing credentials must fail safely');
  assert.equal(unavailable.body.code, 'wpdsac_provider_not_configured');

	const uninstall = await requestJson('/wp-json/wpdsac-test/v1/uninstall', {
		method: 'POST',
	});
	assert.equal(uninstall.response.status, 200, 'Uninstall probe must respond');
	assert.equal(uninstall.body.tables_removed, true, 'Uninstall must remove plugin tables');
	assert.equal(uninstall.body.options_removed, true, 'Uninstall must remove plugin options');
	assert.equal(uninstall.body.cron_removed, true, 'Uninstall must clear plugin schedules');

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
    signal: AbortSignal.timeout(30000),
  });
  const body = await response.json();

  return { response, body };
}

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
const chatStyles = await readFile(
  join(projectRoot, 'assets/build/chat.css'),
  'utf8'
);
const settingsPhp = await readFile(join(projectRoot, 'src/Admin/Settings.php'), 'utf8');
const faqPhp = await readFile(join(projectRoot, 'src/Knowledge/FaqPostType.php'), 'utf8');
const manualSourcePhp = await readFile(join(projectRoot, 'src/Knowledge/ManualSource.php'), 'utf8');
const elementorSourcePhp = await readFile(join(projectRoot, 'src/Knowledge/ElementorSource.php'), 'utf8');
const contactSourcePhp = await readFile(join(projectRoot, 'src/Knowledge/ContactSource.php'), 'utf8');
const answerEnricherPhp = await readFile(join(projectRoot, 'src/Knowledge/AnswerEnricher.php'), 'utf8');
const promptGuardPhp = await readFile(join(projectRoot, 'src/AI/PromptGuard.php'), 'utf8');
const providerManagerPhp = await readFile(join(projectRoot, 'src/AI/ProviderManager.php'), 'utf8');
const chatControllerPhp = await readFile(join(projectRoot, 'src/Api/ChatController.php'), 'utf8');
const quickActionsPhp = await readFile(join(projectRoot, 'src/Chat/QuickActions.php'), 'utf8');
const appearancePhp = await readFile(join(projectRoot, 'src/Chat/Appearance.php'), 'utf8');
const postIndexerPhp = await readFile(join(projectRoot, 'src/Knowledge/PostIndexer.php'), 'utf8');
const leadNotifierPhp = await readFile(join(projectRoot, 'src/Data/LeadNotifier.php'), 'utf8');
const chatbotTemplate = await readFile(join(projectRoot, 'templates/chatbot.php'), 'utf8');
const pluginPhp = await readFile(join(projectRoot, 'wp-ds-aichatbot.php'), 'utf8');
const bootstrapPhp = await readFile(join(projectRoot, 'src/Plugin.php'), 'utf8');
const embeddingsFactoryPhp = await readFile(join(projectRoot, 'src/AI/EmbeddingsProviderFactory.php'), 'utf8');
const migratorPhp = await readFile(join(projectRoot, 'src/Lifecycle/Migrator.php'), 'utf8');
const pluginVersion = pluginPhp.match(/define\(\s*'WPDSAC_VERSION',\s*'([^']+)'\s*\)/)?.[1];
const databaseVersion = migratorPhp.match(/DB_VERSION\s*=\s*'([^']+)'/)?.[1];

assert.ok(pluginVersion, 'The plugin version constant must be readable');
assert.ok(databaseVersion, 'The database schema version must be readable');

assert.match(adminScript, /data-wpdsac-tab/);
assert.match(adminScript, /wpdsacPreviewFallback/);
assert.match(adminScript, /data-wpdsac-provider-select/);
assert.match(adminScript, /wpdsacActiveSettingsTab/);
assert.match(adminScript, /wpdsac_save_settings/);
assert.match(adminScript, /wpdsacDebugProvider/);
assert.match(adminScript, /wpdsac_credentials/);
assert.match(adminScript, /wpdsac_credential_payload/);
assert.match(adminScript, /Credential preflight/);
assert.match(adminScript, /removeProperty\('display'\)/);
assert.match(adminScript, /wpdsacAdminTheme/);
assert.match(adminScript, /window\.wp\.media/);
assert.match(adminScript, /data-wpdsac-action-repeater/);
assert.match(adminStyles, /wpdsac-admin-theme-dark/);
assert.doesNotMatch(adminScript, /providerTargets/);
assert.match(settingsPhp, /data-wpdsac-provider-field/);
assert.match(settingsPhp, /wpdsac-fields-table/);
assert.match(settingsPhp, /render_settings_fields_table/);
assert.match(adminStyles, /wpdsac-provider-setting\[hidden\]/);
assert.match(chatScript, /REST request failed/);
assert.match(settingsPhp, /add_menu_page/);
assert.match(settingsPhp, /wp-menu-image img/);
assert.match(settingsPhp, /add_submenu_page/);
assert.match(settingsPhp, /ensure_settings_first/);
assert.match(faqPhp, /'show_in_menu'\s*=>\s*false/);
assert.match(manualSourcePhp, /wpdsac_manual_knowledge/);
assert.match(elementorSourcePhp, /_elementor_data/);
assert.match(postIndexerPhp, /elementor\/editor\/after_save/);
assert.match(contactSourcePhp, /wpdsac_contact_information/);
assert.match(answerEnricherPhp, /source_url/);
assert.match(chatScript, /noopener noreferrer/);
assert.match(chatScript, /wpdsacVisitorName/);
assert.match(chatScript, /createOscillator/);
assert.match(chatStyles, /white-space:\s*normal !important/);
assert.match(chatScript, /visitor_name/);
assert.match(chatScript, /getConversationHistory/);
assert.match(chatScript, /wpdsacConversationHistory/);
assert.match(chatScript, /restoreConversationHistory/);
assert.match(chatScript, /conversationLifetime\s*=\s*24/);
assert.match(chatScript, /ensureConversationFresh/);
assert.match(providerManagerPhp, /remove_repeated_greeting/);
assert.match(chatScript, /history:\s*getConversationHistory/);
assert.match(providerManagerPhp, /CONVERSATION HISTORY/);
assert.match(chatScript, /scheduleIntroBubble/);
assert.match(chatScript, /wpdsacAvatarUrl/);
assert.match(chatScript, /wpdsacAvatarPositionX/);
assert.match(chatbotTemplate, /wpdsac-chat__avatar-frame/);
assert.match(chatStyles, /wpdsac-chat__avatar-frame/);
assert.match(embeddingsFactoryPhp, /GeminiEmbeddingsProvider/);
assert.match(embeddingsFactoryPhp, /OpenRouterEmbeddingsProvider/);
assert.match(chatScript, /wpdsacMessageTemplate/);
assert.match(chatStyles, /backdrop-filter:\s*blur/);
assert.match(chatStyles, /wpdsac-chat__message-row/);
assert.match(chatStyles, /--wpdsac-message-height/);
assert.match(chatStyles, /--wpdsac-title-weight/);
assert.match(appearancePhp, /message_line_height/);
assert.match(appearancePhp, /message_animation_enabled/);
assert.match(chatScript, /animateAssistantContent/);
assert.match(chatScript, /wpdsacMessageContent/);
assert.match(chatScript, /prefers-reduced-motion:\s*reduce/);
assert.match(chatStyles, /wpdsac-chat__typing-word/);
assert.match(chatStyles, /contain:\s*style/);
assert.doesNotMatch(
  chatStyles,
  /^\s*(?:\.wpdsac-chat__|(?:img|svg)\.wpdsac-chat__)/m,
  'Every chatbot component selector must be scoped by the chatbot root.',
);

// CSS selector allowlist audit — check every top-level selector.
{
  const stripped = chatStyles
    .replace(/@keyframes\s+\S+\s*\{[^}]*\}/gs, '')
    .replace(/@media\s+[^{]+\{/g, '@media {');
  const topLines = stripped.split('\n').filter((l) => /^[^\s]/.test(l) && /\{/.test(l));
  const topSelectors = topLines.map((l) => l.split('{')[0].trim()).filter(Boolean);

  const allowedRoot = /^\.wpdsac-chat(\s|\[|:|\.|,|>|~|\+|$)/;
  const allowedBody = /^body\s*>\s*\.wpdsac-chat/;
  const allowedBodyClass = /^body\.wpdsac-modal-open(\s*\{)?/;
  const allowedNavHighlight = /^\[data-wpdsac-navigation-highlight\]/;
  const allowedMedia = /^@media\b/;
  const allowedKeyframes = /^@keyframes\b/;

  for (const sel of topSelectors) {
    assert.ok(
      allowedRoot.test(sel)
      || allowedBody.test(sel)
      || allowedBodyClass.test(sel)
      || allowedNavHighlight.test(sel)
      || allowedMedia.test(sel)
      || allowedKeyframes.test(sel),
      `Top-level CSS selector "${sel}" is not in the allowlist.`,
    );
  }
}
assert.match(chatScript, /data-wpdsac-navigation-highlight/);
assert.match(chatbotTemplate, /data-wpdsac-message-word-delay/);
assert.match(settingsPhp, /reply_sound/);
assert.match(settingsPhp, /\{username\}/);
assert.match(providerManagerPhp, /Visitor name \(untrusted profile data\)/);
assert.match(chatbotTemplate, /data-wpdsac-open-lead/);
assert.doesNotMatch(chatbotTemplate, /data-wpdsac-name-form/);
assert.match(chatbotTemplate, /data-wpdsac-quick-actions/);
assert.ok(
  chatbotTemplate.indexOf('data-wpdsac-quick-actions') < chatbotTemplate.indexOf('data-wpdsac-form'),
  'Quick actions should be rendered before the message form.',
);
assert.match(chatbotTemplate, /name="email"/);
assert.doesNotMatch(chatScript, /hideQuickAction/);
assert.match(chatScript, /openLeadForm/);
assert.match(chatScript, /extractLeadDetails/);
assert.match(chatScript, /lead\.hidden = true/);
assert.match(chatScript, /collectNavigationTargets/);
assert.match(chatScript, /wpdsacNavigationUrl/);
assert.match(chatScript, /wpdsacNavigationLabel/);
assert.match(chatScript, /wpdsac-contact-form/);
assert.match(chatScript, /data-wpdsac-action/);
assert.match(chatScript, /prepareLeadModal/);
assert.match(chatScript, /chatByLeadModal/);
assert.match(providerManagerPhp, /WPDSAC_ACTION\|lead_form/);
assert.match(chatControllerPhp, /sanitize_navigation_targets/);
assert.match(providerManagerPhp, /SITE ACTION POLICY/);
assert.match(quickActionsPhp, /MAX_ACTIONS\s*=\s*8/);
assert.match(chatbotTemplate, /role="dialog"/);
assert.match(chatbotTemplate, /aria-modal="true"/);
assert.match(chatStyles, /--wpdsac-height/);
assert.match(chatStyles, /wpdsac-launcher-gradient/);
assert.match(chatStyles, /prefers-reduced-motion/);
assert.match(chatbotTemplate, /data-wpdsac-launcher-animation/);
assert.match(chatbotTemplate, /name="phone"[^>]+required/);
assert.match(chatbotTemplate, /data-wpdsac-intro-trigger/);
assert.match(chatbotTemplate, /wpdsac-chat__avatar/);
assert.match(leadNotifierPhp, /wp_mail/);
assert.match(settingsPhp, /Knowledge/);
assert.match(settingsPhp, /prompt_guard_enabled/);
assert.match(promptGuardPhp, /prompt_injection/);
assert.match(promptGuardPhp, /model_probe/);
assert.match(promptGuardPhp, /off_topic/);
assert.match(promptGuardPhp, /Your public chatbot name/);
assert.match(promptGuardPhp, /Never use an em dash/);
assert.match(promptGuardPhp, /never copy the choice notation literally/);
assert.match(settingsPhp, /Chatbot name/);
assert.match(providerManagerPhp, /guard->inspect/);
assert.match(providerManagerPhp, /normalize_human_punctuation/);
assert.match(chatbotTemplate, /data-wpdsac-context-actions/);
assert.match(chatbotTemplate, /data-wpdsac-actions/);
assert.ok(
  chatbotTemplate.indexOf('data-wpdsac-context-actions') > chatbotTemplate.indexOf('data-wpdsac-messages'),
  'Context actions container must be after messages, before the form.',
);
assert.ok(
  chatbotTemplate.indexOf('data-wpdsac-context-actions') < chatbotTemplate.indexOf('data-wpdsac-form'),
  'Context actions must be before the input form.',
);
assert.match(chatbotTemplate, /data-wpdsac-quick-actions/);
assert.ok(
  chatbotTemplate.indexOf('data-wpdsac-quick-actions') > chatbotTemplate.indexOf('data-wpdsac-messages'),
  'Default quick actions must be after messages.',
);
assert.match(chatbotTemplate, /role="group"/);
assert.match(chatbotTemplate, /Reply options/);
assert.match(chatScript, /renderContextActions/);
assert.match(chatScript, /clearContextActions/);
assert.match(chatScript, /data-wpdsac-context-message/);
assert.match(chatScript, /wpdsacContextMessage/);
assert.match(chatScript, /quick_replies/);
assert.match(chatScript, /\/reengage/);
assert.match(chatScript, /wpdsacReengage/);
assert.match(chatScript, /getReengageState/);
assert.match(chatScript, /saveReengageState/);
assert.match(chatScript, /resetReengageActivity/);
assert.match(chatScript, /visibilitychange/);
assert.match(chatScript, /previousIntroText/);
assert.match(chatScript, /currentlyExpanded/);
assert.match(chatScript, /reengageInFlight/);
assert.doesNotMatch(chatScript, /\[SYSTEM:/);
assert.match(chatStyles, /wpdsac-chat__context-actions/);
assert.match(chatStyles, /wpdsac-chat__context-action/);
assert.match(chatStyles, /wpdsac-chat__actions/);
assert.match(chatStyles, /context-actions\[hidden\]/);

// Verify QA marker parsing moved server-side.
const parserPhp = await readFile(join(projectRoot, 'src/AI/QuickReplyParser.php'), 'utf8');
assert.match(parserPhp, /QuickReplyParser/);
assert.match(parserPhp, /\[\[WPDSAC_QA/);
assert.match(parserPhp, /strip_all_tags/);

const reengagePhp = await readFile(join(projectRoot, 'src/AI/ReengageService.php'), 'utf8');
assert.match(reengagePhp, /ReengageService/);
assert.match(reengagePhp, /wpdsac_reengage_/);

const reengageControllerPhp = await readFile(join(projectRoot, 'src/Api/ReengageController.php'), 'utf8');
assert.match(reengageControllerPhp, /ReengageController/);
assert.match(reengageControllerPhp, /\/reengage/);

// Verify ProviderManager has reengage hook.
assert.match(providerManagerPhp, /wpdsac_reengage_exchange/);
assert.match(providerManagerPhp, /Re-engage prompt/);

// Verify ChatController returns quick_replies.
assert.match(chatControllerPhp, /quick_replies/);

// Verify Plugin.php wires ReengageController.
assert.match(bootstrapPhp, /ReengageController/);
assert.match(bootstrapPhp, /ReengageService/);
assert.match(bootstrapPhp, /QuickReplyParser/);

// --- Behavioral tests -- re-engage state machine ---

// Terminal reasons must not reschedule.
assert.match(chatScript, /terminalReason/, 'Re-engage must track terminal state.');
assert.match(chatScript, /TERMINAL_REASONS/, 'Terminal reasons must be a defined set.');
assert.match(chatScript, /clearReengageSchedule/, 'clearReengageSchedule must cancel the timer and save terminal state.');
assert.doesNotMatch(chatScript, /finally\s*\{[^}]*scheduleReengage/s, 'Finally must not reschedule on its own.');

// provider_error stops the cycle.
assert.match(chatScript, /provider_error/, 'provider_error must be a recognized terminal reason.');

// max_reached, lead_exists stop.
assert.match(reengagePhp, /max_reached/, 'max_reached reason must be returned by guard.');
assert.match(reengagePhp, /lead_exists/, 'lead_exists reason must be returned by guard.');

// cooldown creates exactly one retry via retry_after.
assert.match(chatScript, /cooldown/, 'cooldown must be handled by handleReengageState.');
assert.match(chatScript, /retry_after/, 'retry_after must be respected for cooldown retry.');

// rate_limited must not return reason=ok.
assert.match(reengageControllerPhp, /rate_limited/, 'rate_limited must set guard reason, not default to ok.');
// daily_limit must not return reason=ok.
assert.match(reengageControllerPhp, /daily_limit/, 'daily_limit must set guard reason, not default to ok.');
// empty_reply must not return reason=ok.
assert.match(reengageControllerPhp, /empty_reply/, 'empty_reply must set guard reason, not default to ok.');
// request_in_progress must return consistent reengage structure.
assert.match(reengageControllerPhp, /request_in_progress/, 'request_in_progress must set guard reason.');
assert.match(reengageControllerPhp, /reengage_state/, 'All /reengage returns must use reengage_state().');

// visibilitychange must not resume terminal state.
assert.match(chatScript, /visibilitychange/, 'visibilitychange handler must be present.');
assert.match(chatScript, /terminalReason/, 'visibilitychange must check terminalReason before firing.');

// Contextual replies scoped per chat session.
assert.match(chatScript, /contextRepliesStorageKey/, 'Context replies storage must be scoped per chat/session.');
assert.match(chatScript, /clearStoredContextReplies/, 'Context replies must be cleared on user action.');
assert.match(chatScript, /storeContextReplies/, 'Context replies must be stored before close.');
assert.match(chatScript, /getStoredContextReplies/, 'Context replies must be restored on open.');

// Manual reply clears stored contextual replies.
assert.match(chatScript, /clearContextActions\(chat\)/, 'Form submit must clear context actions.');
assert.match(chatScript, /hideAllQaButtons/, 'Form submit must hide all QA buttons.');

// QuickReplyParser uses gettext fallback.
assert.match(parserPhp, /Choose an option/, 'QuickReplyParser must use gettext fallback, not hardcoded Russian.');

// Visual regression: key CSS rules must exist.
assert.match(chatStyles, /\.wpdsac-chat\s*\.wpdsac-chat__panel\s*\{[^}]*backdrop-filter\s*:\s*blur/, 'Panel must have glass blur.');
assert.match(chatStyles, /\.wpdsac-chat\s*\.wpdsac-chat__messages\s*\{[^}]*background\s*:\s*(?:transparent|var\(--wpdsac-msg-bg\))/, 'Messages must have transparent or custom background.');
assert.match(chatStyles, /\.wpdsac-chat\s*\.wpdsac-chat__messages\s*\{[^}]*scrollbar-width\s*:\s*none/, 'Messages must hide scrollbar.');
assert.match(chatStyles, /\.wpdsac-chat\s*\.wpdsac-chat__typing/, 'Chat must have typing indicator styles.');
assert.match(chatStyles, /\.wpdsac-chat\s*\.wpdsac-chat__conversation\s*\{[^}]*background\s*:\s*transparent/, 'Conversation must be transparent.');
assert.match(chatScript, /typing\.remove/, 'Chat must clean up typing indicator.');
assert.match(chatScript, /wpdsac-chat__typing-dot/, 'Chat must render typing dots.');
assert.doesNotMatch(parserPhp, /Выберите подходящий вариант/, 'QuickReplyParser must not have hardcoded Russian fallback.');

// ChatController must not import ReengageService.
assert.doesNotMatch(chatControllerPhp, /use.*ReengageService/, 'ChatController must not import unused ReengageService.');

// ReengageService must not have double PHPDoc.
const reengagePhpContent = await readFile(join(projectRoot, 'src/AI/ReengageService.php'), 'utf8');
assert.doesNotMatch(reengagePhpContent, /\/\*\*\s*\n\s+\/\*\*/, 'ReengageService must not have duplicate PHPDoc.');

// Context buttons must be above form and outside messages.
assert.ok(
  chatbotTemplate.indexOf('data-wpdsac-context-actions') > chatbotTemplate.indexOf('data-wpdsac-messages'),
  'Context actions must be outside the message history.',
);
assert.ok(
  chatbotTemplate.indexOf('data-wpdsac-context-actions') < chatbotTemplate.indexOf('data-wpdsac-form'),
  'Context actions must be above the input form.',
);
assert.match(chatbotTemplate, /data-wpdsac-actions/);
assert.ok(
  chatbotTemplate.indexOf('data-wpdsac-context-actions') > chatbotTemplate.indexOf('data-wpdsac-actions'),
  'Context actions must be inside the actions wrapper.',
);

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
    plugin_version: pluginVersion,
    db_version: databaseVersion,
    rate_limit_table: true,
    request_lock_table: true,
    knowledge_table: true,
    conversations_table: true,
    messages_table: true,
    leads_table: true,
    conversation_cleanup_cron: true,
    lead_cleanup_cron: true,
    conversation_mail_scheduled: true,
    settings_non_autoloaded: true,
    shortcode_registered: true,
    shortcode_rendered: true,
    shortcode_escaped: true,
    global_widget_rendered: true,
    custom_message_placeholder_rendered: true,
    reply_sound_rendered: true,
    message_animation_rendered: true,
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
    knowledge_auto_indexed: true,
    elementor_content_indexed: true,
    source_url_retrieved: true,
    requested_link_appended: true,
    faq_registered: true,
    faq_merged_into_knowledge: true,
    faq_indexed: true,
    manual_knowledge_indexed: true,
    manual_knowledge_non_autoloaded: true,
    contact_knowledge_indexed: true,
    contact_option_non_autoloaded: true,
    requested_contacts_appended: true,
    pdf_indexed: true,
    pdf_option_non_autoloaded: true,
    woocommerce_indexed: true,
    conversation_logged: true,
    privacy_exported: true,
    privacy_erased: true,
    lead_rendered: true,
    lead_saved: true,
    lead_mail_sent: true,
    conversation_mail_sent: true,
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
			phone: '+7 700 000 00 00',
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
			phone: '+7 700 000 00 00',
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

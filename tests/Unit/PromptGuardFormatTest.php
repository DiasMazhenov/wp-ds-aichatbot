<?php
/**
 * Behavioral regression: PromptGuard must produce well-formed policy text.
 *
 * @package WPDsAiChatbotTests
 */

use DiasMazhenov\WPDsAiChatbot\AI\PromptGuard;
use PHPUnit\Framework\TestCase;

final class PromptGuardFormatTest extends TestCase {

	/**
	 * When a chatbot name is configured, the name instruction must appear
	 * on a real newline — never as a visible '\n' literal.
	 */
	public function test_chatbot_name_instruction_uses_real_newline(): void {
		$instructions = PromptGuard::protected_instructions(
			'Be concise.',
			'',
			'',
			'AI-Dana'
		);

		$this->assertStringContainsString( "\n- Your public chatbot name is \"AI-Dana\"", $instructions );

		$this->assertStringNotContainsString( '\n', $instructions );
	}

	/**
	 * Without a chatbot name the policy must still end correctly and
	 * must not leave a dangling single-quote literal.
	 */
	public function test_protected_instructions_without_chatbot_name(): void {
		$instructions = PromptGuard::protected_instructions(
			'Be concise.',
			'',
			'',
			''
		);

		$this->assertStringContainsString( 'SECURITY POLICY', $instructions );
		$this->assertStringNotContainsString( 'Your public chatbot name is', $instructions );
		$this->assertStringNotContainsString( '\n', $instructions );
	}

	/**
	 * The policy must not contain escaped single-quote representations
	 * anywhere in protected instructions.
	 */
	public function test_policy_contains_no_visible_escape_sequences(): void {
		$instructions = PromptGuard::protected_instructions(
			'Be concise.',
			'доставка оплата',
			'Я не могу ответить.',
			'МойБот'
		);

		$this->assertStringNotContainsString( '\n', $instructions );
		$this->assertStringNotContainsString( '\t', $instructions );

		$this->assertStringContainsString( "\n- Your public chatbot name is \"МойБот\"", $instructions );
		$this->assertStringContainsString( "\nAllowed topic scope: доставка оплата", $instructions );
		$this->assertStringContainsString( "\nExact refusal text: Я не могу ответить.", $instructions );
	}

	/**
	 * The chatbot name instruction must appear between the main policy
	 * block and the topic scope block — not smashed together without a newline.
	 */
	public function test_policy_blocks_are_separated_by_newlines(): void {
		$instructions = PromptGuard::protected_instructions(
			'Be concise.',
			'',
			'',
			'AI-Dana'
		);

		$blocks = array(
			'SECURITY POLICY (higher priority',
			'- Your public chatbot name is "AI-Dana"',
			'Never invent another name and never repeat the introduction',
			'SITE INSTRUCTIONS:',
			'Be concise.',
		);

		$previous_pos = 0;

		foreach ( $blocks as $block ) {
			$pos = strpos( $instructions, $block );

			$this->assertNotFalse( $pos, "Block not found: {$block}" );
			$this->assertGreaterThanOrEqual( $previous_pos, $pos, "Block out of order: {$block}" );

			$previous_pos = $pos;
		}
	}
}

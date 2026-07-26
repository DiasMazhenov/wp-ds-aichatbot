<?php
/**
 * Unit tests for CSV export sanitization.
 *
 * @package WPDsAiChatbotTests
 */

use DiasMazhenov\WPDsAiChatbot\Admin\LeadsPage;
use PHPUnit\Framework\TestCase;

final class CsvExportTest extends TestCase {

	/**
	 * Data provider for formula injection characters.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function formula_injection_provider(): array {
		return array(
			'equals sign'  => array( '=SUM(A1:B1)', "'=SUM(A1:B1)" ),
			'plus sign'    => array( '+CMD|C:\\Calc.exe', "'+CMD|C:\\Calc.exe" ),
			'minus sign'   => array( '-1+1', "'-1+1" ),
			'at sign'      => array( '=COMMAND("ls")', "'=COMMAND(\"ls\")" ),
			'normal text'  => array( 'John Doe', 'John Doe' ),
			'empty string' => array( '', '' ),
			'leading space' => array( ' =test', ' =test' ),
			'equals in middle' => array( 'foo=bar', 'foo=bar' ),
		);
	}

	/**
	 * Test that sanitize_csv_cell neutralizes formula injection characters.
	 *
	 * @dataProvider formula_injection_provider
	 * @param string $input    Raw cell value.
	 * @param string $expected Expected sanitized value.
	 * @return void
	 */
	public function test_sanitize_csv_cell_neutralizes_formula_injection( string $input, string $expected ): void {
		$this->assertSame( $expected, LeadsPage::sanitize_csv_cell( $input ) );
	}
}

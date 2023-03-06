<?php

/**
 * @group Database
 * @covers ApiGetHeaderFooter
 */
class ApiGetHeaderFooterTest extends ApiTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Explicitly enable the message cache to allow testing custom header/footer messages.
		$this->getServiceContainer()->getMessageCache()->enable();
	}

	protected function tearDown(): void {
		parent::tearDown();
		$this->getServiceContainer()->getMessageCache()->disable();
	}

	public function testShouldRequireMessageId(): void {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'The "messageid" parameter must be set.' );

		$this->doApiRequest( [
			'action' => 'getheaderfooter',
			'contexttitle' => 'Some title',
			'messageid' => ''
		] );
	}

	public function testShouldThrowOnInvalidTitle(): void {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'Not a valid contexttitle.' );

		$this->doApiRequest( [
			'action' => 'getheaderfooter',
			'contexttitle' => '~~~Invalid',
			'messageid' => 'Hf-nsfooter-'
		] );
	}

	public function testShouldReturnEmptyContentIfGivenMessageIsEmpty(): void {
		$messageId = 'Hf-nsheader-';
		$this->editPage( new TitleValue( NS_MEDIAWIKI, $messageId ), '' );

		[ $res ] = $this->doApiRequest( [
			'action' => 'getheaderfooter',
			'contexttitle' => 'Some title',
			'messageid' => $messageId
		] );

		$this->assertSame(
			[ 'getheaderfooter' => [ 'result' => '<div class="mw-parser-output"></div>' ] ],
			$res
		);
	}

	public function testShouldReturnParsedContentInTheContextOfTheGivenPage(): void {
		$messageId = 'Hf-nsheader-';
		$this->editPage(
			new TitleValue( NS_MEDIAWIKI, $messageId ),
			'Header for {{PAGENAME}}'
		);

		[ $res ] = $this->doApiRequest( [
			'action' => 'getheaderfooter',
			'contexttitle' => 'Some title',
			'messageid' => $messageId
		] );

		$this->assertSame(
			[
				'getheaderfooter' => [
					'result' => "<div class=\"mw-parser-output\"><p>Header for Some title\n</p></div>"
				]
			],
			$res
		);
	}
}

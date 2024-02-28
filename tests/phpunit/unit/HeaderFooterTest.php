<?php

/**
 * @covers HeaderFooter
 */
class HeaderFooterTest extends MediaWikiUnitTestCase {

	private OutputPage $outputPage;

	private ParserOutput $parserOutput;

	private FauxRequest $webRequest;

	protected function setUp(): void {
		parent::setUp();
		$this->outputPage = $this->createMock( OutputPage::class );
		$this->parserOutput = $this->createMock( ParserOutput::class );
		$this->webRequest = new FauxRequest();

		$this->outputPage->expects( $this->any() )
			->method( 'getRequest' )
			->willReturn( $this->webRequest );
	}

	/**
	 * @dataProvider provideNonViewActions
	 */
	public function testShouldDoNothingForNonViewActions(
		array $overrides,
		string $actionName
	): void {
		$this->webRequest->setVal( 'action', $actionName );

		$this->outputPage->expects( $this->never() )
			->method( 'addModules' );
		$this->parserOutput->expects( $this->never() )
			->method( $this->anything() );

		$this->getHeaderFooter( $overrides )->onOutputPageParserOutput(
			$this->outputPage,
			$this->parserOutput
		);
	}

	public function provideNonViewActions(): iterable {
		$configOverrides = [
			'no async elements' => [
				'HeaderFooterEnableAsyncHeader' => false,
				'HeaderFooterEnableAsyncFooter' => false
			],
			'async header' => [
				'HeaderFooterEnableAsyncHeader' => true,
				'HeaderFooterEnableAsyncFooter' => false
			],
			'async footer' => [
				'HeaderFooterEnableAsyncHeader' => false,
				'HeaderFooterEnableAsyncFooter' => true,
			],
			'all async' => [
				'HeaderFooterEnableAsyncHeader' => false,
				'HeaderFooterEnableAsyncFooter' => false
			],
		];

		foreach ( $configOverrides as $name => $overrides ) {
			foreach ( [ 'edit', 'history', 'submit' ] as $actionName ) {
				yield "config: $name, action: $actionName" => [ $overrides, $actionName ];
			}
		}
	}

	/**
	 * @dataProvider provideNonAsync
	 */
	public function testShouldInjectNonAsyncElements( string $input, string $expected ): void {
		$elements = [ 'namespace header', 'page header', 'page footer', 'namespace footer' ];
		$msgs = [];

		foreach ( $elements as $text ) {
			$msg = $this->createMock( Message::class );
			$msgContLang = $this->createMock( Message::class );
			$msg->expects( $this->any() )
				->method( 'inContentLanguage' )
				->willReturn( $msgContLang );
			$msg->expects( $this->any() )
				->method( 'parse' )
				->willReturn( $text );
			$msgContLang->expects( $this->any() )
				->method( 'isBlank' )
				->willReturn( false );

			$msgs[] = $msg;
		}

		$this->parserOutput->expects( $this->any() )
			->method( 'getText' )
			->willReturn( $input );

		$title = $this->createMock( Title::class );
		$title->expects( $this->any() )
			->method( 'getNsText' )
			->willReturn( 'Test' );
		$title->expects( $this->any() )
			->method( 'getPrefixedDBkey' )
			->willReturn( 'Test:Foo' );

		$this->outputPage->expects( $this->any() )
			->method( 'getTitle' )
			->willReturn( $title );

		[ $nsHeaderMsg, $pageHeaderMsg, $pageFooterMsg, $nsFooterMsg ] = $msgs;

		$this->outputPage->expects( $this->any() )
			->method( 'msg' )
			->willReturnMap( [
				[ 'hf-nsheader-Test', $nsHeaderMsg ],
				[ 'hf-header-Test:Foo', $pageHeaderMsg ],
				[ 'hf-footer-Test:Foo', $pageFooterMsg ],
				[ 'hf-nsfooter-Test', $nsFooterMsg ],
			] );

		$this->outputPage->expects( $this->never() )
			->method( 'addModules' );

		$this->parserOutput->expects( $this->once() )
			->method( 'setText' )
			->with( $expected );

		$this->getHeaderFooter()->onOutputPageParserOutput(
			$this->outputPage,
			$this->parserOutput
		);
	}

	public function provideNonAsync(): iterable {
		yield 'all elements enabled' => [
			'<div class="mw-parser-output">foo</div>',

			'<div class="hf-nsheader" id="hf-nsheader-Test">namespace header</div>' .
			'<div class="hf-header" id="hf-header-Test:Foo">page header</div>' .
			'<div class="mw-parser-output">foo</div>' .
			'<div class="hf-footer" id="hf-footer-Test:Foo">page footer</div>' .
			'<div class="hf-nsfooter" id="hf-nsfooter-Test">namespace footer</div>'
		];
		yield 'namespace-specific header disabled' => [
			'<div class="mw-parser-output">foo __NONSHEADER__</div>',

			'<div class="hf-header" id="hf-header-Test:Foo">page header</div>' .
			'<div class="mw-parser-output">foo </div>' .
			'<div class="hf-footer" id="hf-footer-Test:Foo">page footer</div>' .
			'<div class="hf-nsfooter" id="hf-nsfooter-Test">namespace footer</div>'
		];
		yield 'namespace-specific footer disabled' => [
			'<div class="mw-parser-output">foo __NONSFOOTER__</div>',

			'<div class="hf-nsheader" id="hf-nsheader-Test">namespace header</div>' .
			'<div class="hf-header" id="hf-header-Test:Foo">page header</div>' .
			'<div class="mw-parser-output">foo </div>' .
			'<div class="hf-footer" id="hf-footer-Test:Foo">page footer</div>'
		];
		yield 'page-specific header disabled' => [
			'<div class="mw-parser-output">foo __NOHEADER__</div>',

			'<div class="hf-nsheader" id="hf-nsheader-Test">namespace header</div>' .
			'<div class="mw-parser-output">foo </div>' .
			'<div class="hf-footer" id="hf-footer-Test:Foo">page footer</div>' .
			'<div class="hf-nsfooter" id="hf-nsfooter-Test">namespace footer</div>'
		];
		yield 'page-specific footer disabled' => [
			'<div class="mw-parser-output">foo __NOFOOTER__</div>',

			'<div class="hf-nsheader" id="hf-nsheader-Test">namespace header</div>' .
			'<div class="hf-header" id="hf-header-Test:Foo">page header</div>' .
			'<div class="mw-parser-output">foo </div>' .
			'<div class="hf-nsfooter" id="hf-nsfooter-Test">namespace footer</div>'
		];
	}

	public function testShouldInjectOnlyJavascriptAndWrappersIfAsync(): void {
		$this->parserOutput->expects( $this->any() )
			->method( 'getText' )
			->willReturn( '<div class="mw-parser-output">foo</div>' );

		$title = $this->createMock( Title::class );
		$title->expects( $this->any() )
			->method( 'getNsText' )
			->willReturn( 'Test' );
		$title->expects( $this->any() )
			->method( 'getPrefixedDBkey' )
			->willReturn( 'Test:Foo' );

		$this->outputPage->expects( $this->any() )
			->method( 'getTitle' )
			->willReturn( $title );

		$this->outputPage->expects( $this->never() )
			->method( 'msg' );

		$this->outputPage->expects( $this->once() )
			->method( 'addModules' )
			->with( 'ext.headerfooter.dynamicload' );

		$this->parserOutput->expects( $this->once() )
			->method( 'setText' )
			->with( '<div class="hf-nsheader" id="hf-nsheader-Test"></div>' .
				'<div class="hf-header" id="hf-header-Test:Foo"></div>' .
				'<div class="mw-parser-output">foo</div>' .
				'<div class="hf-footer" id="hf-footer-Test:Foo"></div>' .
				'<div class="hf-nsfooter" id="hf-nsfooter-Test"></div>'
			);

		$async = [
			'HeaderFooterEnableAsyncHeader' => true,
			'HeaderFooterEnableAsyncFooter' => true
		];

		$this->getHeaderFooter( $async )->onOutputPageParserOutput(
			$this->outputPage,
			$this->parserOutput
		);
	}

	private function getHeaderFooter( array $configOverrides = [] ): HeaderFooter {
		$configDefaults = [
			'HeaderFooterEnableAsyncHeader' => false,
			'HeaderFooterEnableAsyncFooter' => false
		];

		return new HeaderFooter(
			new HashConfig( $configOverrides + $configDefaults ),
			WANObjectCache::newEmpty()
		);
	}
}

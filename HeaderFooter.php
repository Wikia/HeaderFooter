<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Hook\OutputPageParserOutputHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;

/**
 * Main hook handler class for HeaderFooter.
 */
class HeaderFooter implements OutputPageParserOutputHook, ResourceLoaderGetConfigVarsHook {

	private const CONSTRUCTOR_OPTIONS = [
		'HeaderFooterEnableAsyncHeader',
		'HeaderFooterEnableAsyncFooter'
	];

	private ServiceOptions $options;

	public function __construct( Config $config, private WANObjectCache $cache ) {
		$this->options = new ServiceOptions( self::CONSTRUCTOR_OPTIONS, $config );
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Inject header and footer elements into the page content.
	 * @param OutputPage $outputPage
	 * @param ParserOutput $parserOutput
	 */
	public function onOutputPageParserOutput( $outputPage, $parserOutput ): void {
		$action = $outputPage->getRequest()->getVal( "action" );
		if ( ( $action == 'edit' ) || ( $action == 'submit' ) || ( $action == 'history' ) ) {
			return;
		}

		$ns = $outputPage->getTitle()->getNsText();
		$name = $outputPage->getTitle()->getPrefixedDBKey();

		$text = $parserOutput->getText();

		$nsheader = $this->conditionalInclude(
			$outputPage, $text, '__NONSHEADER__', 'hf-nsheader', $ns );
		$header = $this->conditionalInclude(
			$outputPage, $text, '__NOHEADER__',   'hf-header', $name );
		$footer = $this->conditionalInclude(
			$outputPage, $text, '__NOFOOTER__',   'hf-footer', $name );
		$nsfooter = $this->conditionalInclude(
			$outputPage, $text, '__NONSFOOTER__', 'hf-nsfooter', $ns );

		$parserOutput->setText( $nsheader . $header . $text . $footer . $nsfooter );

		if (
			$this->options->get( 'HeaderFooterEnableAsyncFooter' ) ||
			$this->options->get( 'HeaderFooterEnableAsyncHeader' )
		) {
			$outputPage->addModules( 'ext.headerfooter.dynamicload' );
		}
	}

	/**
	 * Verifies & Strips ''disable command'', returns $content if all OK.
	 * @param MessageLocalizer $msgLocalizer MessageLocalizer instance to use for translations.
	 * @param string &$text Parsed page HTML.
	 * @param string $disableWord Special string to disable injecting content.
	 * @param string $class CSS class of the element to inject.
	 * @param string $unique Unique identifier used to build an HTML ID for the injected element.
	 * @return string The HTML element to be added to the content.
	 */
	private function conditionalInclude(
		MessageLocalizer $msgLocalizer,
		&$text,
		$disableWord,
		$class,
		$unique
	) {
		// is there a disable command lurking around?
		$disable = str_contains( $text, $disableWord );

		// if there is, get rid of it
		// make sure that the disableWord does not break the REGEX below!
		$text = preg_replace( '/' . $disableWord . '/si', '', $text );

		// if there is a disable command, then don't return anything
		if ( $disable ) {
			return '';
		}

		// Also used as an HTML ID
		$msgId = "$class-$unique";
		$attributes = [ 'class' => $class, 'id' => $msgId ];

		$isHeader = $class === 'hf-nsheader' || $class === 'hf-header';
		$isFooter = $class === 'hf-nsfooter' || $class === 'hf-footer';

		if ( ( $this->options->get( 'HeaderFooterEnableAsyncFooter' ) && $isFooter )
			|| ( $this->options->get( 'HeaderFooterEnableAsyncHeader' ) && $isHeader ) ) {
			// Just drop an empty div into the page. Will fill it with async
			// request after page load
			return Html::element( 'div', $attributes );
		} else {
			$msg = $msgLocalizer->msg( $msgId );

			if ( $msg->inContentLanguage()->isBlank() ) {
				return '';
			}

			$msgText = $this->cache->getWithSetCallback(
				$this->cache->makeKey( 'HeaderFooter', $class, md5( $msgId ) ),
				15 * WANObjectCache::TTL_MINUTE,
				fn () => $msg->parse()
			);

			// don't need to bother if there is no content.
			if ( empty( $msgText ) ) {
				return '';
			}

			return Html::rawElement( 'div', $attributes, $msgText );
		}
	}

	/**
	 * Expose whether header and footer tag contents should be fetched asynchronously.
	 *
	 * @param array &$vars
	 * @param string $skin
	 * @param Config $config
	 * @return void
	 */
	public function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ): void {
		$vars['egHeaderFooter'] = [
			'enableAsyncHeader' => $this->options->get( 'HeaderFooterEnableAsyncHeader' ),
			'enableAsyncFooter' => $this->options->get( 'HeaderFooterEnableAsyncFooter' ),
		];
	}

}

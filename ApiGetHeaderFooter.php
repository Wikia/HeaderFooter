<?php

/**
 * API module for MediaWiki's HeaderFooter extension.
 *
 * @author James Montalvo
 * @since Version 3.0
 */

use MediaWiki\Page\PageReferenceValue;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * API module to review revisions
 */
class ApiGetHeaderFooter extends ApiBase {

	/**
	 * @inheritDoc
	 */
	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		private Parser $parser,
		private TitleParser $titleParser
	) {
		parent::__construct( $mainModule, $moduleName );
	}

	public function execute() {
		$params = $this->extractRequestParams();

		try {
			$contextTitle = $this->titleParser->parseTitle( $params['contexttitle'] );
			$contextTitle = PageReferenceValue::localReference(
				$contextTitle->getNamespace(),
				$contextTitle->getDBkey()
			);
		} catch ( MalformedTitleException $e ) {
			$this->dieWithError( new RawMessage( "Not a valid contexttitle." ), 'notarget' );
		}

		$messageId = $params['messageid'];

		$messageText = $this->msg( $messageId )->page( $contextTitle )->text();

		// don't need to bother if there is no content.
		if ( empty( $messageText ) ) {
			$messageText = '';
		}

		if ( $this->msg( $messageId )->inContentLanguage()->isBlank() ) {
			$messageText = '';
		}

		$messageText = $this->parser->parse(
			$messageText,
			$contextTitle,
			ParserOptions::newFromContext( $this )
		)->getText();

		$this->getResult()->addValue( null, $this->getModuleName(), [ 'result' => $messageText ] );
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'contexttitle' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string'
			],
			'messageid' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string'
			]
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=getheaderfooter&contexttitle=Main_Page&messageid=Hf-nsfooter-'
				=> 'apihelp-getheaderfooter-example-1',
		];
	}

	public function mustBePosted() {
		return false;
	}

	public function isWriteMode() {
		return false;
	}

}

<?php

namespace SMW\Tests\Factbox;

use ParserOutput;
use ReflectionClass;
use SMW\DIProperty;
use SMW\DIWikiPage;
use SMW\Factbox\Factbox;
use SMW\ParserData;
use SMW\SemanticData;
use SMW\TableFormatter;
use SMW\Tests\TestEnvironment;
use Title;

/**
 * @covers \SMW\Factbox\Factbox
 * @group semantic-mediawiki
 *
 * @license GNU GPL v2+
 * @since 1.9
 *
 * @author mwjames
 */
class FactboxTest extends \PHPUnit_Framework_TestCase {

	private $stringValidator;
	private $testEnvironment;

	protected function setUp() {
		parent::setUp();

		$this->testEnvironment = new TestEnvironment();
		$this->stringValidator = $this->testEnvironment->getUtilityFactory()->newValidatorFactory()->newStringValidator();

		$this->testEnvironment->addConfiguration( 'smwgShowFactbox', SMW_FACTBOX_NONEMPTY );
	}

	protected function tearDown() {
		$this->testEnvironment->tearDown();
		parent::tearDown();
	}

	public function testCanConstruct() {

		$store = $this->getMockBuilder( '\SMW\Store' )
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		$parserData = $this->getMockBuilder( '\SMW\ParserData' )
			->disableOriginalConstructor()
			->getMock();

		$this->assertInstanceOf(
			Factbox::class,
			new Factbox( $store, $parserData )
		);
	}

	public function testGetContent() {

		$text = __METHOD__;

		$parserData = new ParserData(
			Title::newFromText( __METHOD__ ),
			new ParserOutput()
		);

		$store = $this->getMockBuilder( '\SMW\Store' )
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		// Build Factbox stub object to encapsulate the method
		// without the need for other dependencies to occur
		$instance = $this->getMockBuilder( '\SMW\Factbox\Factbox' )
			->setConstructorArgs( [
				$store,
				$parserData
			] )
			->setMethods( [ 'fetchContent', 'getMagicWords' ] )
			->getMock();

		$instance->expects( $this->any() )
			->method( 'getMagicWords' )
			->will( $this->returnValue( 'Lula' ) );

		$instance->expects( $this->any() )
			->method( 'fetchContent' )
			->will( $this->returnValue( $text ) );

		$this->assertFalse( $instance->isVisible() );

		$instance->doBuild();

		$this->assertInternalType(
			'string',
			$instance->getContent()
		);

		$this->assertEquals(
			$text,
			$instance->getContent()
		);

		$this->assertTrue( $instance->isVisible() );
	}

	public function testGetAttachmentContent() {

		$dataItem = $this->getMockBuilder( '\SMWDataItem' )
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		$store = $this->getMockBuilder( '\SMW\Store' )
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		$store->expects( $this->any() )
			->method( 'getPropertyValues' )
			->will( $this->returnValue( [ $dataItem ] ) );

		$parserOutput = $this->getMockBuilder( '\ParserOutput' )
			->disableOriginalConstructor()
			->getMock();

		$title = Title::newFromText( __METHOD__ );

		$instance = new Factbox(
			$store,
			new ParserData( $title, $parserOutput )
		);

		$instance->setAttachments(
			[
				DIWikiPage::newFromText( 'Foo', NS_FILE )
			]
		);

		$instance->setFeatureSet( SMW_FACTBOX_DISPLAY_ATTACHMENT );
		$attachmentContent = $instance->getAttachmentContent();

		$this->assertContains(
			__METHOD__,
			$attachmentContent
		);

		$this->assertContains(
			'|Foo]]',
			$attachmentContent
		);
	}

	public function testGetContentRoundTripForNonEmptyContent() {

		$subject = DIWikiPage::newFromTitle( Title::newFromText( __METHOD__ ) );

		$this->testEnvironment->addConfiguration( 'smwgShowFactbox', SMW_FACTBOX_NONEMPTY );

		$store = $this->getMockBuilder( '\SMW\Store' )
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		$semanticData = $this->getMockBuilder( '\SMW\SemanticData' )
			->disableOriginalConstructor()
			->getMock();

		$semanticData->expects( $this->any() )
			->method( 'getSubject' )
			->will( $this->returnValue( $subject ) );

		$semanticData->expects( $this->any() )
			->method( 'hasVisibleProperties' )
			->will( $this->returnValue( true ) );

		$semanticData->expects( $this->any() )
			->method( 'getPropertyValues' )
			->will( $this->returnValue( [ $subject ] ) );

		$semanticData->expects( $this->any() )
			->method( 'getProperties' )
			->will( $this->returnValue( [ DIProperty::newFromUserLabel( 'SomeFancyProperty' ) ] ) );

		$parserOutput = $this->setupParserOutput( $semanticData );

		$instance = new Factbox(
			$store,
			new ParserData( $subject->getTitle(), $parserOutput )
		);

		$result = $instance->doBuild()->getContent();

		$this->assertInternalType(
			'string',
			$result
		);

		$this->assertContains(
			$subject->getDBkey(),
			 $result
		);

		$this->assertEquals(
			$subject->getTitle(),
			$instance->getTitle()
		);
	}

	public function testCreateTable() {

		$parserData = new ParserData(
			Title::newFromText( __METHOD__ ),
			new ParserOutput()
		);

		$store = $this->getMockBuilder( '\SMW\Store' )
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		$instance = new Factbox( $store, $parserData );

		$reflector = new ReflectionClass( '\SMW\Factbox\Factbox' );
		$createTable  = $reflector->getMethod( 'createTable' );
		$createTable->setAccessible( true );

		$this->assertInternalType(
			'string',
			$createTable->invoke( $instance, $parserData->getSemanticData() )
		);
	}

	public function testTabs() {

		$this->assertContains(
			'tab-facts-list',
			Factbox::tabs( 'Foo' )
		);

		$this->assertContains(
			'tab-facts-attachment',
			Factbox::tabs( 'Foo', 'Bar' )
		);

		$this->assertContains(
			'tab-facts-derived',
			Factbox::tabs( 'Foo', 'Bar','Foobar' )
		);
	}

	/**
	 * @dataProvider fetchContentDataProvider
	 */
	public function testFetchContent( $parserData ) {

		$store = $this->getMockBuilder( '\SMW\Store' )
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		$instance = new Factbox(
			$store,
			$parserData
		);

		$reflector = new ReflectionClass( '\SMW\Factbox\Factbox' );

		$fetchContent = $reflector->getMethod( 'fetchContent' );
		$fetchContent->setAccessible( true );

		$this->assertInternalType(
			'string',
			$fetchContent->invoke( $instance, SMW_FACTBOX_NONEMPTY )
		);

		$this->assertEmpty(
			$fetchContent->invoke( $instance, SMW_FACTBOX_HIDDEN )
		);
	}

	/**
	 * @dataProvider contentDataProvider
	 */
	public function testGetContentDataSimulation( $setup, $expected ) {

		$semanticData = $this->getMockBuilder( '\SMW\SemanticData' )
			->disableOriginalConstructor()
			->getMock();

		$semanticData->expects( $this->any() )
			->method( 'hasVisibleSpecialProperties' )
			->will( $this->returnValue( $setup['hasVisibleSpecialProperties'] ) );

		$semanticData->expects( $this->any() )
			->method( 'hasVisibleProperties' )
			->will( $this->returnValue( $setup['hasVisibleProperties'] ) );

		$semanticData->expects( $this->any() )
			->method( 'isEmpty' )
			->will( $this->returnValue( $setup['isEmpty'] ) );

		$store = $this->getMockBuilder( '\SMW\Store' )
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		$store->expects( $this->any() )
			->method( 'getSemanticData' )
			->will( $this->returnValue( $semanticData ) );

		$parserData = $this->getMockBuilder( '\SMW\ParserData' )
			->disableOriginalConstructor()
			->getMock();

		$parserData->expects( $this->any() )
			->method( 'getSubject' )
			->will( $this->returnValue( DIWikiPage::newFromText( __METHOD__ ) ) );

		$parserData->expects( $this->any() )
			->method( 'getSemanticData' )
			->will( $this->returnValue( null ) );

		// Build Factbox stub object to encapsulate the method
		// without the need for other dependencies to occur
		$factbox = $this->getMockBuilder( '\SMW\Factbox\Factbox' )
			->setConstructorArgs( [
				$store,
				$parserData
			] )
			->setMethods( [ 'createTable' ] )
			->getMock();

		$factbox->expects( $this->any() )
			->method( 'createTable' )
			->will( $this->returnValue( $setup['invokedContent'] ) );

		$reflector = new ReflectionClass( '\SMW\Factbox\Factbox' );
		$fetchContent = $reflector->getMethod( 'fetchContent' );
		$fetchContent->setAccessible( true );

		$this->assertInternalType(
			'string',
			$fetchContent->invoke( $factbox )
		);

		$this->assertEquals(
			$expected,
			$fetchContent->invoke( $factbox, $setup['showFactbox'] )
		);
	}

	/**
	 * Conditional content switcher to test combinations of
	 * SMW_FACTBOX_NONEMPTY and SMWSemanticData etc.
	 *
	 * @return array
	 */
	public function contentDataProvider() {

		$text = __METHOD__;
		$provider = [];

		$provider[] = [
			[
				'hasVisibleSpecialProperties' => true,
				'hasVisibleProperties'        => true,
				'isEmpty'                     => false,
				'showFactbox'                 => SMW_FACTBOX_NONEMPTY,
				'invokedContent'              => $text,
			],
			$text // expected return
		];

		$provider[] = [
			[
				'hasVisibleSpecialProperties' => true,
				'hasVisibleProperties'        => true,
				'isEmpty'                     => true,
				'showFactbox'                 => SMW_FACTBOX_NONEMPTY,
				'invokedContent'              => $text,
			],
			$text // expected return
		];

		$provider[] = [
			[
				'hasVisibleSpecialProperties' => false,
				'hasVisibleProperties'        => true,
				'isEmpty'                     => false,
				'showFactbox'                 => SMW_FACTBOX_SPECIAL,
				'invokedContent'              => $text,
			],
			'' // expected return
		];

		$provider[] = [
			[
				'hasVisibleSpecialProperties' => false,
				'hasVisibleProperties'        => false,
				'isEmpty'                     => false,
				'showFactbox'                 => SMW_FACTBOX_NONEMPTY,
				'invokedContent'              => $text,
			],
			'' // expected return
		];

		$provider[] = [
			[
				'hasVisibleSpecialProperties' => true,
				'hasVisibleProperties'        => false,
				'isEmpty'                     => false,
				'showFactbox'                 => SMW_FACTBOX_NONEMPTY,
				'invokedContent'              => $text,
			],
			'' // expected return
		];

		return $provider;
	}

	public function testGetTableHeader() {

		$title = Title::newFromText( __METHOD__ );

		$parserData = new ParserData(
			$title,
			new ParserOutput()
		);

		$parserData->setSemanticData( new SemanticData( DIWikiPage::newFromTitle( $title ) ) );
		$parserData->getSemanticData()->addPropertyObjectValue(
			new DIProperty( 'Foo' ),
			DIWikiPage::newFromTitle( $title )
		);

		$store = $this->getMockBuilder( '\SMW\Store' )
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		$instance = new Factbox( $store, $parserData );

		$this->stringValidator->assertThatStringContains(
			[
				'div class="smwrdflink"'
			],
			$instance->doBuild()->getContent()
		);
	}

	/**
	 * @dataProvider tableContentDataProvider
	 */
	public function testGetTableContent( $test, $expected ) {

		$title = Title::newFromText( __METHOD__ );

		$parserData = new ParserData(
			$title,
			new ParserOutput()
		);

		$store = $this->getMockBuilder( '\SMW\Store' )
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		$property = $this->getMockBuilder( '\SMW\DIProperty' )
			->disableOriginalConstructor()
			->getMock();

		$property->expects( $this->any() )
			->method( 'isUserDefined' )
			->will( $this->returnValue( $test['isUserDefined'] ) );

		$property->expects( $this->any() )
			->method( 'findPropertyTypeID' )
			->will( $this->returnValue( '_wpg' ) );

		$property->expects( $this->any() )
			->method( 'isShown' )
			->will( $this->returnValue( $test['isShown'] ) );

		$property->expects( $this->any() )
			->method( 'getLabel' )
			->will( $this->returnValue( 'Quuey' ) );

		$property->expects( $this->any() )
			->method( 'getDIType' )
			->will( $this->returnValue( \SMWDataItem::TYPE_PROPERTY ) );

		$parserData->setSemanticData(
			new SemanticData( DIWikiPage::newFromTitle( $title ) )
		);

		$parserData->getSemanticData()->addPropertyObjectValue(
			$property,
			DIWikiPage::newFromTitle( $title )
		);

		$instance = new Factbox( $store, $parserData );

		$this->stringValidator->assertThatStringContains(
			$expected,
			$instance->doBuild()->getContent()
		);
	}

	public function tableContentDataProvider() {

		$provider = [];

		$provider[] = [
			[
				'isShown'       => true,
				'isUserDefined' => true,
			],
			[ 'class="smw-table-cell smwprops"' ]
		];

		$provider[] = [
			[
				'isShown'       => false,
				'isUserDefined' => true,
			],
			''
		];

		$provider[] = [
			[
				'isShown'       => true,
				'isUserDefined' => false,
			],
			[ 'class="smw-table-cell smwspecs"' ]
		];

		$provider[] = [
			[
				'isShown'       => false,
				'isUserDefined' => false,
			],
			''
		];

		return $provider;
	}

	/**
	 * @return array
	 */
	public function fetchContentDataProvider() {

		$title = Title::newFromText( __METHOD__ );

		$provider = [];

		$semanticData = $this->getMockBuilder( '\SMW\SemanticData' )
			->disableOriginalConstructor()
			->getMock();

		$semanticData->expects( $this->any() )
			->method( 'getPropertyValues' )
			->will( $this->returnValue( [] ) );

		$semanticData->expects( $this->any() )
			->method( 'isEmpty' )
			->will( $this->returnValue( false ) );

		$parserData = new ParserData(
			$title,
			new ParserOutput()
		);

		$parserData->setSemanticData( $semanticData );

		$provider[] = [ $parserData ];

		$semanticData = $this->getMockBuilder( '\SMW\SemanticData' )
			->disableOriginalConstructor()
			->getMock();

		$semanticData->expects( $this->any() )
			->method( 'getPropertyValues' )
			->will( $this->returnValue( [ new DIProperty( '_SKEY') ] ) );

		$semanticData->expects( $this->any() )
			->method( 'isEmpty' )
			->will( $this->returnValue( false ) );

		$parserData = new ParserData(
			$title,
			new ParserOutput()
		);

		$parserData->setSemanticData( $semanticData );

		$provider[] = [ $parserData ];

		return $provider;
	}

	protected function setupParserOutput( $semanticData ) {

		$parserOutput = new ParserOutput();

		if ( method_exists( $parserOutput, 'setExtensionData' ) ) {
			$parserOutput->setExtensionData( 'smwdata', $semanticData );
		} else {
			$parserOutput->mSMWData = $semanticData;
		}

		return $parserOutput;
	}

}

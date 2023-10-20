<?php

namespace Drupal\Tests\easyjob_api\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Test the EasyjobApiService.
 *
 * @group easyjob_api
 */
class EasyjobApiServiceTest extends KernelTestBase implements ServiceModifierInterface {

  private const TOKEN_RESPONSE = '{"access_token": "dummy-token-_AQAAANCMnd8BFdERjHoAwE_dummy-test-token-a8-8HJyBISw","token_type": "bearer","expires_in": 172799}';

  private const WEB_SETTINGS_RESPONSE = '{"AccessKey":-1645105282,"UserName":"testuser","UserDisplayName":"testUserDisplayName","IsejUser":true,"HasWebAppLicense":true,"HasFreelancerLicense":false,"LNG":0,"GlobalizeCulture":"de-CH","IdUser":67,"IdAddress":0,"IdShortCutUserImage":0,"LengthUnit":"m","WeightUnit":"kg","IsTimeCardUser":false,"AllowActivityView":true,"AllowActivityEdit":false,"AllowMDAddressView":true,"AllowProjectView":true,"AllowProjectAdd":true,"AllowMDItemView":true,"AllowAvailabiltyView":true,"AllowMDOwnerView":true,"AllowMDDocumentView":true,"AllowBarcodeScan":true,"AllowServiceAdd":false,"AllowItemNetValuesView":false,"AllowEditOwnResourceStates":false,"SendGeoLocationOnTCAStart":false,"AllowLeadEdit":false,"UseAdvancedDashboard":false,"AllowRoomplanView":false,"AllowOBUView":false,"CategoryBackslashMode":true,"AvailInclProposal":true,"AllowMailTracking":false,"AllowOsET":false,"WorkflowEnabled":false,"UserFilterEnabled":false,"AllowProjectDashboardConfig":false,"Currencies":[{"ID":1,"IdCurrency":1,"Symbol":"CHF","ThreeLetterCode":"CHF"}],"IdCurrencyUser":1,"AllowPush":false}';

  private const SHORT_LIST_PRODUCTS_RESPONSE = '[{"IdStockType":11801,"IdStockTypeCategoryParent":37,"IdStockTypeCategory":249,"CategoryParent":"Test category","Number":"1000802.00","Caption":"Test caption 1"},{"IdStockType":12315,"IdStockTypeCategoryParent":37,"IdStockTypeCategory":249,"CategoryParent":"Verleihartikel","Category":"Test category","Number":"1001301.00","Caption":"Test caption"}]';

  private const PRODUCTS_EDITED_SINCE_RESPONSE = '[{"ID":10309,"Bearbeitet":"2020-05-19T16:17:00","Publiziert":false,"Deaktiviert":true},{"ID":10312,"Bearbeitet":"2020-05-26T16:00:00","Publiziert":false,"Deaktiviert":false}]';

  private const SINGLE_PRODUCT_DETAILS_RESPONSE = '{"ID":10309,"Nummer":"1000001.00","EigeneNummer":"6182","Titel":"TestTitel","Bearbeitet":"2020-05-19T16:17:00","Publiziert":false,"Mutterwarengruppe":"Information","Warengruppe":"Textbaustein","Produktkategorie1":null,"Produktkategorie2":null,"Produktkategorie3":null,"Beschreibung":null,"Zusatzinformationen":null,"Breite":0.000000,"Hoehe":0.000000,"Tiefe":0.000000,"Volumen":0.000000,"Weight":0.000000,"Durchmesser":null,"Fuellmenge":null,"Linie":null,"Typ":null,"Energiequelle":null,"Farbe":null,"Form":null,"Preiskategorie":null,"Lagerstandort1":null,"Lagerstandort2":null,"Lagerstandort3":null,"Verkaufspreis":0.0000,"Vermietpreis":0.0000,"Vermietartikel":true,"Verkaufsartikel":true,"Verbrauchsartikel":false,"Scheinleistung":0.0000,"Wirkleistung":0.0000,"Verpackungseinheit":1,"Einheit":"Stück","PowerConnection":null,"MobiliarEigenschaften":null,"GastroEigenschaften":null,"Material":null,"Verwendungszweck":null,"Produktstatus":null,"Stil":null,"Saison":null,"Anlass":null,"Motto":null,"ReferenzenGebunden":null,"ReferenzenNormal":null,"DazuPassend":null,"Modellreihe":null,"Farbvarianten":null,"Alternativen":null,"Bild":null,"Anhaenge":null,"ExterneUrl":null,"Deaktiviert":true,"Stil":null,"Saison":null,"Anlass":null,"Motto":null,"ReferenzenGebunden":null,"ReferenzenNormal":null,"DazuPassend":null,"Modellreihe":null,"Farbvarianten":null,"Alternativen":null,"Bild":null,"Anhaenge":null,"ExterneUrl":null,"Deaktiviert":true}';

  private const SINGLE_FILE_DETAIL_RESPONSE = 'random_encoded_file_content';

  private const PRODUCT_AVAILABILITY_FOR_PERIOD_RESPONSE = '{"MinAvail":80,"Price":0.00}';

  private const CREATE_PROJECT_RESPONSE = '{"IdJob":111148,"IdAddressCustomer":84565,"IdAddressDelivery":84565,"ErrorMessage":null}';

  private const GET_PROJECT_RESPONSE = '{"TeamsTeamID":"","TeamsChannelID":"","AllowHeadEdit":true,"AllowDocumentView":true,"AllowTimeCardView":false,"AllowPriceView":true,"AllowActivityView":false,"AllowJobAdd":true,"AllowCostplanView":true,"AllowDashboardView":false,"HtmlColor":"#FCCAB8","CurrencySymbol":"CHF","IdProject":85989,"Number":"23-6741","CustomNumber":"","Caption":"TEST - Projektname","ProjectState":{"ID":3,"IdProjectState":3,"Caption":"Abgesagt","Color":-210248},"StartDate":"2024-10-18T21:07:00","EndDate":"2024-10-19T21:07:00","Address_Customer":{"ID":84565,"IdAddress":84565,"Number":"1079330.00","Company":"TEST Firmenname","FirstName":"TEST Vorame","Surname":"TEST Name","Street":"TEST Straße","Street2":"TEST Adresse Zusatz","Zip":"TEST Zip","City":"TEST City","Phone":"TEST Telefon","PhoneCompany":"","Fax":"","EMail":"TEST E-Mail","WWWAdress":"","Country":null,"PrimaryContact":{"ID":91618,"IdContact":91618,"Surname":"TEST Name","FirstName":"TEST Vorame","Phone":"","Fax":"","Mobile":"","Email":"","PhonePrivate":""},"Name2":"TEST Firma "},"Contact_Customer":null,"Jobs":{},"Stock":{},"Arranger":{},"Comment":"Test comment","ID":"85989"}';

  private const GET_JOB_RESPONSE = '{"AllowHeadEdit":true,"AllowItemView":true,"AllowItemEdit":true,"AllowResourceView":false,"AllowResourceEdit":false,"AllowResourceAllocationView":false,"AllowFixedCostView":true,"AllowFixedCostEdit":false,"AllowPriceView":true,"AllowActivityView":false,"AllowCostplanView":true,"AllowCostplanEdit":false,"DeliveryNoteSignUrl":null,"AllowStaffTimeRecoring":false,"AllowEditFreeLines":false,"HtmlColor":"#FCCAB8","CurrencySymbol":"CHF","IdJob":111148,"Project":{"IdProject":85989,"ID":85989},"Number":"23-6741.01","CustomNumber":"","Caption":"TEST - Projektname","JobState":{"ID":3,"IdJobState":3,"Caption":"Abgesagt","Color":-210248,"IdJobType":1},"DayTimeOut":"2024-10-18T21:07:00","DayTimeIn":"2024-10-19T21:07:00","Address_Delivery":{"ID":84565,"IdAddress":84565,"Number":"1079330.00","Company":"TEST Firmenname","FirstName":"TEST Vorame","Surname":"TEST Name","Street":"TEST Straße","Street2":"TEST Adresse Zusatz","Zip":"TEST Zip","City":"TEST City","Phone":"TEST Telefon","PhoneCompany":"","Fax":"","EMail":"TEST E-Mai"},"Contact_Delivery":{},"Stock":{},"DeliveryAddressNote":"","Arranger":{},"Comment":"","ID":"111148"}';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['easyjob_api'];

  /**
   * Easyjob API Service.
   *
   * @var Drupal\Tests\easyjob_api\Kernel\TestEasyjobApiService
   */
  protected $easyjobApiService;

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $service_definition = $container->getDefinition('easyjob_api.client');
    $service_definition->setClass(TestEasyjobApiService::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set the necessary easyjob_api configuration.
    $config = $this->config('easyjob_api.settings');
    $config->set('username', 'testuser');
    $config->set('password', 'testpass');
    $config->save();

    // Setup the easyjobApiService.
    $this->easyjobApiService = $this->container->get('easyjob_api.client');
    $responses = [
      new Response(200, [], self::TOKEN_RESPONSE),
    ];
    $this->setUpClient($responses);
    $this->easyjobApiService->getToken();
  }

  /**
   * Sets up the client.
   *
   * @param GuzzleHttp\Psr7\Response[] $responses
   *   The mocked responses array.
   */
  protected function setUpClient($responses) {
    $mock = new MockHandler($responses);
    $handler_stack = HandlerStack::create($mock);
    $client = new Client(['handler' => $handler_stack]);
    $this->easyjobApiService->setClient($client);
  }

  /**
   * Test on 404 Not Found response.
   */
  public function testGetWebSettingsOn404NotFound() {
    $responses = [
      new Response(404),
    ];
    $this->setUpClient($responses);
    $response = $this->easyjobApiService->getWebSettings();
    self::assertFalse($response);
  }

  /**
   * Test Get Websettings.
   */
  public function testGetWebsettings() {
    $responses = [
      new Response(200, [], self::WEB_SETTINGS_RESPONSE),
    ];
    $this->setUpClient($responses);
    $response = $this->easyjobApiService->getWebSettings();
    self::assertIsArray($response);
    self::assertArrayHasKey('AccessKey', $response);
    self::assertArrayHasKey('UserDisplayName', $response);
    self::assertArrayHasKey('Currencies', $response);
    self::assertIsArray($response['Currencies']);
    self::assertEquals(count($response), 42);
  }

  /**
   * Test getShortListProducts status code non 200.
   */
  public function testGetShortListProductsNon200StatusCode() {
    $responses = [new Response(202)];
    $this->setUpClient($responses);
    $response = $this->easyjobApiService->getShortListProducts();
    $this->assertFalse($response);
  }

  /**
   * Test getShortListProducts.
   */
  public function testGetShortListProducts() {
    $responses = [
      new Response(200, [], self::SHORT_LIST_PRODUCTS_RESPONSE),
    ];
    $this->setUpClient($responses);
    $response = $this->easyjobApiService->getShortListProducts();
    $this->assertIsArray($response);
    $this->assertEquals(count($response), 2);
    $this->assertIsArray($response[0]);
    $this->assertArrayHasKey('IdStockType', $response[0]);
    $this->assertEquals($response[0]['Number'], '1000802.00');
  }

  /**
   * Test getProductsEditedSince status code non 200.
   */
  public function testGetProductsEditedSinceNon200StatusCode() {
    $responses = [new Response(501)];
    $this->setUpClient($responses);
    $response = $this->easyjobApiService->getProductsEditedSince(0);
    $this->assertFalse($response);
  }

  /**
   * Test getProductsEditedSince.
   */
  public function testGetProductsEditedSince() {
    $responses = [
      new Response(200, [], self::PRODUCTS_EDITED_SINCE_RESPONSE),
    ];
    $this->setUpClient($responses);
    $response = $this->easyjobApiService->getProductsEditedSince(0);
    $this->assertIsArray($response);
    $this->assertEquals(count($response), 2);
    $this->assertIsArray($response[0]);
    $this->assertArrayHasKey('Bearbeitet', $response[0]);
    $this->assertArrayHasKey('Deaktiviert', $response[1]);
    $this->assertEquals($response[0]['Publiziert'], FALSE);
  }

  /**
   * Test getSingleProductDetail status code non 200.
   */
  public function testGetSingleProductDetailNon200StatusCode() {
    $responses = [new Response(501)];
    $this->setUpClient($responses);
    $response = $this->easyjobApiService->getSingleProductDetail('3333994998');
    $this->assertFalse($response);
  }

  /**
   * Test getSingleProductDetail.
   */
  public function testGetSingleProductDetail() {
    $responses = [
      new Response(200, [], self::SINGLE_PRODUCT_DETAILS_RESPONSE),
    ];
    $this->setUpClient($responses);
    $response = $this->easyjobApiService->getSingleProductDetail('10309');
    $this->assertIsArray($response);
    $this->assertEquals(count($response), 58);
    $this->assertArrayHasKey('ID', $response);
    $this->assertArrayHasKey('Warengruppe', $response);
    $this->assertEquals($response['Farbvarianten'], NULL);
  }

  /**
   * Test getSingleFileDetail status code non 200.
   */
  public function testGetSingleFileDetailNon200StatusCode() {
    $responses = [new Response(404)];
    $this->setUpClient($responses);
    $response = $this->easyjobApiService->getSingleFileDetail('3333994998');
    $this->assertFalse($response);
  }

  /**
   * Test getSingleFileDetail.
   */
  public function testGetSingleFileDetail() {
    $responses = [
      new Response(
        200,
        [
          'Content-Length' => 34567,
          'content-disposition' => 'attachment;filename="test-article-image.jpg"',
        ],
        self::SINGLE_FILE_DETAIL_RESPONSE
      ),
    ];
    $this->setUpClient($responses);
    $response = $this->easyjobApiService->getSingleFileDetail('436886');
    $this->assertIsArray($response);
    $this->assertArrayHasKey('filename', $response);
    $this->assertArrayHasKey('content', $response);
    $this->assertEquals($response['filename'], "\"test-article-image.jpg\"");
    $this->assertEquals($response['content'], "random_encoded_file_content");
  }

  /**
   * Test getProductsDetail status code non 200.
   */
  public function testGetProductsDetailNon200StatusCode() {
    $responses = [
      new Response(403, [], self::SINGLE_PRODUCT_DETAILS_RESPONSE),
      new Response(403, [], str_replace('10309', '10312', self::SINGLE_PRODUCT_DETAILS_RESPONSE)),
    ];
    $this->setUpClient($responses);
    $product_ids = json_decode(self::PRODUCTS_EDITED_SINCE_RESPONSE, TRUE);
    $response = $this->easyjobApiService->getProductsDetail($product_ids);
    $this->assertEmpty($response);
  }

  /**
   * Test getProductsDetail.
   */
  public function testGetProductsDetail() {
    $responses = [
      new Response(200, [], self::SINGLE_PRODUCT_DETAILS_RESPONSE),
      new Response(200, [], str_replace('10309', '10312', self::SINGLE_PRODUCT_DETAILS_RESPONSE)),
    ];
    $this->setUpClient($responses);
    $product_ids = json_decode(self::PRODUCTS_EDITED_SINCE_RESPONSE, TRUE);
    $response = $this->easyjobApiService->getProductsDetail($product_ids);
    $this->assertIsArray($response);
    $this->assertEquals(count($response), 2);
    $this->assertIsArray($response[0]);
    $this->assertIsArray($response[1]);
    $this->assertArrayHasKey('Mutterwarengruppe', $response[0]);
    $this->assertArrayHasKey('Produktkategorie1', $response[1]);
    $this->assertArrayHasKey('Verkaufspreis', $response[0]);
    $this->assertArrayHasKey('Verbrauchsartikel', $response[1]);
    $this->assertEquals($response[0]['Nummer'], '1000001.00');
    $this->assertEquals($response[1]['Warengruppe'], 'Textbaustein');
  }

  /**
   * Test getProductAvailabilityForPeriod status code non 200.
   */
  public function testGetProductAvailabilityForPeriodNon200StatusCode() {
    $responses = [new Response(404)];
    $this->setUpClient($responses);
    $args = [
      'product_id' => '10309',
      'start' => '2033-10-17',
      'end' => '2033-10-24',
      'quantity' => '2',
    ];
    $response = $this->easyjobApiService->getProductAvailabilityForPeriod($args);
    $this->assertFalse($response);
  }

  /**
   * Test getProductAvailabilityForPeriod.
   */
  public function testGetProductAvailabilityForPeriod() {
    $responses = [
      new Response(200, [], self::PRODUCT_AVAILABILITY_FOR_PERIOD_RESPONSE),
    ];
    $this->setUpClient($responses);
    $args = [
      'product_id' => '10309',
      'start' => '2033-10-17',
      'end' => '2033-10-24',
      'quantity' => '2',
    ];
    $response = $this->easyjobApiService->getProductAvailabilityForPeriod($args);
    $this->assertIsArray($response);
    $this->assertArrayHasKey('MinAvail', $response);
    $this->assertArrayHasKey('Price', $response);
    $this->assertEquals($response['MinAvail'], 80);
    $this->assertEquals($response['Price'], 0);
  }

  /**
   * Test createProject status code non 200.
   */
  public function testCreateProjectNon200StatusCode() {
    $this->expectException(\Exception::class);
    $responses = [new Response(404)];
    $this->setUpClient($responses);
    $order_data = [];
    $order_data['Items'] = [
      [
        'ID' => '10309',
        'Quantity' => 5,
        'Price' => 0,
      ],
      [
        'ID' => '10312',
        'Quantity' => 5,
        'Price' => 0,
      ],
    ];
    $this->easyjobApiService->createProject($order_data);
  }

  /**
   * Test createProject no data provided.
   */
  public function testCreateProjectNoDataProvided() {
    $this->expectException(\Exception::class);
    $this->setUpClient([]);
    $this->easyjobApiService->createProject([]);
  }

  /**
   * Test createProject.
   */
  public function testCreateProject() {
    $responses = [
      new Response(200, [], self::CREATE_PROJECT_RESPONSE),
    ];
    $this->setUpClient($responses);

    $startDate = date('Y-m-d\TH:i:s', strtotime('+1 year'));
    $endDate = date('Y-m-d\TH:i:s', strtotime('+1 year +1 day'));
    $order_data = [
      'ID' => '100000',
      'ProjectName' => 'TEST - Projektname',
      'StartDate' => $startDate,
      'EndDate' => $endDate,
      'CustomerComment' => 'TEST BESTELLUNG',
      'PaymentAmount' => 123.45,
      'PaymentMethod' => 'Kreditkarte',
      'ProjectState' => '0',
      'Service' => '0',
    ];

    $customer_address = [
      'Company' => 'TEST Firmenname',
      'Name2' => 'TEST Firma Zusatz',
      'Street' => 'TEST Straße',
      'Street2' => 'TEST Adresse Zusatz',
      'Zip' => 'TEST Zip',
      'City' => 'TEST City',
      'Fax' => '',
      'Phone' => 'TEST Telefon',
      'EMail' => 'TEST E-Mail',
      'WWWAdress' => '',
      'Country' => [
        'Caption' => 'ch',
      ],
      'PrimaryContact' => [
        'FirstName' => 'TEST Vorame',
        'Surname' => 'TEST Name',
      ],
    ];
    $order_data['CustomerAddress'] = $customer_address;
    $order_data['DeliveryAddress'] = $customer_address;
    $order_data['InvoiceAddress'] = $customer_address;
    $order_data['Items'] = [
      [
        'ID' => '10309',
        'Quantity' => 5,
        'Price' => 0,
      ],
      [
        'ID' => '10312',
        'Quantity' => 5,
        'Price' => 0,
      ],
    ];

    $project_ids = $this->easyjobApiService->createProject($order_data);
    $this->assertIsArray($project_ids);
    $this->assertCount(4, $project_ids);
    $this->assertArrayHasKey('IdJob', $project_ids);
    $this->assertArrayHasKey('IdAddressCustomer', $project_ids);
    $this->assertArrayHasKey('IdAddressDelivery', $project_ids);
    $this->assertArrayHasKey('ErrorMessage', $project_ids);
    $this->assertEquals($project_ids['IdAddressCustomer'], 84565);
    $this->assertNull($project_ids['ErrorMessage']);
  }

  /**
   * Test getProject no project id.
   */
  public function testGetProjectNoProjectId() {
    $this->expectException(\Exception::class);
    $this->setUpClient([]);
    $this->easyjobApiService->getProject(NULL);
  }

  /**
   * Test getProject status code non 200.
   */
  public function testGetProjectNon200StatusCode() {
    $this->expectException(\Exception::class);
    $responses = [
      new Response(501),
    ];
    $this->setUpClient($responses);
    $this->easyjobApiService->getProject('3334');
  }

  /**
   * Test getProject.
   */
  public function testGetProject() {
    $responses = [
      new Response(200, [], self::GET_PROJECT_RESPONSE),
    ];
    $this->setUpClient($responses);
    $response = $this->easyjobApiService->getProject('85989');
    $this->assertIsArray($response);
    $this->assertIsArray($response['ProjectState']);
    $this->assertIsArray($response['Address_Customer']);
    $this->assertNull($response['Contact_Customer']);
    $this->assertIsArray($response['Stock']);
    $this->assertIsArray($response['Arranger']);
    $this->assertArrayHasKey('Number', $response);
    $this->assertArrayHasKey('ID', $response);
    $this->assertArrayHasKey('Comment', $response);
    $this->assertArrayHasKey('IdProject', $response);
    $this->assertArrayHasKey('Number', $response);
    $this->assertEquals($response['Number'], "23-6741");
  }

  /**
   * Test getJob no job id.
   */
  public function testGetJobNoJobId() {
    $this->expectException(\Exception::class);
    $this->setUpClient([]);
    $this->easyjobApiService->getJob(NULL);
  }

  /**
   * Test getJob status code non 200.
   */
  public function testGetJobNon200StatusCode() {
    $this->expectException(\Exception::class);
    $this->setUpClient([new Response(300)]);
    $this->easyjobApiService->getJob('111148');
  }

  /**
   * Test getJob.
   */
  public function testGetJob() {
    $responses = [
      new Response(200, [], self::GET_JOB_RESPONSE),
    ];
    $this->setUpClient($responses);
    $response = $this->easyjobApiService->getJob('111148');
    $this->assertIsArray($response);
    $this->assertCount(32, $response);
    $this->assertArrayHasKey('Number', $response);
    $this->assertArrayHasKey('JobState', $response);
    $this->assertArrayHasKey('ID', $response);
    $this->assertIsArray($response['Project']);
    $this->assertIsArray($response['JobState']);
    $this->assertArrayHasKey('ID', $response['JobState']);
    $this->assertArrayHasKey('IdJobState', $response['JobState']);
    $this->assertArrayHasKey('Caption', $response['JobState']);
    $this->assertIsArray($response['Address_Delivery']);
    $this->assertIsArray($response['Stock']);
    $this->assertIsArray($response['Arranger']);
  }

}

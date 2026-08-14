<?php

namespace Modules\Sviat\NovaPoshtaPopularCities;

use Okay\Core\Settings;
use Okay\Modules\Sviat\NovaPoshtaPopularCities\Helpers\NPCitiesApiHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Відбір міст із довідника Нової Пошти для списку популярних міст у чекауті.
 * У довіднику поряд із містами лежать села й селища — вони не мусять потрапити
 * у випадайку, інакше відвідувач обирає населений пункт, куди доставки немає.
 *
 * Транспорт підмінюється підкласом (request() зроблено protected), тож жодного
 * звернення до API не відбувається.
 */
class SettlementClassificationTest extends TestCase
{
    /** @param array<int, array<string, mixed>> $rows */
    private function buildHelper(array $rows, bool $success = true): NPCitiesApiHelper
    {
        $response = (object) [
            'success' => $success,
            'data'    => array_map(static fn (array $r) => (object) $r, $rows),
        ];

        return new class($response) extends NPCitiesApiHelper {
            public function __construct(private object $response)
            {
                parent::__construct(
                    (new \ReflectionClass(Settings::class))->newInstanceWithoutConstructor(),
                    new NullLogger()
                );
            }

            protected function request(array $requestParams)
            {
                return $this->response;
            }
        };
    }

    // --- відсів сіл ---------------------------------------------------------

    /** Села й селища відкидаються за описом типу — в обох мовах довідника. */
    /** @dataProvider villageTypeProvider */
    #[DataProvider('villageTypeProvider')]
    public function testVillagesAreExcluded(string $typeDescription): void
    {
        $helper = $this->buildHelper([[
            'SettlementTypeDescription' => $typeDescription,
            'Ref'                       => 'ref-1',
            'Description'               => 'Малі Копані',
        ]]);

        self::assertSame([], $helper->getSettlements());
    }

    public static function villageTypeProvider(): array
    {
        return [
            'село'                    => ['село'],
            'селище'                  => ['селище'],
            'селище міського типу'    => ['селище міського типу'],
            'selo транслітом'         => ['selo'],
            'selyshche транслітом'    => ['selyshche'],
            'великими літерами'       => ['СЕЛО'],
        ];
    }

    /**
     * Відсів сіл іде ПЕРШИМ і перекриває розпізнавання міста. Це важливо саме
     * для «селища міського типу»: рядок містить і «селище», і — у деяких
     * записах довідника — ознаки міста.
     */
    public function testVillageCheckWinsOverTheCityCheck(): void
    {
        $helper = $this->buildHelper([[
            'SettlementTypeDescription'        => 'селище міського типу',
            'SettlementTypeDescriptionTranslit' => 'selyshche mistoho typu',
            'Ref'                              => 'ref-1',
            'Description'                      => 'Гостомель',
        ]]);

        self::assertSame([], $helper->getSettlements());
    }

    // --- відбір міст --------------------------------------------------------

    /** @dataProvider cityTypeProvider */
    #[DataProvider('cityTypeProvider')]
    public function testCitiesAreIncluded(string $typeDescription): void
    {
        $helper = $this->buildHelper([[
            'SettlementTypeDescription' => $typeDescription,
            'Ref'                       => 'ref-1',
            'Description'               => 'Львів',
            'DescriptionTranslit'       => 'Lviv',
        ]]);

        self::assertSame(
            [['ref' => 'ref-1', 'city_name' => 'Львів', 'city_translit' => 'Lviv']],
            $helper->getSettlements()
        );
    }

    public static function cityTypeProvider(): array
    {
        return [
            'місто'            => ['місто'],
            'город'            => ['город'],
            'misto транслітом' => ['misto'],
            'великими літерами' => ['МІСТО'],
        ];
    }

    /**
     * Коли основний опис типу нерозпізнаваний, у діло йде транслітерований —
     * у довіднику трапляються записи, де заповнене лише одне з двох полів.
     */
    public function testTranslitFieldIsTheFallbackForTypeDetection(): void
    {
        $helper = $this->buildHelper([[
            'SettlementTypeDescription'         => 'city',
            'SettlementTypeDescriptionTranslit' => 'misto',
            'Ref'                               => 'ref-1',
            'Description'                       => 'Львів',
        ]]);

        self::assertCount(1, $helper->getSettlements());
    }

    /** Невідомий тип — не місто. Краще не показати, ніж показати недоставне. */
    public function testUnknownSettlementTypeIsNotACity(): void
    {
        $helper = $this->buildHelper([[
            'SettlementTypeDescription' => 'хутір',
            'Ref'                       => 'ref-1',
            'Description'               => 'Нове',
        ]]);

        self::assertSame([], $helper->getSettlements());
    }

    /** Запис без Ref або без назви пропускається — у списку від нього користі нуль. */
    /** @dataProvider incompleteCityProvider */
    #[DataProvider('incompleteCityProvider')]
    public function testIncompleteRecordsAreSkipped(array $row): void
    {
        $helper = $this->buildHelper([['SettlementTypeDescription' => 'місто'] + $row]);

        self::assertSame([], $helper->getSettlements());
    }

    public static function incompleteCityProvider(): array
    {
        return [
            'немає Ref'   => [['Description' => 'Львів']],
            'немає назви' => [['Ref' => 'ref-1']],
            'порожній Ref' => [['Ref' => '', 'Description' => 'Львів']],
        ];
    }

    public function testTranslitNameIsOptional(): void
    {
        $helper = $this->buildHelper([[
            'SettlementTypeDescription' => 'місто',
            'Ref'                       => 'ref-1',
            'Description'               => 'Львів',
        ]]);

        self::assertSame('', $helper->getSettlements()[0]['city_translit']);
    }

    /** Змішаний довідник: у результат їдуть лише міста, у вихідному порядку. */
    public function testMixedDirectoryKeepsOnlyCitiesInOrder(): void
    {
        $helper = $this->buildHelper([
            ['SettlementTypeDescription' => 'село', 'Ref' => 'r1', 'Description' => 'Малі Копані'],
            ['SettlementTypeDescription' => 'місто', 'Ref' => 'r2', 'Description' => 'Львів'],
            ['SettlementTypeDescription' => 'селище', 'Ref' => 'r3', 'Description' => 'Гостомель'],
            ['SettlementTypeDescription' => 'місто', 'Ref' => 'r4', 'Description' => 'Київ'],
        ]);

        self::assertSame(['Львів', 'Київ'], array_column($helper->getSettlements(), 'city_name'));
    }

    // --- невдала відповідь --------------------------------------------------

    public function testUnsuccessfulResponseGivesEmptyResult(): void
    {
        $helper = $this->buildHelper(
            [['SettlementTypeDescription' => 'місто', 'Ref' => 'r1', 'Description' => 'Львів']],
            success: false
        );

        self::assertSame([], $helper->getSettlements());
    }

    // --- пошук за назвою ----------------------------------------------------

    /** У пошуку по назві лишаються тільки записи з кодом типу «м.» — тобто міста. */
    public function testSearchKeepsOnlyCityTypeCodes(): void
    {
        $helper = $this->buildHelper([
            ['SettlementTypeCode' => 'м.', 'Ref' => 'r1', 'DeliveryCity' => 'd1', 'MainDescription' => 'Львів'],
            ['SettlementTypeCode' => 'с.', 'Ref' => 'r2', 'DeliveryCity' => 'd2', 'MainDescription' => 'Львівське'],
            ['SettlementTypeCode' => 'смт', 'Ref' => 'r3', 'DeliveryCity' => 'd3', 'MainDescription' => 'Львівка'],
            ['SettlementTypeCode' => ' м ', 'Ref' => 'r4', 'DeliveryCity' => 'd4', 'MainDescription' => 'Львовиця'],
        ]);

        self::assertSame(['Львів', 'Львовиця'], array_column($helper->searchSettlements('Львів'), 'city_name'));
    }

    /**
     * Порівняння назви регістронезалежне й ігнорує пробіли по краях — інакше
     * визначення міста за назвою з форми не спрацьовувало б на «львів».
     */
    /** @dataProvider cityNameSpellingProvider */
    #[DataProvider('cityNameSpellingProvider')]
    public function testCityRefLookupIgnoresCaseAndSurroundingSpace(string $query): void
    {
        $helper = $this->buildHelper([
            ['SettlementTypeCode' => 'м.', 'Ref' => 'r1', 'DeliveryCity' => 'delivery-ref', 'MainDescription' => 'Львів'],
        ]);

        self::assertSame('delivery-ref', $helper->getCityRefByName($query));
    }

    public static function cityNameSpellingProvider(): array
    {
        return [
            'як є'         => ['Львів'],
            'малими'       => ['львів'],
            'великими'     => ['ЛЬВІВ'],
            'із пробілами' => ['  Львів  '],
        ];
    }

    /** Частковий збіг не годиться — потрібна саме та сама назва. */
    public function testPartialNameMatchIsRejected(): void
    {
        $helper = $this->buildHelper([
            ['SettlementTypeCode' => 'м.', 'Ref' => 'r1', 'DeliveryCity' => 'd1', 'MainDescription' => 'Львівське'],
        ]);

        self::assertNull($helper->getCityRefByName('Львів'));
    }

    /** Запис без ref доставки не годиться — саме він потрібен для розрахунку. */
    public function testRecordWithoutADeliveryRefIsSkipped(): void
    {
        $helper = $this->buildHelper([
            ['SettlementTypeCode' => 'м.', 'Ref' => 'r1', 'DeliveryCity' => '', 'MainDescription' => 'Львів'],
        ]);

        self::assertNull($helper->getCityRefByName('Львів'));
    }
}

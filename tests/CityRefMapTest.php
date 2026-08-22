<?php

namespace Modules\Sviat\NovaPoshtaPopularCities;

use Okay\Core\Settings;
use Okay\Modules\Sviat\NovaPoshtaPopularCities\Helpers\CityNameMatcher;
use Okay\Modules\Sviat\NovaPoshtaPopularCities\Helpers\NPCitiesApiHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Зіставлення населеного пункту з містом доставки — за назвою, кирилицею.
 *
 * `getCities` розрізняє однойменні міста уточненням у дужках
 * («Золочів (Львівська обл.)»), а `getSettlements` віддає ту саму назву без
 * нього. Поки уточнення не знімалось, кожне таке місто не сходилось за назвою
 * і коштувало окремого запиту до API: на живому довіднику це були 61 виклик
 * замість 7, тобто близько одинадцяти зайвих секунд у запиті, який і так
 * упирався в таймаут шлюзу.
 */
class CityRefMapTest extends TestCase
{
    /** @dataProvider qualifierProvider */
    #[DataProvider('qualifierProvider')]
    public function testQualifierInBracketsIsDropped(string $fromCities, string $fromSettlements): void
    {
        $this->assertSame(
            CityNameMatcher::directoryNameKey($fromSettlements),
            CityNameMatcher::directoryNameKey($fromCities),
            "«{$fromCities}» і «{$fromSettlements}» — той самий населений пункт"
        );
    }

    public static function qualifierProvider(): array
    {
        return [
            'область'          => ['Золочів (Львівська обл.)', 'Золочів'],
            'без пробілу'      => ['Золочів(Харківська обл.)', 'Золочів'],
            'тип пункту'       => ['Бахмач (місто)', 'Бахмач'],
            'громада'          => ['Калинівка (Калинівська територіальна громада)', 'Калинівка'],
            'регістр'          => ['КИЇВ', 'Київ'],
            'подвійні пробіли' => ['Біла  Церква', 'Біла Церква'],
        ];
    }

    /** Уточнення знімається, але сама назва лишається значущою. */
    public function testDifferentCitiesDoNotCollapse(): void
    {
        self::assertNotSame(
            CityNameMatcher::directoryNameKey('Золочів (Львівська обл.)'),
            CityNameMatcher::directoryNameKey('Золочівка')
        );
    }

    /** Знімається лише кінцева дужка; текст перед нею — це і є назва. */
    public function testTextBeforeTheBracketsSurvives(): void
    {
        self::assertSame('горішні плавні', CityNameMatcher::directoryNameKey('Горішні Плавні (Комсомольськ) '));
        self::assertSame('нові петрівці', CityNameMatcher::directoryNameKey('Нові Петрівці (Вишгородський р-н)'));
    }

    /**
     * Назва з самих лише дужок дає порожній ключ. Це не помилка, а межа:
     * викликачі порожній ключ відкидають, інакше всі такі рядки злиплися б
     * в один і місто знайшлося б навмання.
     */
    public function testNameMadeOnlyOfBracketsGivesEmptyKey(): void
    {
        self::assertSame('', CityNameMatcher::directoryNameKey('(місто)'));
        self::assertSame([], $this->helper([1 => [['Ref' => 'r', 'Description' => '(місто)']]])->getCityRefsByName(3));
    }

    public function testMapKeysAreNormalisedAndFirstWins(): void
    {
        $helper = $this->helper([
            1 => [
                ['Ref' => 'ref-kyiv',      'Description' => 'Київ'],
                ['Ref' => 'ref-zolochiv',  'Description' => 'Золочів (Львівська обл.)'],
                ['Ref' => 'ref-zolochiv2', 'Description' => 'Золочів (Харківська обл.)'],
            ],
        ]);

        $map = $helper->getCityRefsByName(3);

        self::assertSame('ref-kyiv', $map['київ']);
        // Однойменні міста різних областей зводяться в один ключ — це відома
        // неоднозначність довідника, і перемагає перше. Головне, щоб місто
        // взагалі знайшлось, а не коштувало окремого запиту.
        self::assertSame('ref-zolochiv', $map['золочів']);
    }

    public function testWalksEveryPageUntilAnIncompleteOne(): void
    {
        $helper = $this->helper([
            1 => [['Ref' => 'a', 'Description' => 'Алушта'], ['Ref' => 'b', 'Description' => 'Балта']],
            2 => [['Ref' => 'c', 'Description' => 'Вараш'], ['Ref' => 'd', 'Description' => 'Гадяч']],
            3 => [['Ref' => 'e', 'Description' => 'Дубно']],
            4 => [['Ref' => 'f', 'Description' => 'Житомир']],
        ]);

        $map = $helper->getCityRefsByName(2);

        self::assertSame(['алушта', 'балта', 'вараш', 'гадяч', 'дубно'], array_keys($map));
    }

    public function testRowsWithoutRefOrNameAreSkipped(): void
    {
        $helper = $this->helper([
            1 => [
                ['Ref' => '',        'Description' => 'Без рефа'],
                ['Ref' => 'ref-ok',  'Description' => ''],
                ['Ref' => 'ref-two', 'Description' => 'Ніжин'],
            ],
        ]);

        self::assertSame(['ніжин' => 'ref-two'], $helper->getCityRefsByName(3));
    }

    /** @param array<int, array<int, array<string, string>>> $pages */
    private function helper(array $pages): NPCitiesApiHelper
    {
        return new class($pages) extends NPCitiesApiHelper {
            /** @param array<int, array<int, array<string, string>>> $pages */
            public function __construct(private array $pages)
            {
                parent::__construct(
                    (new \ReflectionClass(Settings::class))->newInstanceWithoutConstructor(),
                    new NullLogger()
                );
            }

            protected function request(array $requestParams)
            {
                $page = (int) $requestParams['methodProperties']['Page'];

                return (object) [
                    'success' => true,
                    'data'    => array_map(
                        static fn (array $row) => (object) $row,
                        $this->pages[$page] ?? []
                    ),
                ];
            }
        };
    }
}

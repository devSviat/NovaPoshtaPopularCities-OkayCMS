<?php

namespace Modules\Sviat\NovaPoshtaPopularCities;

use Okay\Modules\Sviat\NovaPoshtaPopularCities\Helpers\CityNameMatcher;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Місто відвідувача визначається за IP, і geo-IP сервіс віддає назву
 * транслітом. Далі вона зіставляється з довідником Нової Пошти — теж
 * транслітом, але записаним за іншою схемою.
 *
 * Точний збіг рядків тут не працює: `Sofiivska Borschahivka` з логу проду
 * і `Sofiivska Borshchahivka` з довідника — той самий населений пункт.
 * Розходяться на «щ» (shch/sch), пробілах, дефісах і апострофах, а частина
 * назв — ще й основою: `Kiev` проти `Kyiv`.
 */
class TranslitMatchingTest extends TestCase
{
    /** @dataProvider sameSettlementProvider */
    #[DataProvider('sameSettlementProvider')]
    public function testSpellingsOfTheSameSettlementMatch(string $fromGeoIp, string $fromDirectory): void
    {
        $this->assertTrue(
            CityNameMatcher::matches($fromGeoIp, $fromDirectory),
            "«{$fromGeoIp}» і «{$fromDirectory}» — той самий населений пункт"
        );
    }

    public static function sameSettlementProvider(): array
    {
        return [
            'щ як sch і shch'     => ['Sofiivska Borschahivka', 'Sofiivska Borshchahivka'],
            'дефіс проти пробілу' => ['Bila-Tserkva', 'Bila Tserkva'],
            'апостроф'            => ["Kam'yanets-Podilskyi", 'Kamyanets Podilskyi'],
            'регістр'             => ['KYIV', 'Kyiv'],
            'зайві пробіли'       => ['  Chabany  ', 'Chabany'],
            // Схеми розходяться на приголосних: українську «г» пишуть h або g,
            // «х» — kh або h, «и»/«і» — y або i. У довіднику НП трапляється
            // і те, і те.
            'г як g і h'          => ['Horodyshche', 'Gorodishche'],
            'х як kh і h'         => ['Bahmach', 'Bakhmach'],
            'и як y та i'         => ['Baturin', 'Baturyn'],
            'подвоєння'           => ['Odessa', 'Odesa'],
            'англійський підпис у довіднику' => ['Bershad', 'City of Bershad'],
            // Однойменні пункти НП розрізняє уточненням у дужках, і воно
            // потрапляє в саму транслітерацію. Сервіс віддає лише назву.
            'уточнення в дужках'  => ['Bakhmach', 'Bakhmach (misto)'],
            'довге уточнення'     => ['Kalynivka', 'Kalynivka (Kalynivska terytorialna hromada)'],
        ];
    }

    /**
     * Написання, через які місто не знаходилось: у логах проду це найчастіше
     * Київ. Праворуч — те, що справді лежить у довіднику Нової Пошти.
     *
     * @dataProvider knownAliasProvider
     */
    #[DataProvider('knownAliasProvider')]
    public function testKnownAlternativeSpellingsResolveToDirectory(string $fromGeoIp, string $fromDirectory): void
    {
        $this->assertTrue(
            CityNameMatcher::matches($fromGeoIp, $fromDirectory),
            "geo-IP віддає «{$fromGeoIp}», у довіднику «{$fromDirectory}»"
        );
    }

    public static function knownAliasProvider(): array
    {
        return [
            'Kiev'         => ['Kiev', 'Kyiv'],
            'Zaporizhzhya' => ['Zaporizhzhya', 'Zaporizhia'],
            'Zaporozhye'   => ['Zaporozhye', 'Zaporizhia'],
            'Kharkov'      => ['Kharkov', 'Kharkiv'],
            'Odessa'       => ['Odessa', 'Odesa'],
            'Lvov'         => ['Lvov', 'Lviv'],
            'Nikolaev'     => ['Nikolaev', 'Mykolaiv'],
            'Dnepr'        => ['Dnepropetrovsk', 'Dnipro'],
            'Chernigov'    => ['Chernigov', 'Chernihiv'],
            'Rovno'        => ['Rovno', 'Rivne'],
            'Kirovograd'   => ['Kirovograd', 'Kropyvnytskyi'],
            'Uzhgorod'     => ['Uzhgorod', 'Uzhhorod'],
            'Vinnytsya'    => ['Vinnytsya', 'Vinnytsia'],
        ];
    }

    /** Різні міста не мають злипатись — інакше знайдемо не те. */
    /** @dataProvider differentSettlementProvider */
    #[DataProvider('differentSettlementProvider')]
    public function testDifferentSettlementsStayDifferent(string $one, string $two): void
    {
        $this->assertFalse(CityNameMatcher::matches($one, $two));
    }

    public static function differentSettlementProvider(): array
    {
        return [
            'Борщагівки різні' => ['Sofiivska Borshchahivka', 'Petropavlivska Borshchahivka'],
            'схожі назви'      => ['Lviv', 'Lvivske'],
            'аліас не всеїдний' => ['Kiev', 'Kyivske'],
            // Уточнення знімається, але сама назва лишається значущою.
            'різні назви з уточненням' => ['Bakhmach', 'Baturyn (misto)'],
        ];
    }

    /**
     * Аліаси зводять до написання Нової Пошти, а не навпаки: інакше назва з
     * довідника почала б знаходити чужі міста.
     */
    public function testAliasesApplyToGeoIpSideOnly(): void
    {
        self::assertTrue(CityNameMatcher::matches('Kiev', 'Kyiv'));
        self::assertFalse(CityNameMatcher::matches('Kyiv', 'Kiev'));
    }

    public function testEmptyDirectoryValueNeverMatches(): void
    {
        // У довіднику city_translit — nullable, і порожній рядок не сміє
        // збігтися з порожнім результатом geo-IP.
        self::assertFalse(CityNameMatcher::matches('', ''));
        self::assertFalse(CityNameMatcher::matches('Kyiv', ''));
    }
}

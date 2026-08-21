<?php

namespace Modules\Sviat\NovaPoshtaPopularCities;

use Okay\Modules\Sviat\NovaPoshtaPopularCities\Controllers\GetCityByIpController;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;

/**
 * Місто відвідувача визначається за IP, і geo-IP сервіс віддає назву
 * транслітом. Далі вона зіставляється з довідником Нової Пошти — теж
 * транслітом, але записаним за іншою схемою.
 *
 * Точний збіг рядків тут не працює: `Sofiivska Borschahivka` з логу проду
 * і `Sofiivska Borshchahivka` з довідника — той самий населений пункт.
 * Розходяться саме на «щ» (shch/sch), пробілах, дефісах і апострофах.
 */
class TranslitMatchingTest extends TestCase
{
    /** @dataProvider sameSettlementProvider */
    #[DataProvider('sameSettlementProvider')]
    public function testSpellingsOfTheSameSettlementMatch(string $fromGeoIp, string $fromDirectory): void
    {
        $this->assertSame(
            $this->normalize($fromDirectory),
            $this->normalize($fromGeoIp),
            "«{$fromGeoIp}» і «{$fromDirectory}» — той самий населений пункт"
        );
    }

    public static function sameSettlementProvider(): array
    {
        return [
            'щ як sch і shch'   => ['Sofiivska Borschahivka', 'Sofiivska Borshchahivka'],
            'дефіс проти пробілу' => ['Bila-Tserkva', 'Bila Tserkva'],
            'апостроф'          => ["Kam'yanets-Podilskyi", 'Kamyanets Podilskyi'],
            'регістр'           => ['KYIV', 'Kyiv'],
            'зайві пробіли'     => ['  Chabany  ', 'Chabany'],
        ];
    }

    /** Різні міста не мають злипатись — інакше знайдемо не те. */
    /** @dataProvider differentSettlementProvider */
    #[DataProvider('differentSettlementProvider')]
    public function testDifferentSettlementsStayDifferent(string $one, string $two): void
    {
        $this->assertNotSame($this->normalize($one), $this->normalize($two));
    }

    public static function differentSettlementProvider(): array
    {
        return [
            'Борщагівки різні' => ['Sofiivska Borshchahivka', 'Petropavlivska Borshchahivka'],
            'схожі назви'      => ['Lviv', 'Lvivske'],
        ];
    }

    private function normalize(string $value): string
    {
        $controller = (new ReflectionClass(GetCityByIpController::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(GetCityByIpController::class, 'normalizeTranslit');
        // З 8.1 приватні методи доступні рефлексії без цього виклику, і він там
        // deprecated. Стенд стоку працює на 7.4, тож лишається під умовою.
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        return $method->invoke($controller, $value);
    }
}

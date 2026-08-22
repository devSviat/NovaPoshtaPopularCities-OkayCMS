<?php

namespace Modules\Sviat\NovaPoshtaPopularCities;

use Okay\Core\Settings;
use Okay\Modules\Sviat\NovaPoshtaPopularCities\Helpers\NPCitiesApiHelper;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Обхід сторінок довідника Нової Пошти.
 *
 * Тонкість, через яку довідник приїжджав неповним: getSettlements() віддає
 * сторінку населених пунктів, а модулю потрібні лише міста — і після відсіву
 * сіл на сторінці лишається жменя записів або жодного. Якщо рахувати сторінки
 * по відфільтрованому результату, обхід зупиняється на першій же сторінці без
 * міст, а весь хвіст довідника лишається незавантаженим.
 *
 * Транспорт підмінюється підкласом (request() зроблено protected), тож жодного
 * звернення до API не відбувається.
 */
class SettlementsPagingTest extends TestCase
{
    private const LIMIT = 4;

    public function testKeepsPagingThroughAPageThatHasNoCitiesAtAll(): void
    {
        $helper = $this->buildHelper([
            1 => [$this->city('Золочів'), $this->village(), $this->village(), $this->village()],
            2 => [$this->village(), $this->village(), $this->village(), $this->village()],
            3 => [$this->city('Калинівка')],
        ]);

        $names = array_column($helper->getAllCitySettlements(self::LIMIT), 'city_name');

        self::assertSame(['Золочів', 'Калинівка'], $names);
    }

    public function testStopsOnTheFirstIncompletePage(): void
    {
        $helper = $this->buildHelper([
            1 => [$this->city('Ніжин'), $this->city('Ніжин-2'), $this->village(), $this->village()],
            2 => [$this->city('Прилуки')],
            // Далі сторінок бути не може, але якщо обхід не спиниться —
            // сюди він дійде, і тест це побачить.
            3 => [$this->city('Зайве місто')],
        ]);

        $names = array_column($helper->getAllCitySettlements(self::LIMIT), 'city_name');

        self::assertSame(['Ніжин', 'Ніжин-2', 'Прилуки'], $names);
    }

    public function testEmptyFirstPageGivesEmptyResultRatherThanLooping(): void
    {
        $helper = $this->buildHelper([1 => []]);

        self::assertSame([], $helper->getAllCitySettlements(self::LIMIT));
    }

    public function testMaxPagesCapsTheWalk(): void
    {
        // Кожна сторінка повна, тобто сама по собі обхід не спинить.
        $pages = [];
        for ($page = 1; $page <= 10; $page++) {
            $pages[$page] = [$this->city('Місто ' . $page), $this->village(), $this->village(), $this->village()];
        }

        $names = array_column($this->buildHelper($pages)->getAllCitySettlements(self::LIMIT, 3), 'city_name');

        self::assertSame(['Місто 1', 'Місто 2', 'Місто 3'], $names);
    }

    /** @param array<int, array<int, array<string, string>>> $pages */
    private function buildHelper(array $pages): NPCitiesApiHelper
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
                $rows = $this->pages[$page] ?? [];

                return (object) [
                    'success' => true,
                    'data'    => array_map(static fn (array $row) => (object) $row, $rows),
                ];
            }
        };
    }

    /** @return array<string, string> */
    private function city(string $name): array
    {
        return [
            'SettlementTypeDescription' => 'місто',
            'Ref'                       => 'ref-' . $name,
            'Description'               => $name,
            'DescriptionTranslit'       => $name,
        ];
    }

    /** @return array<string, string> */
    private function village(): array
    {
        static $n = 0;
        $n++;

        return [
            'SettlementTypeDescription' => 'село',
            'Ref'                       => 'village-' . $n,
            'Description'               => 'Село ' . $n,
        ];
    }
}

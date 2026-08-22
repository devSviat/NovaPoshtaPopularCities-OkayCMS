<?php


namespace Okay\Modules\Sviat\NovaPoshtaPopularCities\Helpers;


/**
 * Зіставлення назви міста від geo-IP сервісу з довідником Нової Пошти.
 *
 * Обидві сторони пишуть латиницею, але за різними схемами. Нова Пошта тримає
 * українську офіційну транслітерацію (`Kyiv`, `Zaporizhia`), а geo-IP сервіси
 * повертають то її, то англійські усталені назви, то стару російську схему
 * (`Kiev`, `Zaporizhzhya`, `Kharkov`). Точний збіг рядків тут не працює.
 */
class CityNameMatcher
{
    /**
     * Усталені варіанти написання, які нормалізація звести не може: вони
     * розходяться не орфографією, а самою основою назви.
     *
     * Ключ і значення — уже нормалізовані форми. Поповнюється за логом:
     * рядок «City not found in database. Searched translit: X» називає саме
     * ту форму, яку віддав сервіс.
     */
    private static $aliases = [
        'kiev'           => 'kyiv',
        'kyyiv'          => 'kyiv',
        'kharkov'        => 'kharkiv',
        'odessa'         => 'odesa',
        'dnepr'          => 'dnipro',
        'dnepropetrovsk' => 'dnipro',
        'dnipropetrovsk' => 'dnipro',
        'zaporozhye'     => 'zaporizhia',
        'zaporizhzhya'   => 'zaporizhia',
        'zaporizhzhia'   => 'zaporizhia',
        'lvov'           => 'lviv',
        'nikolaev'       => 'mykolaiv',
        'mykolayiv'      => 'mykolaiv',
        'krivoyrog'      => 'kryvyirih',
        'kryvyyrih'      => 'kryvyirih',
        'vinnitsa'       => 'vinnytsia',
        'vinnytsya'      => 'vinnytsia',
        'chernigov'      => 'chernihiv',
        'chernovtsy'     => 'chernivtsi',
        'zhitomir'       => 'zhytomyr',
        'khmelnitskiy'   => 'khmelnytskyi',
        'khmelnytskyy'   => 'khmelnytskyi',
        'rovno'          => 'rivne',
        'kirovograd'     => 'kropyvnytskyi',
        'kropivnitskiy'  => 'kropyvnytskyi',
        'ivanofrankovsk' => 'ivanofrankivsk',
        'kremenchug'     => 'kremenchuk',
        'ternopol'       => 'ternopil',
        'uzhgorod'       => 'uzhhorod',
        'belayatserkov'  => 'bilatserkva',
        'kamenskoye'     => 'kamianske',
        'slavyansk'      => 'sloviansk',
        'pavlograd'      => 'pavlohrad',
        'berdyansk'      => 'berdiansk',
        'drogobych'      => 'drohobych',
        'irpen'          => 'irpin',
    ];

    /**
     * Ключ для порівняння. Одна й та сама функція застосовується до обох
     * сторін — інакше зіставляти було б нічого з чим.
     */
    public static function key(string $value): string
    {
        $value = mb_strtolower(trim($value));

        // Довідник Нової Пошти місцями містить не транслітерацію, а англійський
        // підпис: «Бершадь» лежить як «City of Bershad».
        if (strpos($value, 'city of ') === 0) {
            $value = substr($value, 8);
        }

        // Однойменні населені пункти Нова Пошта розрізняє уточненням у дужках,
        // і те уточнення потрапляє й у транслітерацію: `Bakhmach (misto)`,
        // `Kalynivka (Kalynivska terytorialna hromada)`. Для зіставлення воно
        // зайве — сервіс віддає саму назву. Знімаємо лише те, перед чим щось
        // лишається, щоб «(Misto)» без назви не перетворилось на порожній ключ.
        $withoutQualifier = preg_replace('/^(.+?)\s*\([^()]*\)\s*$/u', '$1', $value);
        if (is_string($withoutQualifier) && trim($withoutQualifier) !== '') {
            $value = $withoutQualifier;
        }

        // Різні geo-IP сервіси та Нова Пошта можуть по-різному передавати
        // українське "щ": shch / sch. Напр. Borshchahivka / Borschahivka.
        $value = str_replace('shch', 'sch', $value);

        // Пробіли, дефіси та апострофи не повинні створювати різні ключі.
        $key = preg_replace('/[^a-z0-9]+/u', '', $value);

        return $key === null ? $value : $key;
    }

    /**
     * Найм'якший ключ: зводить розбіжності самих схем транслітерації.
     *
     * Українську «г» одні пишуть `h`, інші `g`; «х» — то `kh`, то `h`; «и» та
     * «і» розходяться на `y`/`i`. Через це `Gorodishche` з довідника Нової
     * Пошти й `Horodyshche` від сервісу — той самий Городище.
     *
     * Перевірено на повному довіднику міст (405 записів): жодної колізії між
     * різними містами цей ключ не створює.
     */
    private static function foldedKey(string $key): string
    {
        $key = str_replace(['kh', 'g'], ['h', 'h'], $key);
        $key = str_replace('y', 'i', $key);

        // Подвоєння теж різняться схемами: Odessa / Odesa.
        $folded = preg_replace('/(.)\1+/', '$1', $key);

        return $folded === null ? $key : $folded;
    }

    /**
     * Ключ назви від geo-IP сервісу, зведений до написання Нової Пошти.
     */
    public static function directoryKey(string $geoIpName): string
    {
        $key = self::key($geoIpName);

        return isset(self::$aliases[$key]) ? self::$aliases[$key] : $key;
    }

    /**
     * Чи це той самий населений пункт. Аліаси застосовуються лише до назви
     * від сервісу: довідник Нової Пошти — сторона, до написання якої зводимо.
     */
    public static function matches(string $geoIpName, string $directoryName): bool
    {
        $directory = self::key($directoryName);
        if ($directory === '') {
            return false;
        }

        $geo = self::key($geoIpName);
        if ($geo === '') {
            return false;
        }

        // Від найточнішого до найм'якшого: точний збіг, потім усталений
        // відповідник, і аж тоді згортання схем транслітерації.
        return $directory === $geo
            || $directory === self::directoryKey($geoIpName)
            || self::foldedKey($directory) === self::foldedKey($geo);
    }
}

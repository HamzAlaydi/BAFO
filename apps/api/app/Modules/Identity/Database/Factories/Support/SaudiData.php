<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Factories\Support;

use Faker\Generator;

/**
 * Realistic Saudi test data for the module factories: Arabic company and person names, CR and
 * VAT numbers in the API.md §0.6 formats, `+9665` mobiles and national-address parts.
 * Factory-only (never used by application code).
 */
final class SaudiData
{
    /**
     * @var list<array{0: string, 1: string}>
     */
    private const array COMPANY_NAMES = [
        ['الأفق', 'Al Ofoq'],
        ['المصدر', 'Al Masdar'],
        ['الرواد', 'Al Rowad'],
        ['النخبة', 'Al Nokhba'],
        ['البيان', 'Al Bayan'],
        ['الريادة', 'Al Riyada'],
        ['الإتقان', 'Al Itqan'],
        ['المدار', 'Al Madar'],
        ['السنابل', 'Al Sanabel'],
        ['الواحة', 'Al Waha'],
        ['الصفوة', 'Al Safwa'],
        ['المستقبل', 'Al Mustaqbal'],
        ['الجزيرة', 'Al Jazeera'],
        ['الأصالة', 'Al Asala'],
        ['التميز', 'Al Tamayuz'],
        ['الوفاق', 'Al Wifaq'],
        ['الدرعية', 'Al Diriyah'],
        ['نجد', 'Najd'],
    ];

    /**
     * @var list<array{0: string, 1: string}>
     */
    private const array ACTIVITIES = [
        ['للتجارة', 'Trading'],
        ['للمقاولات', 'Contracting'],
        ['للتقنية', 'Technology'],
        ['للخدمات اللوجستية', 'Logistics'],
        ['للتوريدات', 'Supplies'],
        ['للصناعة', 'Industries'],
        ['للاستشارات', 'Consulting'],
        ['للخدمات الطبية', 'Medical Services'],
    ];

    /**
     * Arabic city, with the CR prefix of its commercial registry office.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const array CITIES = [
        ['الرياض', '1010'],
        ['جدة', '4030'],
        ['الدمام', '2050'],
        ['مكة المكرمة', '4031'],
        ['المدينة المنورة', '4650'],
        ['الخبر', '2051'],
        ['بريدة', '1131'],
        ['أبها', '5850'],
        ['تبوك', '3550'],
    ];

    /**
     * @var list<string>
     */
    private const array DISTRICTS = ['العليا', 'الملقا', 'النخيل', 'الروضة', 'الشاطئ', 'السليمانية', 'الياسمين', 'الفيصلية'];

    /**
     * @var list<string>
     */
    private const array STREETS = ['طريق الملك فهد', 'طريق الأمير سلطان', 'شارع التحلية', 'طريق الملك عبدالعزيز', 'شارع الأمير محمد بن عبدالعزيز', 'طريق العروبة'];

    /**
     * A company: display name (Arabic), legal names (Arabic and English) and an e-mail domain.
     *
     * @return array{name: string, legal_name_ar: string, legal_name_en: string, domain: string}
     */
    public static function company(Generator $faker): array
    {
        [$nameAr, $nameEn] = $faker->randomElement(self::COMPANY_NAMES);
        [$activityAr, $activityEn] = $faker->randomElement(self::ACTIVITIES);

        return [
            'name' => "شركة {$nameAr} {$activityAr}",
            'legal_name_ar' => "شركة {$nameAr} {$activityAr} المحدودة",
            'legal_name_en' => "{$nameEn} {$activityEn} Co. Ltd.",
            'domain' => strtolower(str_replace(' ', '', $nameEn.$activityEn)).'.sa',
        ];
    }

    /**
     * An Arabic person name (first and family name, no title).
     */
    public static function personName(): string
    {
        $arabic = fake('ar_SA');

        return $arabic->firstName().' '.$arabic->lastName();
    }

    /**
     * @return array{city: string, cr_prefix: string}
     */
    public static function city(Generator $faker): array
    {
        [$city, $prefix] = $faker->randomElement(self::CITIES);

        return ['city' => $city, 'cr_prefix' => $prefix];
    }

    /**
     * Commercial registration: exactly 10 digits (`^\d{10}$`), starting with the office prefix.
     */
    public static function crNumber(Generator $faker, string $prefix = '1010'): string
    {
        return $prefix.$faker->unique()->numerify('######');
    }

    /**
     * VAT number: 15 digits, first and last digit 3 (`^3\d{13}3$`).
     */
    public static function vatNumber(Generator $faker): string
    {
        return '3'.$faker->unique()->numerify('#############').'3';
    }

    /**
     * Saudi mobile in E.164 (`^\+9665\d{8}$`).
     */
    public static function mobile(Generator $faker): string
    {
        return '+9665'.$faker->numerify('########');
    }

    /**
     * National address parts (ARCHITECTURE §5.3).
     *
     * @return array{address_building_number: string, address_street: string, address_district: string, address_postal_code: string, address_additional_number: string, address_short: string}
     */
    public static function nationalAddress(Generator $faker): array
    {
        return [
            'address_building_number' => $faker->numerify('####'),
            'address_street' => $faker->randomElement(self::STREETS),
            'address_district' => 'حي '.$faker->randomElement(self::DISTRICTS),
            'address_postal_code' => $faker->numerify('1####'),
            'address_additional_number' => $faker->numerify('####'),
            'address_short' => strtoupper($faker->lexify('????')).$faker->numerify('####'),
        ];
    }

    /**
     * A SAR amount in halalas, on whole riyals (granularity 100), between the two riyal bounds.
     */
    public static function halalas(Generator $faker, int $minRiyals, int $maxRiyals): int
    {
        return $faker->numberBetween($minRiyals, $maxRiyals) * 100;
    }
}

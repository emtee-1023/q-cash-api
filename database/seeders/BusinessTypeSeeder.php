<?php

namespace Database\Seeders;

use App\Models\BusinessType;
use Illuminate\Database\Seeder;

class BusinessTypeSeeder extends Seeder
{
    /**
     * Ballpark gross monthly turnover for Kenyan micro and small businesses.
     *
     * Values are rounded and deliberately conservative. They are based on:
     * - CBK/KNBS/FSD Kenya MSE Tracker 2023: most MSEs turn over KES 20k-100k/month,
     *   and 87.4% have annual turnover below KES 1m.
     * - Laterite/TRANSFORM 2024: mama mboga mean gross monthly income KES 22,829
     *   (median 15,000); kibanda mean KES 24,803.
     * - Viffa Consult/Mogo 2025: boda boda riders average KES 1,100/day over six days
     *   (~KES 26,400/month, net of fuel).
     * - FSD Kenya grocery retail study 2021: duka ~KES 5,000/day and supa duka
     *   ~KES 22,000/day; high-end mini marts can be much higher.
     * - Trailblazers 2025: salon monthly revenue KES 30k-60k rural, KES 50k-100k
     *   peri-urban, and KES 100k-200k urban.
     *
     * These are turnover estimates, not profit, and vary widely by location, size,
     * stock range, and season.
     *
     * @var array<int, array<string, mixed>>
     */
    private const TYPES = [
        [
            'name' => 'Mama Mboga',
            'slug' => 'mama-mboga',
            'category' => 'food',
            'description' => 'Small-scale vegetable and fresh produce vendor.',
            'average_monthly_revenue_minor' => 3000000,
        ],
        [
            'name' => 'Boda Boda & Ride Hailing',
            'slug' => 'boda-boda-ride-hailing',
            'category' => 'transport',
            'description' => 'Motorcycle taxi and app-based ride hailing operator.',
            'average_monthly_revenue_minor' => 3500000,
        ],
        [
            'name' => 'Small Restaurant & Cafe',
            'slug' => 'small-restaurant-cafe',
            'category' => 'food',
            'description' => 'Neighbourhood eatery, cafe, or food kiosk.',
            'average_monthly_revenue_minor' => 10000000,
        ],
        [
            'name' => 'General Shop (Duka)',
            'slug' => 'general-shop',
            'category' => 'retail',
            'description' => 'Small neighbourhood shop selling everyday household goods.',
            'average_monthly_revenue_minor' => 10000000,
        ],
        [
            'name' => 'E-commerce Store',
            'slug' => 'ecommerce-store',
            'category' => 'ecommerce',
            'description' => 'Online store selling through social media, marketplaces, or its own website.',
            'average_monthly_revenue_minor' => 6000000,
        ],
        [
            'name' => 'Florist',
            'slug' => 'florist',
            'category' => 'retail',
            'description' => 'Flower and gift shop serving walk-in and event customers.',
            'average_monthly_revenue_minor' => 5000000,
        ],
        [
            'name' => 'Salon & Barbershop',
            'slug' => 'salon-barbershop',
            'category' => 'services',
            'description' => 'Hair salon, barbershop, or beauty services business.',
            'average_monthly_revenue_minor' => 8000000,
        ],
        [
            'name' => 'Boutique & Fashion',
            'slug' => 'boutique-fashion',
            'category' => 'retail',
            'description' => 'Clothing, footwear, and fashion accessories retailer.',
            'average_monthly_revenue_minor' => 8000000,
        ],
        [
            'name' => 'Hardware Store',
            'slug' => 'hardware-store',
            'category' => 'retail',
            'description' => 'Building materials, tools, and hardware supplies shop.',
            'average_monthly_revenue_minor' => 15000000,
        ],
        [
            'name' => 'Electronics Shop',
            'slug' => 'electronics-shop',
            'category' => 'retail',
            'description' => 'Phones, accessories, and consumer electronics retailer.',
            'average_monthly_revenue_minor' => 15000000,
        ],
        [
            'name' => 'Agrovet & Farm Supplies',
            'slug' => 'agrovet-farm-supplies',
            'category' => 'agriculture',
            'description' => 'Agrovet shop selling seeds, feed, and farm inputs.',
            'average_monthly_revenue_minor' => 12000000,
        ],
        [
            'name' => 'Kiosk',
            'slug' => 'kiosk',
            'category' => 'retail',
            'description' => 'Small roadside kiosk selling snacks, airtime, and essentials.',
            'average_monthly_revenue_minor' => 4000000,
        ],
        [
            'name' => 'Mini Mart & Supermarket',
            'slug' => 'mini-mart-supermarket',
            'category' => 'retail',
            'description' => 'Mini mart or small supermarket serving a local catchment.',
            'average_monthly_revenue_minor' => 30000000,
        ],
        [
            'name' => 'Wholesale & Distribution',
            'slug' => 'wholesale-distributor',
            'category' => 'wholesale',
            'description' => 'Small wholesaler or distributor supplying other businesses.',
            'average_monthly_revenue_minor' => 50000000,
        ],
        [
            'name' => 'General Services',
            'slug' => 'general-services',
            'category' => 'services',
            'description' => 'Repairs, cleaning, tailoring, and other local services.',
            'average_monthly_revenue_minor' => 6000000,
        ],
        [
            'name' => 'Other Small Business',
            'slug' => 'other-small-business',
            'category' => 'other',
            'description' => 'A small business that does not fit the other categories.',
            'average_monthly_revenue_minor' => 5000000,
        ],
    ];

    public function run(): void
    {
        foreach (self::TYPES as $type) {
            BusinessType::query()->updateOrCreate(
                ['slug' => $type['slug']],
                $type + ['is_active' => true],
            );
        }

    }

    public static function slugForLegacy(?string $category, ?string $type = null): string
    {
        return match ($category) {
            'barbershop' => 'salon-barbershop',
            'general_store', 'misc' => 'general-shop',
            'groceries', 'greengrocer' => 'mama-mboga',
            'hardware' => 'hardware-store',
            'fashion' => 'boutique-fashion',
            'agrovet' => 'agrovet-farm-supplies',
            'kiosk' => 'kiosk',
            'electronics' => 'electronics-shop',
            'cafe' => 'small-restaurant-cafe',
            'supermarket' => 'mini-mart-supermarket',
            'transport' => 'boda-boda-ride-hailing',
            default => match ($type) {
                'wholesale' => 'wholesale-distributor',
                'service' => 'general-services',
                'retail' => 'general-shop',
                default => 'other-small-business',
            },
        };
    }
}

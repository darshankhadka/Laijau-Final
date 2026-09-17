<?php

namespace App\Services;

class NepalLocationService
{
    /**
     * Districts comprising the Kathmandu Valley delivery zone.
     */
    public const VALLEY_DISTRICTS = [
        'Kathmandu',
        'Lalitpur',
        'Bhaktapur',
    ];

    /**
     * All 7 Provinces and their 77 Districts.
     */
    public const PROVINCES_WITH_DISTRICTS = [
        'Bagmati Province' => [
            'Kathmandu', 'Lalitpur', 'Bhaktapur', 'Chitwan', 'Dhading',
            'Dolakha', 'Kavrepalanchok', 'Makwanpur', 'Nuwakot', 'Ramechhap',
            'Rasuwa', 'Sindhuli', 'Sindhupalchok'
        ],
        'Koshi Province' => [
            'Bhojpur', 'Dhankuta', 'Ilam', 'Jhapa', 'Khotang', 'Morang',
            'Okhaldhunga', 'Panchthar', 'Sankhuwasabha', 'Solukhumbu',
            'Sunsari', 'Taplejung', 'Terhathum', 'Udayapur'
        ],
        'Madhesh Province' => [
            'Bara', 'Dhanusha', 'Mahottari', 'Parsa', 'Rautahat',
            'Saptari', 'Sarlahi', 'Siraha'
        ],
        'Gandaki Province' => [
            'Baglung', 'Gorkha', 'Kaski', 'Lamjung', 'Manang',
            'Mustang', 'Myagdi', 'Nawalpur', 'Parbat', 'Syangja', 'Tanahun'
        ],
        'Lumbini Province' => [
            'Arghakhanchi', 'Banke', 'Bardiya', 'Dang', 'Gulmi',
            'Kapilvastu', 'Parasi', 'Palpa', 'Pyuthan', 'Rolpa',
            'Rukum East', 'Rupandehi'
        ],
        'Karnali Province' => [
            'Dailekh', 'Dolpa', 'Humla', 'Jajarkot', 'Jumla',
            'Kalikot', 'Mugu', 'Rukum West', 'Salyan', 'Surkhet'
        ],
        'Sudurpashchim Province' => [
            'Achham', 'Baitadi', 'Bajhang', 'Bajura', 'Dadeldhura',
            'Darchula', 'Doti', 'Kailali', 'Kanchanpur'
        ],
    ];

    /**
     * Determine if a district is inside the Kathmandu Valley delivery zone.
     */
    public static function isKathmanduValley(?string $district): bool
    {
        if (empty($district)) {
            return false;
        }

        $clean = strtolower(trim($district));
        foreach (self::VALLEY_DISTRICTS as $vd) {
            if (strtolower($vd) === $clean) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if Cash on Delivery is allowed for a given district.
     * RULE: COD is available ONLY inside Kathmandu Valley.
     */
    public static function isCodAvailable(?string $district): bool
    {
        return self::isKathmanduValley($district);
    }

    /**
     * Calculate delivery fee based on district and cart subtotal.
     */
    public static function calculateShippingFee(?string $district, float $subtotal = 0.0): float
    {
        $insideValley = self::isKathmanduValley($district);

        if ($insideValley) {
            // Free delivery inside valley for orders Rs. 2,000 and above
            return $subtotal >= 2000 ? 0.00 : 100.00;
        }

        // Outside valley courier shipping: free for Rs. 3,500 and above, otherwise Rs. 150
        return $subtotal >= 3500 ? 0.00 : 150.00;
    }

    /**
     * Return list of eligible payment methods for a given district.
     */
    public static function getEligiblePaymentMethods(?string $district): array
    {
        $methods = [
            [
                'id' => 'connectips',
                'name' => 'connectIPS / NCHL',
                'type' => 'online_interbank',
                'description' => 'Direct real-time interbank online payment via NCHL connectIPS',
                'badge' => 'Real-Time Interbank',
            ],
            [
                'id' => 'esewa',
                'name' => 'eSewa',
                'type' => 'manual_prepaid',
                'description' => 'Pay via eSewa digital wallet to 9843512095 (Delta Nine business group)',
                'badge' => 'Instant Verification',
            ],
            [
                'id' => 'khalti',
                'name' => 'Khalti',
                'type' => 'manual_prepaid',
                'description' => 'Pay via Khalti digital wallet to 9843512095',
                'badge' => 'Digital Wallet',
            ],
            [
                'id' => 'bank_transfer',
                'name' => 'Bank Transfer',
                'type' => 'manual_prepaid',
                'description' => 'Direct deposit or mobile banking transfer',
                'badge' => 'Direct Bank',
            ],
        ];

        if (self::isCodAvailable($district)) {
            array_unshift($methods, [
                'id' => 'cod',
                'name' => 'Cash on Delivery (COD)',
                'type' => 'cod',
                'description' => 'Pay cash upon package arrival at your doorstep',
                'badge' => 'Kathmandu Valley Exclusive',
            ]);
        }

        return $methods;
    }

    /**
     * Get list of all provinces.
     */
    public static function getProvinces(): array
    {
        return array_keys(self::PROVINCES_WITH_DISTRICTS);
    }

    /**
     * Get districts grouped by province or flat list.
     */
    public static function getAllDistricts(): array
    {
        $districts = [];
        foreach (self::PROVINCES_WITH_DISTRICTS as $list) {
            foreach ($list as $d) {
                $districts[] = $d;
            }
        }
        sort($districts);
        return $districts;
    }
}

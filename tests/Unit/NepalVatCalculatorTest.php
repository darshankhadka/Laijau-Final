<?php

namespace Tests\Unit;

use App\Services\TaxCalculatorService;
use Tests\TestCase;

class NepalVatCalculatorTest extends TestCase
{
    protected TaxCalculatorService $taxCalculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->taxCalculator = new TaxCalculatorService();
    }

    /**
     * Requirement: If taxable amount is NPR 800 (VAT-exclusive):
     * VAT = 800 * 13% = 104
     * Gross = 800 + 104 = 904
     */
    public function test_vat_exclusive_calculation_on_800(): void
    {
        $result = $this->taxCalculator->calculateExclusive(800.00);

        $this->assertSame(800.00, $result['taxable_amount']);
        $this->assertSame(13.0, $result['vat_rate']);
        $this->assertSame(104.00, $result['vat_amount']);
        $this->assertSame(904.00, $result['gross_amount']);

        // Test static helper
        $staticResult = TaxCalculatorService::calcExclusive(800.00);
        $this->assertSame(104.00, $staticResult['vat_amount']);
        $this->assertSame(904.00, $staticResult['gross_amount']);
    }

    /**
     * Requirement: If customer pays NPR 800 including VAT (VAT-inclusive):
     * Net = 800 / 1.13 = 707.96
     * VAT = 800 - 707.96 = 92.04
     * Gross = 800
     */
    public function test_vat_inclusive_calculation_on_800(): void
    {
        $result = $this->taxCalculator->calculateInclusive(800.00);

        $this->assertSame(707.96, $result['net_amount']);
        $this->assertSame(707.96, $result['taxable_amount']);
        $this->assertSame(13.0, $result['vat_rate']);
        $this->assertSame(92.04, $result['vat_amount']);
        $this->assertSame(800.00, $result['gross_amount']);

        // Verify that Net + VAT strictly equals Gross
        $this->assertSame(800.00, round($result['net_amount'] + $result['vat_amount'], 2));

        // Test static helpers
        $this->assertSame(707.96, TaxCalculatorService::extractNetFromInclusive(800.00));
        $this->assertSame(92.04, TaxCalculatorService::extractVatFromInclusive(800.00));
    }

    /**
     * Requirement: Never produce 800 -> 200 VAT because that represents 25% nepalese VAT.
     */
    public function test_never_produces_200_vat_on_800(): void
    {
        $exclusive = $this->taxCalculator->calculateExclusive(800.00);
        $inclusive = $this->taxCalculator->calculateInclusive(800.00);

        $this->assertNotEquals(200.00, $exclusive['vat_amount'], 'VAT exclusive of 800 must never be 200');
        $this->assertNotEquals(200.00, $inclusive['vat_amount'], 'VAT inclusive of 800 must never be 200');
    }

    /**
     * Test various price points under statutory Nepal 13% VAT.
     */
    public function test_various_price_points(): void
    {
        // NPR 1000 inclusive: Net 884.96, VAT 115.04
        $res1000 = TaxCalculatorService::calcInclusive(1000.00);
        $this->assertSame(884.96, $res1000['net_amount']);
        $this->assertSame(115.04, $res1000['vat_amount']);

        // NPR 1000 exclusive: VAT 130.00, Gross 1130.00
        $res1000Ex = TaxCalculatorService::calcExclusive(1000.00);
        $this->assertSame(130.00, $res1000Ex['vat_amount']);
        $this->assertSame(1130.00, $res1000Ex['gross_amount']);

        // NPR 2500 inclusive: Net 2212.39, VAT 287.61
        $res2500 = TaxCalculatorService::calcInclusive(2500.00);
        $this->assertSame(2212.39, $res2500['net_amount']);
        $this->assertSame(287.61, $res2500['vat_amount']);
        $this->assertSame(2500.00, round($res2500['net_amount'] + $res2500['vat_amount'], 2));
    }

    /**
     * Test zero and boundary conditions.
     */
    public function test_zero_and_boundary_conditions(): void
    {
        $resZeroEx = TaxCalculatorService::calcExclusive(0.00);
        $this->assertSame(0.00, $resZeroEx['vat_amount']);
        $this->assertSame(0.00, $resZeroEx['gross_amount']);

        $resZeroIn = TaxCalculatorService::calcInclusive(0.00);
        $this->assertSame(0.00, $resZeroIn['vat_amount']);
        $this->assertSame(0.00, $resZeroIn['net_amount']);
    }
}

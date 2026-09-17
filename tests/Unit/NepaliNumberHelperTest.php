<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\NepaliNumberHelper;
use PHPUnit\Framework\TestCase;

class NepaliNumberHelperTest extends TestCase
{
    /**
     * Test exact South Asian number grouping per Nepal financial standard.
     */
    public function test_south_asian_number_grouping(): void
    {
        // User required examples
        $this->assertSame('3,64,72,322.78', NepaliNumberHelper::format(36472322.78, 2));
        $this->assertSame('3,12,26,517.02', NepaliNumberHelper::format(31226517.02, 2));
        $this->assertSame('1,00,000', NepaliNumberHelper::format(100000, 0));
        $this->assertSame('5,00,000', NepaliNumberHelper::format(500000, 0));
        $this->assertSame('10,00,000', NepaliNumberHelper::format(1000000, 0));
        $this->assertSame('1,00,00,000', NepaliNumberHelper::format(10000000, 0));

        // Currency formatting
        $this->assertSame('Rs. 3,64,72,322.78', NepaliNumberHelper::formatCurrency(36472322.78, 'Rs. ', 2));
        $this->assertSame('Rs. 3,12,26,517.02', NepaliNumberHelper::formatCurrency(31226517.02, 'Rs. ', 2));
        $this->assertSame('Rs. 1,00,000', NepaliNumberHelper::formatCurrency(100000, 'Rs. ', 0));
        $this->assertSame('Rs. 5,00,000', NepaliNumberHelper::formatCurrency(500000, 'Rs. ', 0));
    }

    /**
     * Test Lakh and Crore human-facing representation.
     */
    public function test_lakh_and_crore_representation(): void
    {
        // Crores
        $this->assertSame('Rs. 3.65 crore', NepaliNumberHelper::formatLakhCrore(36472322.78));
        $this->assertSame('Rs. 3.12 crore', NepaliNumberHelper::formatLakhCrore(31226517.02));
        $this->assertSame('Rs. 3.12 crore', NepaliNumberHelper::formatLakhCrore(31227267));
        $this->assertSame('Rs. 1 crore', NepaliNumberHelper::formatLakhCrore(10000000));

        // Lakhs
        $this->assertSame('Rs. 1 lakh', NepaliNumberHelper::formatLakhCrore(100000));
        $this->assertSame('Rs. 5 lakh', NepaliNumberHelper::formatLakhCrore(500000));
        $this->assertSame('Rs. 10 lakh', NepaliNumberHelper::formatLakhCrore(1000000));

        // Small amounts
        $this->assertSame('Rs. 800', NepaliNumberHelper::formatLakhCrore(800));
        $this->assertSame('Rs. 104', NepaliNumberHelper::formatLakhCrore(104));
        $this->assertSame('Rs. 904', NepaliNumberHelper::formatLakhCrore(904));
    }

    /**
     * Test global helper functions nepali_number, nepali_currency, nepali_lakh_crore.
     */
    public function test_global_helper_functions(): void
    {
        $this->assertSame('3,64,72,322.78', nepali_number(36472322.78, 2));
        $this->assertSame('Rs. 3,64,72,322.78', nepali_currency(36472322.78, 'Rs. ', 2));
        $this->assertSame('Rs. 3.65 crore', nepali_lakh_crore(36472322.78));
        $this->assertSame('Rs. 5 lakh', nepali_lakh_crore(500000));
    }
}

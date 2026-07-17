<?php

namespace Tests\Unit;

use App\Http\Controllers\ReportController;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    /** @test */
    public function report_types_lists_every_routed_report_method(): void
    {
        $response = app(ReportController::class)->reportTypes()->getData(true);

        $expectedMethods = [
            'stockValuation', 'inventoryMovement', 'stockAging', 'wasteReport',
            'costAnalysis', 'profitability', 'dashboard', 'salesReport',
            'inventoryReport', 'userActivityReport', 'monthlyReport', 'sellingProducts',
            'customerPurchaseHistory', 'salesByCategory', 'refundsAndReturns',
            'monthlySalesGrowth', 'userEngagement', 'inventoryTurnover',
            'paymentMethodPerformance', 'getShifts', 'monthlyCost',
            'productCostComparison',
        ];

        $this->assertTrue($response['success']);
        $this->assertEqualsCanonicalizing($expectedMethods, array_column($response['data'], 'method'));
    }
}

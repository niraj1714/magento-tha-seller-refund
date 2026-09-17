<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Test\Unit\Model;

use Acme\SellerRefund\Model\Config;
use Acme\SellerRefund\Model\RefundEligibility;
use Acme\SellerRefund\Model\SellerLineResolver;
use Acme\SellerRefund\Model\ResourceModel\PriorRefundQuantity;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RefundEligibilityTest extends TestCase
{
    private Config&MockObject $config;

    private SellerLineResolver&MockObject $sellerLineResolver;

    private PriorRefundQuantity&MockObject $priorRefundQuantity;

    private RefundEligibility $eligibility;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('windowDays')->willReturn(7);
        $this->sellerLineResolver = $this->createMock(SellerLineResolver::class);
        $this->priorRefundQuantity = $this->createMock(PriorRefundQuantity::class);
        $this->priorRefundQuantity->method('sumRefundedByOrderItem')->willReturn([]);
        $this->eligibility = new RefundEligibility(
            $this->config,
            $this->sellerLineResolver,
            $this->priorRefundQuantity
        );
    }

    public function testRefundOnEighthDayIsOutsideContractualWindow(): void
    {
        $order = $this->order('2026-09-01 12:00:00');
        $this->sellerLineResolver->method('sellerLines')->willReturn([5 => $this->item()]);

        $result = $this->eligibility->check($order, new \DateTimeImmutable('2026-09-09 12:00:00'));

        self::assertFalse($result->isEligible());
        self::assertContains(RefundEligibility::REASON_WINDOW_EXPIRED, $result->getReasons());
    }

    public function testRefundOnSeventhDayIsWithinContractualWindow(): void
    {
        $order = $this->order('2026-09-01 12:00:00');
        $this->sellerLineResolver->method('sellerLines')->willReturn([5 => $this->item()]);

        $result = $this->eligibility->check($order, new \DateTimeImmutable('2026-09-08 12:00:00'));

        self::assertTrue($result->isEligible());
        self::assertSame([], $result->getReasons());
    }

    private function order(string $deliveredAt): OrderInterface&MockObject
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getData')->with('mp_delivered_at')->willReturn($deliveredAt);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getEntityId')->willReturn(100);

        return $order;
    }

    private function item(): OrderItem&MockObject
    {
        $item = $this->createMock(OrderItem::class);
        $item->method('getQtyOrdered')->willReturn(1.0);

        return $item;
    }
}
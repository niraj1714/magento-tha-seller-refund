<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Test\Unit\Model;

use Acme\SellerRefund\Exception\ValidationException;
use Acme\SellerRefund\Model\EligibilityResult;
use Acme\SellerRefund\Model\RefundEligibility;
use Acme\SellerRefund\Model\RefundValidator;
use Acme\SellerRefund\Model\SellerLineResolver;
use Acme\SellerRefund\Model\Submission\RefundSubmission;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit coverage of the server-side submit re-check (BR-03). The seller-line resolver is
 * mocked; no database or application bootstrap is touched.
 */
class RefundValidatorTest extends TestCase
{
    private SellerLineResolver&MockObject $sellerLineResolver;

    private RefundEligibility&MockObject $eligibility;

    private TimezoneInterface&MockObject $timezone;

    private RefundValidator $validator;

    /** @var OrderInterface&MockObject */
    private OrderInterface $order;

    protected function setUp(): void
    {
        $this->sellerLineResolver = $this->createMock(SellerLineResolver::class);
        $this->eligibility = $this->createMock(RefundEligibility::class);
        $this->eligibility->method('check')->willReturn(new EligibilityResult(true));
        $this->timezone = $this->createMock(TimezoneInterface::class);
        $this->timezone->method('date')->willReturn(new \DateTimeImmutable('2026-09-08 12:00:00'));
        $this->order = $this->createMock(OrderInterface::class);
        $this->validator = new RefundValidator($this->sellerLineResolver, $this->eligibility, $this->timezone);
    }

    /**
     * A selection at exactly the remaining refundable quantity is accepted: 3 ordered, 2
     * already refunded, 1 requested.
     */
    public function testSelectionAtRemainingQuantityIsAccepted(): void
    {
        $this->sellerLineResolver->method('sellerLines')->willReturn([
            5 => $this->orderItem('3'),
        ]);

        $submission = new RefundSubmission('defect', [5 => '1']);

        $this->validator->validate($this->order, $submission, [5 => '2']);

        // No exception means the selection is valid.
        $this->addToAssertionCount(1);
    }

    public function testQuantityExceedingOrderedIsRejected(): void
    {
        $this->sellerLineResolver->method('sellerLines')->willReturn([
            5 => $this->orderItem('2'),
        ]);

        $submission = new RefundSubmission('defect', [5 => '3']);

        $this->expectException(ValidationException::class);
        $this->validator->validate($this->order, $submission, [5 => '0']);
    }

    public function testZeroQuantityIsRejected(): void
    {
        $this->sellerLineResolver->method('sellerLines')->willReturn([
            5 => $this->orderItem('2'),
        ]);

        $submission = new RefundSubmission('defect', [5 => '0']);

        $this->expectException(ValidationException::class);
        $this->validator->validate($this->order, $submission, []);
    }

    public function testNegativeQuantityIsRejected(): void
    {
        $this->sellerLineResolver->method('sellerLines')->willReturn([
            5 => $this->orderItem('2'),
        ]);

        $submission = new RefundSubmission('defect', [5 => '-1']);

        $this->expectException(ValidationException::class);
        $this->validator->validate($this->order, $submission, []);
    }

    public function testNonSellerLineIsRejected(): void
    {
        // The requested item id is not among the seller lines.
        $this->sellerLineResolver->method('sellerLines')->willReturn([
            5 => $this->orderItem('2'),
        ]);

        $submission = new RefundSubmission('defect', [9 => '1']);

        $this->expectException(ValidationException::class);
        $this->validator->validate($this->order, $submission, []);
    }

    public function testUnknownReasonIsRejected(): void
    {
        $this->sellerLineResolver->method('sellerLines')->willReturn([
            5 => $this->orderItem('2'),
        ]);

        $submission = new RefundSubmission('not_a_reason', [5 => '1']);

        $this->expectException(ValidationException::class);
        $this->validator->validate($this->order, $submission, []);
    }

    public function testIneligibleOrderIsRejectedBeforeLineValidation(): void
    {
        $this->eligibility->method('check')->willReturn(new EligibilityResult(false, ['window_expired']));

        $submission = new RefundSubmission('defect', [5 => '1']);

        $this->expectException(ValidationException::class);
        $this->validator->validate($this->order, $submission, []);
    }

    private function orderItem(string $qtyOrdered): OrderItem&MockObject
    {
        $item = $this->createMock(OrderItem::class);
        $item->method('getQtyOrdered')->willReturn((float) $qtyOrdered);

        return $item;
    }
}

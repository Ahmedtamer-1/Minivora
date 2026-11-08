<?php

namespace App\Modules\Orders\Enums;

/**
 * Order statuses and the moves allowed between them. FR-ORD-01
 * Cash on delivery skips "paid": it goes pending → processing → shipped → delivered and counts as
 * paid once delivered.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Returned = 'returned';

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::Pending => [self::Paid, self::Processing, self::Cancelled],
            self::Paid => [self::Processing, self::Cancelled, self::Refunded],
            self::Processing => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered, self::Returned],
            self::Delivered => [self::Returned, self::Refunded],
            self::Cancelled, self::Refunded, self::Returned => [],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }

    /** Closed orders: nothing more to do. */
    public function isClosed(): bool
    {
        return $this->next() === [];
    }

    /** Statuses whose money still counts as a sale. */
    public function countsAsSale(): bool
    {
        return ! in_array($this, [self::Cancelled, self::Refunded, self::Returned], true);
    }

    /** Moving here puts the items back on the shelf. FR-ORD-03 */
    public function restocks(): bool
    {
        return in_array($this, [self::Cancelled, self::Returned], true);
    }

    /** The shopper gets an email when an order reaches these. FR-ORD-02 */
    public function emailsShopper(): bool
    {
        return in_array($this, [self::Paid, self::Shipped, self::Cancelled], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}

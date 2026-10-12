<?php

declare(strict_types=1);

namespace App\Domain\Menu\Actions;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Branches\Models\Branch;
use App\Domain\Menu\BranchMenu;
use App\Domain\Menu\Models\BranchProduct;
use App\Domain\Menu\Models\Product;
use App\Domain\Menu\Notifications\LowStock;
use App\Domain\Staff\Enums\EmployeeStatus;
use App\Domain\Staff\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Everything a branch changes about a product during the day: serve it or
 * not, its branch price, "out of stock today", the daily stock count, and
 * (when orders are placed) using that stock up with a low-stock alert.
 */
final class BranchStock
{
    public function __construct(private readonly BranchMenu $menu) {}

    /** Off the menu until the branch's next local midnight, then back by itself. */
    public function markOutOfStockToday(Product $product, Branch $branch, ?User $actor = null): BranchProduct
    {
        return $this->update($product, $branch, ['out_of_stock_until' => $this->menu->nextMidnight($branch)], $actor, 'menu.out_of_stock');
    }

    public function backInStock(Product $product, Branch $branch, ?User $actor = null): BranchProduct
    {
        return $this->update($product, $branch, ['out_of_stock_until' => null], $actor, 'menu.back_in_stock');
    }

    public function setServed(Product $product, Branch $branch, bool $served, ?User $actor = null): BranchProduct
    {
        return $this->update($product, $branch, ['is_available' => $served], $actor, $served ? 'menu.served' : 'menu.not_served');
    }

    /** A branch-only price, or null to use the menu price. */
    public function setPrice(Product $product, Branch $branch, ?int $priceMinor, ?User $actor = null): BranchProduct
    {
        if ($priceMinor !== null && $priceMinor < 0) {
            throw new InvalidArgumentException('Prices cannot be negative.');
        }

        return $this->update($product, $branch, ['price_override_minor' => $priceMinor], $actor, 'menu.branch_price');
    }

    /**
     * Count stock per day (null stops counting). Setting it refills today's
     * remaining stock to the new amount.
     */
    public function setDailyStock(Product $product, Branch $branch, ?int $daily, ?int $lowThreshold = null, ?User $actor = null): BranchProduct
    {
        return $this->update($product, $branch, [
            'daily_stock' => $daily,
            'stock_remaining' => $daily,
            'stock_date' => $daily === null ? null : $this->menu->localDate($branch),
            'low_stock_threshold' => $daily === null ? null : $lowThreshold,
            'low_stock_alerted_at' => null,
        ], $actor, 'menu.daily_stock');
    }

    /**
     * Uses up stock for an order. Returns false (and changes nothing) when
     * there isn't enough left today. Alerts the branch once per day when the
     * count drops to the low-stock threshold.
     */
    public function consume(Product $product, Branch $branch, int $quantity = 1): bool
    {
        return DB::transaction(function () use ($product, $branch, $quantity): bool {
            $setting = BranchProduct::withoutTenancy()
                ->where('branch_id', $branch->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if ($setting === null || $setting->daily_stock === null) {
                return true;
            }

            $today = $this->menu->localDate($branch);
            $left = (int) $this->menu->stockLeft($setting, $today);

            if ($left < $quantity) {
                return false;
            }

            $isNewDay = $setting->stock_date?->toDateString() !== $today;

            $setting->forceFill([
                'stock_remaining' => $left - $quantity,
                'stock_date' => $today,
                'low_stock_alerted_at' => $isNewDay ? null : $setting->low_stock_alerted_at,
            ])->save();

            $threshold = $setting->low_stock_threshold;

            if ($threshold !== null && $left - $quantity <= $threshold && $setting->low_stock_alerted_at === null) {
                $setting->forceFill(['low_stock_alerted_at' => now()])->save();
                DB::afterCommit(fn () => $this->alert($product, $branch, $left - $quantity));
            }

            return true;
        });
    }

    /**
     * Puts stock back when an order is cancelled the same local day. (A
     * cancellation after midnight doesn't top up the new day's count.)
     */
    public function release(Product $product, Branch $branch, int $quantity, string $orderLocalDate): void
    {
        DB::transaction(function () use ($product, $branch, $quantity, $orderLocalDate): void {
            $setting = BranchProduct::withoutTenancy()
                ->where('branch_id', $branch->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if ($setting === null || $setting->daily_stock === null || $setting->stock_date?->toDateString() !== $orderLocalDate) {
                return;
            }

            $setting->forceFill(['stock_remaining' => min($setting->daily_stock, (int) $setting->stock_remaining + $quantity)])->save();
        });
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function update(Product $product, Branch $branch, array $changes, ?User $actor, string $event): BranchProduct
    {
        if ($product->restaurant_id !== $branch->restaurant_id) {
            throw new InvalidArgumentException('Product and branch belong to different restaurants.');
        }

        $setting = BranchProduct::withoutTenancy()->firstOrNew(
            ['branch_id' => $branch->id, 'product_id' => $product->id],
            ['restaurant_id' => $branch->restaurant_id],
        );

        $setting->forceFill($changes)->save();

        activity()->performedOn($product)->causedBy($actor)->event($event)
            ->withProperties(array_merge(['branch' => $branch->code], array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format(DATE_ATOM) : $v, $changes)))
            ->log($event);

        return $setting;
    }

    private function alert(Product $product, Branch $branch, int $left): void
    {
        $recipients = Employee::withoutTenancy()
            ->with(['user', 'position', 'branches'])
            ->where('restaurant_id', $branch->restaurant_id)
            ->where('status', EmployeeStatus::Active)
            ->get()
            ->filter(fn (Employee $employee): bool => $employee->allows(RestaurantPermission::ManageAvailability) && $employee->coversBranch($branch))
            ->map(fn (Employee $employee): User => $employee->user);

        Notification::send($recipients, new LowStock($product, $branch, $left));
    }
}

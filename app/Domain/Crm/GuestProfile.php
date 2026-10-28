<?php

declare(strict_types=1);

namespace App\Domain\Crm;

use App\Domain\Campaigns\Models\CampaignMessage;
use App\Domain\Crm\Models\CustomerSegment;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Models\StampCard;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;

/**
 * Everything the guest profile shows, read in a fixed number of queries
 * however long the guest's history is.
 */
final class GuestProfile
{
    public const RECENT_ORDERS = 6;

    public const FAVOURITES = 5;

    /**
     * @return array{
     *     lifetime_minor: int,
     *     recent: Collection<int, Order>,
     *     favourites: list<array{product: Product, quantity: int}>,
     *     segments: Collection<int, CustomerSegment>,
     *     wallet: Collection<int, WalletReward>,
     *     stamps: Collection<int, StampCard>,
     *     campaigns: array{received: int, orders: int, revenue_minor: int},
     * }
     */
    public function for(Customer $customer): array
    {
        $customer->loadMissing(['tier', 'segments.product']);

        $lifetime = (int) Order::query()
            ->where('customer_id', $customer->id)
            ->where('status', OrderStatus::PickedUp->value)
            ->sum('total_minor');

        $recent = Order::query()
            ->where('customer_id', $customer->id)
            ->with('branch')
            ->withSum('items as quantity', 'quantity')
            ->latest('id')
            ->limit(self::RECENT_ORDERS)
            ->get();

        $top = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.customer_id', $customer->id)
            ->where('orders.status', OrderStatus::PickedUp->value)
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id as product_id, sum(order_items.quantity) as quantity')
            ->orderByDesc('quantity')
            ->orderBy('order_items.product_id')
            ->limit(self::FAVOURITES)
            ->toBase()
            ->get();

        $products = Product::query()->whereKey($top->pluck('product_id')->all())->get()->keyBy('id');

        $favourites = [];

        foreach ($top as $row) {
            $product = $products->get($row->product_id);

            if ($product instanceof Product) {
                $favourites[] = ['product' => $product, 'quantity' => (int) $row->quantity];
            }
        }

        $wallet = WalletReward::query()
            ->where('customer_id', $customer->id)
            ->usable()
            ->with('reward')
            ->orderBy('expires_at')
            ->get();

        $stamps = StampCard::query()
            ->where('customer_id', $customer->id)
            ->whereHas('program', fn ($query) => $query->whereNull('archived_at'))
            ->with('program')
            ->get();

        $campaigns = CampaignMessage::query()
            ->where('customer_id', $customer->id)
            ->where('status', CampaignMessage::SENT)
            ->selectRaw('count(*) as received, count(order_id) as orders, coalesce(sum(revenue_minor), 0) as revenue')
            ->first();

        return [
            'lifetime_minor' => $lifetime,
            'recent' => $recent,
            'favourites' => $favourites,
            'segments' => $customer->segments,
            'wallet' => $wallet,
            'stamps' => $stamps,
            'campaigns' => [
                'received' => (int) $campaigns?->getAttribute('received'),
                'orders' => (int) $campaigns?->getAttribute('orders'),
                'revenue_minor' => (int) $campaigns?->getAttribute('revenue'),
            ],
        ];
    }
}

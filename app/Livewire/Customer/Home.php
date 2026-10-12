<?php

declare(strict_types=1);

namespace App\Livewire\Customer;

use App\Domain\Branches\BranchSchedule;
use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Menu\BranchMenu;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Actions\Reorder;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\KitchenLoad;
use App\Domain\Ordering\Models\Order;
use App\Domain\Tenancy\Models\Restaurant;
use App\Livewire\Customer\Concerns\GuestPage;
use App\Support\Greeting;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

/**
 * The restaurant's front door: a warm greeting, "Your usual?" for returning
 * guests, favourites, and every branch with its live status, wait and
 * distance (when the guest shares location).
 */
class Home extends Component
{
    use GuestPage;

    public function mount(Restaurant $restaurant): void
    {
        $this->restaurant = $restaurant;
        $this->rememberSource();
    }

    /** One tap: the last order goes back in the bag, straight to checkout. */
    public function orderUsual(): void
    {
        $customer = $this->customer();
        $last = $customer === null ? null : Order::withoutTenancy()
            ->with(['items', 'branch'])
            ->where('customer_id', $customer->id)
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->latest('id')
            ->first();

        if ($last === null) {
            return;
        }

        $this->cart()->replace($this->restaurant->id, app(Reorder::class)->lines($last));
        $this->redirectRoute('customer.checkout', ['restaurant' => $this->restaurant->slug, 'branch' => $last->branch->code], navigate: true);
    }

    public function render(): View
    {
        $customer = $this->customer();
        $branches = $this->branches();
        $schedule = app(BranchSchedule::class);

        $last = $customer === null ? null : Order::withoutTenancy()
            ->with(['items', 'branch'])
            ->where('customer_id', $customer->id)
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->latest('id')
            ->first();

        $active = $customer === null ? null : Order::withoutTenancy()
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', [OrderStatus::PickedUp->value, OrderStatus::Cancelled->value])
            ->latest('id')
            ->first();

        $featuredBranch = $branches->firstWhere('id', $customer?->last_branch_id) ?? $branches->first();
        $highlights = $featuredBranch !== null ? $this->highlights($featuredBranch, $this->favoriteIds($customer)) : [];

        return $this->page('livewire.customer.home', [
            'customer' => $customer,
            'branches' => $branches,
            'schedule' => $schedule,
            'greeting' => Greeting::for($customer?->firstName(), $this->restaurant->timezone),
            'last' => $last,
            'active' => $active,
            'featuredBranch' => $featuredBranch,
            'highlights' => $highlights,
            'busy' => $this->busyLevels($branches),
        ]);
    }

    /**
     * Busy level and expected wait per branch, from the live kitchen load.
     *
     * @param  Collection<int, Branch>  $branches
     * @return array<int, array{level: string, minutes: int}>
     */
    private function busyLevels(Collection $branches): array
    {
        $load = app(KitchenLoad::class);

        return $branches->mapWithKeys(fn (Branch $branch): array => [$branch->id => [
            'level' => $load->level($branch),
            'minutes' => $load->estimate($branch),
        ]])->all();
    }

    /**
     * @return list<int>
     */
    private function favoriteIds(?Customer $customer): array
    {
        if ($customer === null) {
            return [];
        }

        return array_values($customer->favorites()->get(['products.id'])->map(fn (Product $product): int => $product->id)->all());
    }

    /**
     * @return Collection<int, Branch>
     */
    private function branches(): Collection
    {
        return Branch::withoutTenancy()
            ->where('restaurant_id', $this->restaurant->id)
            ->with(['hours', 'closures'])
            ->orderBy('sort')
            ->get()
            ->each(fn (Branch $b) => $b->setRelation('restaurant', $this->restaurant));
    }

    /**
     * Favourites, popular and new items at the guest's branch, orderable now.
     *
     * @param  list<int>  $favoriteIds
     * @return array{favorites: list<array<string, mixed>>, popular: list<array<string, mixed>>, new: list<array<string, mixed>>}
     */
    private function highlights(Branch $branch, array $favoriteIds): array
    {
        $items = collect(app(BranchMenu::class)->sections($branch))
            ->flatMap(fn (array $section): array => $section['items'])
            ->filter(fn (array $item): bool => $item['offer']->isAvailable());

        return [
            'favorites' => array_values($items->filter(fn (array $i): bool => in_array($i['product']->id, $favoriteIds, true))->take(8)->values()->all()),
            'popular' => array_values($items->filter(fn (array $i): bool => $i['product']->is_popular)->take(8)->values()->all()),
            'new' => array_values($items->filter(fn (array $i): bool => $i['product']->is_new)->take(8)->values()->all()),
        ];
    }
}

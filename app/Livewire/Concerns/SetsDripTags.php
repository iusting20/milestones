<?php

namespace App\Livewire\Concerns;

use App\Enums\DripEnergy;
use App\Enums\DripMoney;
use App\Enums\DripTag;
use App\Support\Onboarding;
use Illuminate\Database\Eloquent\Model;
use ValueError;

trait SetsDripTags
{
    abstract protected function findDripTaggable(int $id): Model;

    public function setDripTag(int $id, ?string $tag): void
    {
        $item = $this->findDripTaggable($id);

        if ($this->cannotSetDripTag($item)) {
            return;
        }

        if ($tag === null || $tag === '') {
            $item->update(['drip_tag' => null]);

            return;
        }

        try {
            $dripTag = DripTag::from($tag);
        } catch (ValueError) {
            return;
        }

        if ($item->drip_tag === $dripTag) {
            $item->update(['drip_tag' => null]);

            return;
        }

        $item->update(['drip_tag' => $dripTag]);
        Onboarding::complete('milestone.drip');
    }

    public function setDripMoney(int $id, ?string $money): void
    {
        $item = $this->findDripTaggable($id);

        if ($this->cannotSetDripTag($item)) {
            return;
        }

        if ($money === null || $money === '') {
            $item->update(['drip_tag' => null]);

            return;
        }

        try {
            $newMoney = DripMoney::from($money);
        } catch (ValueError) {
            return;
        }

        if ($item->drip_tag?->money() === $newMoney) {
            $item->update(['drip_tag' => null]);

            return;
        }

        $energy = $item->drip_tag?->energy() ?? DripEnergy::Drains;

        $item->update(['drip_tag' => DripTag::fromAxes($newMoney, $energy)]);
    }

    public function setDripEnergy(int $id, ?string $energy): void
    {
        $item = $this->findDripTaggable($id);

        if ($this->cannotSetDripTag($item)) {
            return;
        }

        if ($energy === null || $energy === '') {
            $item->update(['drip_tag' => null]);

            return;
        }

        try {
            $newEnergy = DripEnergy::from($energy);
        } catch (ValueError) {
            return;
        }

        if ($item->drip_tag?->energy() === $newEnergy) {
            $item->update(['drip_tag' => null]);

            return;
        }

        $money = $item->drip_tag?->money() ?? DripMoney::Low;

        $item->update(['drip_tag' => DripTag::fromAxes($money, $newEnergy)]);
    }

    protected function cannotSetDripTag(Model $item): bool
    {
        return (bool) ($item->getAttribute('is_separator') ?? false);
    }
}

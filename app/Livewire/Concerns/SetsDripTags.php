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
            $this->writeDripAxes($item, null, null);

            return;
        }

        try {
            $dripTag = DripTag::from($tag);
        } catch (ValueError) {
            return;
        }

        if ($item->drip_tag === $dripTag) {
            $this->writeDripAxes($item, null, null);

            return;
        }

        $this->writeDripAxes($item, $dripTag->money(), $dripTag->energy());
        Onboarding::complete('milestone.drip');
    }

    public function setDripMoney(int $id, ?string $money): void
    {
        $item = $this->findDripTaggable($id);

        if ($this->cannotSetDripTag($item)) {
            return;
        }

        $currentMoney = $item->drip_money ?? $item->drip_tag?->money();
        $energy = $item->drip_energy ?? $item->drip_tag?->energy();

        if ($money === null || $money === '') {
            $this->writeDripAxes($item, null, $energy);

            return;
        }

        try {
            $newMoney = DripMoney::from($money);
        } catch (ValueError) {
            return;
        }

        if ($currentMoney === $newMoney) {
            $this->writeDripAxes($item, null, $energy);

            return;
        }

        $this->writeDripAxes($item, $newMoney, $energy);

        if ($energy !== null) {
            Onboarding::complete('milestone.drip');
        }
    }

    public function setDripEnergy(int $id, ?string $energy): void
    {
        $item = $this->findDripTaggable($id);

        if ($this->cannotSetDripTag($item)) {
            return;
        }

        $money = $item->drip_money ?? $item->drip_tag?->money();
        $currentEnergy = $item->drip_energy ?? $item->drip_tag?->energy();

        if ($energy === null || $energy === '') {
            $this->writeDripAxes($item, $money, null);

            return;
        }

        try {
            $newEnergy = DripEnergy::from($energy);
        } catch (ValueError) {
            return;
        }

        if ($currentEnergy === $newEnergy) {
            $this->writeDripAxes($item, $money, null);

            return;
        }

        $this->writeDripAxes($item, $money, $newEnergy);

        if ($money !== null) {
            Onboarding::complete('milestone.drip');
        }
    }

    protected function cannotSetDripTag(Model $item): bool
    {
        return (bool) ($item->getAttribute('is_separator') ?? false);
    }

    protected function writeDripAxes(Model $item, ?DripMoney $money, ?DripEnergy $energy): void
    {
        $item->update([
            'drip_money' => $money,
            'drip_energy' => $energy,
            'drip_tag' => ($money !== null && $energy !== null)
                ? DripTag::fromAxes($money, $energy)
                : null,
        ]);
    }
}

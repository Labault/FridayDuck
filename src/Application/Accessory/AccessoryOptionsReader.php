<?php

declare(strict_types=1);

namespace App\Application\Accessory;

use App\Domain\Accessory\Accessory;
use App\Domain\Accessory\AccessoryRepository;
use App\Domain\Accessory\FridayAccessoryOption;
use App\Domain\Accessory\FridayAccessoryOptionRepository;

/**
 * Lit les options d'une édition en y joignant l'identité de l'accessoire (code,
 * label) — projection partagée par le GET (§24.1), l'événement de résultats
 * (§24.5) et le calcul du gagnant (§10.4).
 */
final readonly class AccessoryOptionsReader
{
    public function __construct(
        private AccessoryRepository $accessoryRepository,
        private FridayAccessoryOptionRepository $fridayAccessoryOptionRepository,
    ) {
    }

    /**
     * @return list<AccessoryOptionView> triées par display_order
     */
    public function forEdition(string $fridayEditionId): array
    {
        $options = $this->fridayAccessoryOptionRepository->findByEdition($fridayEditionId);
        if ([] === $options) {
            return [];
        }

        $accessoriesById = [];
        foreach ($this->accessoryRepository->findByIds(array_map(static fn (FridayAccessoryOption $fridayAccessoryOption): string => $fridayAccessoryOption->accessoryId(), $options)) as $accessory) {
            $accessoriesById[$accessory->id()] = $accessory;
        }

        $views = [];
        foreach ($options as $option) {
            $accessory = $accessoriesById[$option->accessoryId()] ?? null;
            if (!$accessory instanceof Accessory) {
                continue;
            }
            $views[] = new AccessoryOptionView($accessory->code(), $accessory->label(), $option->displayOrder(), $option->voteCount());
        }

        return $views;
    }
}

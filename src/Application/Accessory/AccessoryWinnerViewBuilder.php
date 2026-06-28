<?php

declare(strict_types=1);

namespace App\Application\Accessory;

use App\Domain\Accessory\Accessory;
use App\Domain\Accessory\AccessoryRepository;

/**
 * Construit la projection riche du gagnant à partir de son code (extension de
 * sérialisation, pas de logique : la désignation reste {@see ResolveAccessoryWinner}).
 */
final readonly class AccessoryWinnerViewBuilder
{
    public function __construct(private AccessoryRepository $accessoryRepository)
    {
    }

    public function fromCode(string $code): AccessoryWinnerView
    {
        $accessory = $this->accessoryRepository->findByCode($code);
        if (!$accessory instanceof Accessory) {
            // Le gagnant vient toujours du catalogue ; absence = incohérence grave.
            throw new \RuntimeException(\sprintf('Accessoire gagnant introuvable au catalogue : %s.', $code));
        }

        return new AccessoryWinnerView(
            $accessory->code(),
            $accessory->label(),
            $accessory->slot()->value,
            $accessory->svgGroupId(),
        );
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the Akeneo PIM Enterprise Edition.
 *
 * (c) 2020 Akeneo SAS (http://www.akeneo.com)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Akeneo\Pim\Automation\DataQualityInsights\Domain;

use Akeneo\Pim\Automation\DataQualityInsights\Domain\ValueObject\AxisCode;
use Akeneo\Pim\Automation\DataQualityInsights\Domain\ValueObject\CriterionCode;

interface Axis
{
    public function getCode(): AxisCode;

    public function getCriteriaCodes(): array;

    public function getCriterionCoefficient(CriterionCode $criterionCode): int;
}

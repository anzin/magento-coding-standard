<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento2\TestsHelper\PHP;

/**
 * Interface to test method return type.
 */
interface MethodSomeInterface
{
    /**
     * @return array
     */
    public function testReturnTypeWrong(): array;
}

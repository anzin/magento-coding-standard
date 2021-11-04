<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento2\TestsHelper\PHP;

/**
 * Class to test return method type.
 */
class MethodSomeClass
{
    /**
     * @return array
     */
    public function testClassMethodWrong()
    {
        return [];
    }

    /**
     * @return array
     */
    public function testClassMethod(): array
    {
        return [];
    }
}

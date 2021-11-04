<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento2\Tests\PHP;

use Magento2\Tests\PHP\MethodSomeClass;
use Magento2\Tests\PHP\MethodSomeInterface;

/**
 * Class to test method return type.
 */
class MethodValidateReturnType extends MethodSomeClass implements MethodSomeInterface
{
    /**
     * @return bool
     */
    public function testClassMethodWrong(): bool
    {
        return true;
    }

    /**
     * @return array
     */
    public function testClassMethod(): array
    {
        return [];
    }

    /**
     * @return string
     */
    public function testReturnTypeWrong(): string
    {
        return '';
    }

    /**
     * @return string
     */
    public function testReturnType(): string
    {
        return '';
    }
}

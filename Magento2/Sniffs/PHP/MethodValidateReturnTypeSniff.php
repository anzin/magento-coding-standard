<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento2\Sniffs\PHP;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Sniffs\Sniff;
use ReflectionClass;
use ReflectionException;

/**
 * Sniff to validate method return type.
 */
class MethodValidateReturnTypeSniff implements Sniff
{
    /**
     * String representation of warning.
     *
     * @var string
     */
    private $warningMessage = 'The return method type must be compatible with parent return type.';

    /**
     * Warning violation code.
     *
     * @var string
     */
    private $warningCode = 'MethodValidateReturnType';

    /**
     * @var string
     */
    private $currentMethodName;

    /**
     * @var string
     */
    private $methodReturnType;

    /**
     * @inheritdoc
     */
    public function register(): array
    {
        return [
            T_FUNCTION
        ];
    }

    /**
     * @inheritdoc
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        if (preg_match('/(?i)__construct/', $tokens[$stackPtr + 2]['content'])) {
            return;
        }

        $this->currentMethodName = $tokens[$stackPtr + 2]['content'];
        $this->methodReturnType = $this->getMethodReturnType($phpcsFile, $stackPtr);

        if ($this->checkExtendMethodReturnType($phpcsFile) || $this->checkImplementsMethodReturnType($phpcsFile)) {
            $phpcsFile->addWarning($this->warningMessage, $stackPtr, $this->warningCode);
        }
    }

    /**
     * Method to check return type on parent method.
     *
     * @param File $phpcsFile
     *
     * @return bool
     */
    private function checkExtendMethodReturnType(File $phpcsFile): bool
    {
        $useStringDataPath = $this->getFullPathNameFromUse($phpcsFile);

        if (!$useStringDataPath) {
            return false;
        }

        $phpcsFile = $this->getPhpCsFile(current($useStringDataPath));
        $tokens = $phpcsFile->getTokens();
        $methodData = array_keys(array_column($tokens, 'content'), $this->currentMethodName);

        if (!$methodData) {
            return $this->checkExtendMethodReturnType($phpcsFile);
        }

        $subMethodReturnType = $this->getMethodReturnType($phpcsFile, current($methodData));

        if ($subMethodReturnType &&  $subMethodReturnType !== $this->methodReturnType) {
            return true;
        }

        return false;
    }


    /**
     * Method to check return type on parent method.
     *
     * @param File $phpcsFile
     * @param string $type
     *
     * @return bool
     */
    private function checkImplementsMethodReturnType(File $phpcsFile, string $type = 'implements'): bool
    {
        $types = $this->getFullPathNameFromUse($phpcsFile, $type);

        foreach ($types as $path) {
            $phpcsFile = $this->getPhpCsFile($path);
            $tokens = $phpcsFile->getTokens();
            $methodData = array_keys(array_column($tokens, 'content'), $this->currentMethodName);

            if (!$methodData) {
                continue;
            }

            $subMethodReturnType = $this->getMethodReturnType($phpcsFile, current($methodData));

            if ($subMethodReturnType && $subMethodReturnType !== $this->methodReturnType) {
                return true;
            }
        }

        foreach ($types as $path) {
            $phpcsFile = $this->getPhpCsFile($path);
            $result =  $this->checkImplementsMethodReturnType($phpcsFile, 'extends');

            if ($result) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get method return type.
     *
     * @param File $phpcsFile
     * @param int $methodTokenKey
     *
     * @return string
     */
    private function getMethodReturnType(File $phpcsFile, int $methodTokenKey): string
    {
        $endSearchTokenKey = $phpcsFile->findNext(T_OPEN_CURLY_BRACKET, $methodTokenKey) ?:
            $phpcsFile->findNext(T_SEMICOLON, $methodTokenKey) ;
        $returnTypeToken = $phpcsFile->findNext(T_COLON, $methodTokenKey, $endSearchTokenKey);

        return !($returnTypeToken && $endSearchTokenKey) ? '' :
            trim($phpcsFile->getTokensAsString($returnTypeToken + 1, $endSearchTokenKey - $returnTypeToken - 1));
    }

    /**
     * Method to get full path data from use.
     *
     * @param File $phpcsFile
     * @param string $type
     *
     * @return array
     */
    private function getFullPathNameFromUse(File $phpcsFile, string $type = 'extends'): array
    {
        $tokens = $phpcsFile->getTokens();
        $classTokenKey = current(array_keys(array_column($tokens, 'content'), 'class'));

        if (!$classTokenKey) {
            $classTokenKey = current(array_keys(array_column($tokens, 'content'), 'interface'));
        }

        $typeNames = $type === 'implements' ? $phpcsFile->findImplementedInterfaceNames($classTokenKey) :
            $this->getClassExtendsPath($phpcsFile, $classTokenKey);
        $foundTypes = [];

        if ($typeNames) {
            foreach ($typeNames as $name) {
                $typeCandidates[] = $name;

                foreach ($this->getUsesFromFile($phpcsFile) as $use) {
                    $typeCandidates[] = rtrim($use, '\\') . '\\' . ltrim($name, '\\');
                }

                $resolvedType = $this->resolveTypeFromPaths($typeCandidates);
                $typeCandidates = [];

                if ($resolvedType) {
                    $foundTypes[] = $resolvedType;
                }
            }
        }

        return $foundTypes;
    }

    /**
     * Get all uses from file.
     *
     * @param File $phpcsFile
     *
     * @return array
     */
    private function getUsesFromFile(File $phpcsFile): array
    {
        $tokens = $phpcsFile->getTokens();
        $useNameData = array_keys(array_column($tokens, 'content'), 'use');
        $uses = [];

        foreach ($useNameData as $useName) {
            $semicolon = $phpcsFile->findNext(T_SEMICOLON, $useName);
            $useData = array_slice($tokens, $useName, $semicolon - $useName);
            $useStringPathArray = [];

            foreach ($useData as $useString) {
                if ($useString['type'] === 'T_STRING') {
                    $useStringPathArray[] = $useString['content'];
                }
            }

            array_pop($useStringPathArray);

            $uses[] = '\\' . implode('\\', $useStringPathArray);
        }

        return $uses;
    }

    /**
     * Resolve type from provided paths.
     *
     * @param array $paths
     *
     * @return string|null
     */
    private function resolveTypeFromPaths(array $paths): ?string
    {
        foreach ($paths as $typeCandidate) {
            try {
                new ReflectionClass($typeCandidate);

                return $typeCandidate;
            } catch (ReflectionException $exception) {
                continue;
            }
        }

        return null;
    }

    /**
     * Get class extends name data.
     *
     * @param File $phpcsFile
     * @param int $stackPtr
     *
     * @return array
     */
    private function getClassExtendsPath(File $phpcsFile, int $stackPtr): array
    {
        $startStackPtr = $phpcsFile->findNext(T_IMPLEMENTS, $stackPtr) ?:
            $phpcsFile->findNext(T_OPEN_CURLY_BRACKET, $stackPtr);
        $extendTokenKey = $phpcsFile->findNext(T_EXTENDS, $stackPtr);

        if (!$extendTokenKey) {
            return [];
        }

        $string = $phpcsFile->getTokensAsString($extendTokenKey + 1, $startStackPtr - $extendTokenKey - 1);

        return array_map('trim', explode(',', $string));
    }

    /**
     * Get phpcs file by path.
     *
     * @param string $path
     *
     * @return File
     */
    private function getPhpCsFile(string $path): File
    {
        $absolutePath = (new ReflectionClass($path))->getFileName();
        $config = new Config();
        $ruleset = new Ruleset($config);
        $phpcsFile = new File($absolutePath, $ruleset, $config);

        $phpcsFile->setContent(file_get_contents($absolutePath));
        $phpcsFile->parse();

        return $phpcsFile;
    }
}

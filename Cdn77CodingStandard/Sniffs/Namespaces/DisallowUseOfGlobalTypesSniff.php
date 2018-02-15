<?php

declare(strict_types=1);

namespace Cdn77CodingStandard\Sniffs\Namespaces;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SlevomatCodingStandard\Helpers\TokenHelper;
use SlevomatCodingStandard\Helpers\UseStatementHelper;
use const T_USE;
use function ltrim;
use function strpos;
use function strtolower;

class DisallowUseOfGlobalTypesSniff implements Sniff
{
    public const CODE_USE_CONTAINS_GLOBAL_TYPE = 'UseContainsGlobalType';

    /**
     * @return int[]
     */
    public function register() : array
    {
        return [T_USE];
    }

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
     * @param int $pointer
     */
    public function process(File $file, $pointer) : void
    {
        if (
            UseStatementHelper::isTraitUse($file, $pointer)
            || UseStatementHelper::isAnonymousFunctionUse($file, $pointer)
        ) {
            return;
        }

        $nextElement = strtolower($file->getTokens()[TokenHelper::findNextEffective($file, $pointer + 1)]['content']);

        if ($nextElement === 'function' || $nextElement === 'const') {
            return;
        }

        $name = UseStatementHelper::getFullyQualifiedTypeNameFromUse($file, $pointer);

        if (strpos(ltrim($name, '\\'), '\\') !== false) {
            return;
        }

        $file->addError(
            'Use of global types is forbidden, reference it directly using FQCN.',
            $pointer,
            self::CODE_USE_CONTAINS_GLOBAL_TYPE
        );
    }
}
